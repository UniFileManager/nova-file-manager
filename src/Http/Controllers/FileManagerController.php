<?php

declare(strict_types=1);

namespace UniFileManager\NovaFileManager\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use UniFileManager\Core\Contracts\StorageAreaResolver;
use UniFileManager\Core\Exceptions\FolderNotEmpty;
use UniFileManager\Core\Exceptions\InvalidFilePath;
use UniFileManager\Core\Services\FileManager;

final class FileManagerController extends Controller
{
    public function __construct(
        private readonly FileManager $files,
        private readonly StorageAreaResolver $storageAreas,
    ) {}

    public function storageAreas(): JsonResponse
    {
        return response()->json([
            'data' => array_values(array_map(
                fn (string $key, array $area): array => [
                    'key' => $key,
                    'label' => $this->areaLabel($key, $area),
                    'visibility' => (string) ($area['visibility'] ?? 'private'),
                    'default' => $key === (string) config('nova-file-manager.default_area', 'private'),
                ],
                array_keys($this->enabledStorageAreas()),
                $this->enabledStorageAreas(),
            )),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $manager = $this->manager($request);

            return response()->json([
                'data' => array_map(
                    fn (array $item): array => $this->decorateItem($request, $item),
                    $manager->list($request->user(), $this->path($request)),
                ),
            ]);
        } catch (InvalidFilePath $exception) {
            return $this->invalidRequest($exception);
        }
    }

    public function storeFolder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['nullable', 'string'],
            'name' => ['nullable', 'string'],
            'area' => ['nullable', 'string'],
        ]);

        try {
            $path = $this->manager($request)->createNewDirectory(
                $request->user(),
                (string) ($validated['path'] ?? ''),
                (string) ($validated['name'] ?? 'New folder'),
            );
        } catch (InvalidFilePath $exception) {
            return $this->invalidRequest($exception);
        }

        return response()->json(['path' => $path], 201);
    }

    public function storeUpload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['nullable', 'string'],
            'area' => ['nullable', 'string'],
            'file' => ['required', 'file'],
        ]);

        try {
            $path = $this->manager($request)->upload(
                $request->user(),
                $request->file('file'),
                (string) ($validated['path'] ?? ''),
            );
        } catch (InvalidFilePath $exception) {
            return $this->invalidRequest($exception);
        }

        return response()->json(['path' => $path], 201);
    }

    public function rename(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string'],
            'name' => ['required', 'string'],
            'area' => ['nullable', 'string'],
        ]);

        try {
            $path = $this->manager($request)->rename(
                $request->user(),
                $validated['path'],
                $validated['name'],
            );
        } catch (InvalidFilePath $exception) {
            return $this->invalidRequest($exception);
        }

        return response()->json(['path' => $path]);
    }

    public function moveDestinations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string'],
            'area' => ['nullable', 'string'],
        ]);

        try {
            return response()->json([
                'data' => collect($this->manager($request)->moveDestinations($request->user(), $validated['path']))
                    ->map(fn (string $label, string $path): array => ['path' => $path, 'label' => $label])
                    ->values()
                    ->all(),
            ]);
        } catch (InvalidFilePath $exception) {
            return $this->invalidRequest($exception);
        }
    }

    public function move(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string'],
            'destination' => ['required', 'string'],
            'area' => ['nullable', 'string'],
        ]);

        try {
            $path = $this->manager($request)->move(
                $request->user(),
                $validated['path'],
                $validated['destination'],
            );
        } catch (InvalidFilePath $exception) {
            return $this->invalidRequest($exception);
        }

        return response()->json(['path' => $path]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paths' => ['required', 'array'],
            'paths.*' => ['required', 'string'],
            'area' => ['nullable', 'string'],
        ]);

        $deleted = 0;
        $nonEmptyFolders = 0;
        $failed = 0;
        $manager = $this->manager($request);

        foreach ($validated['paths'] as $path) {
            try {
                $manager->delete($request->user(), $path);
                $deleted++;
            } catch (FolderNotEmpty) {
                $nonEmptyFolders++;
            } catch (InvalidFilePath) {
                $failed++;
            }
        }

        return response()->json([
            'deleted' => $deleted,
            'non_empty_folders' => $nonEmptyFolders,
            'failed' => $failed,
        ]);
    }

    public function preview(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string'],
            'area' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'boolean'],
        ]);

        $manager = $this->manager($request);

        return $request->boolean('thumbnail')
            ? $manager->thumbnail($request->user(), $validated['path'])
            : $manager->preview($request->user(), $validated['path']);
    }

    public function download(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string'],
            'area' => ['nullable', 'string'],
        ]);

        return $this->manager($request)->download($request->user(), $validated['path']);
    }

    public function selectableFile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string'],
            'area' => ['nullable', 'string'],
        ]);

        $manager = $this->manager($request);
        $file = $manager->selectableFile($request->user(), $validated['path']);

        return response()->json([
            'data' => [
                ...$file,
                'public_url' => $manager->publicUrl($request->user(), $validated['path']),
            ],
        ]);
    }

    private function manager(Request $request): FileManager
    {
        return $this->files->forArea($this->area($request));
    }

    private function area(Request $request): string
    {
        $area = $request->string('area')->toString();

        return $area !== '' ? $area : (string) config('nova-file-manager.default_area', 'private');
    }

    private function path(Request $request): string
    {
        return $request->string('path')->toString();
    }

    private function decorateItem(Request $request, array $item): array
    {
        $area = $this->area($request);
        $path = (string) ($item['path'] ?? '');
        $type = (string) ($item['type'] ?? '');

        if ($type !== 'file') {
            return $item;
        }

        return [
            ...$item,
            'preview_url' => route('nova-file-manager.items.preview', ['area' => $area, 'path' => $path]),
            'thumbnail_url' => route('nova-file-manager.items.preview', ['area' => $area, 'path' => $path, 'thumbnail' => 1]),
            'download_url' => route('nova-file-manager.items.download', ['area' => $area, 'path' => $path]),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function enabledStorageAreas(): array
    {
        return array_filter(
            $this->storageAreas->areas(),
            static fn (mixed $area): bool => is_array($area) && ($area['enabled'] ?? false),
        );
    }

    /** @param array<string, mixed> $area */
    private function areaLabel(string $key, array $area): string
    {
        if (isset($area['label']) && is_string($area['label']) && $area['label'] !== '') {
            return $area['label'];
        }

        return match ((string) ($area['visibility'] ?? $key)) {
            'public' => 'Public files',
            'private' => 'Private files',
            default => str($key)->replace(['-', '_'], ' ')->title()->toString(),
        };
    }

    private function invalidRequest(InvalidFilePath|AccessDeniedHttpException $exception): JsonResponse
    {
        return response()->json(['message' => $exception->getMessage()], $exception instanceof AccessDeniedHttpException ? 403 : 422);
    }
}
