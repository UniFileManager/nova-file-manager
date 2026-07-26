<?php

declare(strict_types=1);

namespace UniFileManager\NovaFileManager\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use UniFileManager\Core\Exceptions\FolderNotEmpty;
use UniFileManager\Core\Exceptions\InvalidFilePath;
use UniFileManager\Core\Services\FileManager;

final class FileManagerController extends Controller
{
    public function __construct(private readonly FileManager $files) {}

    public function index(Request $request): JsonResponse
    {
        $manager = $this->manager($request);

        return response()->json([
            'data' => $manager->list($request->user(), $this->path($request)),
        ]);
    }

    public function storeFolder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['nullable', 'string'],
            'name' => ['nullable', 'string'],
            'area' => ['nullable', 'string'],
        ]);

        $path = $this->manager($request)->createNewDirectory(
            $request->user(),
            (string) ($validated['path'] ?? ''),
            (string) ($validated['name'] ?? 'New folder'),
        );

        return response()->json(['path' => $path], 201);
    }

    public function storeUpload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['nullable', 'string'],
            'area' => ['nullable', 'string'],
            'file' => ['required', 'file'],
        ]);

        $path = $this->manager($request)->upload(
            $request->user(),
            $request->file('file'),
            (string) ($validated['path'] ?? ''),
        );

        return response()->json(['path' => $path], 201);
    }

    public function rename(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string'],
            'name' => ['required', 'string'],
            'area' => ['nullable', 'string'],
        ]);

        $path = $this->manager($request)->rename(
            $request->user(),
            $validated['path'],
            $validated['name'],
        );

        return response()->json(['path' => $path]);
    }

    public function move(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string'],
            'destination' => ['required', 'string'],
            'area' => ['nullable', 'string'],
        ]);

        $path = $this->manager($request)->move(
            $request->user(),
            $validated['path'],
            $validated['destination'],
        );

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
}
