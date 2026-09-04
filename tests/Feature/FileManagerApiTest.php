<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('testing');
});

it('lists files with preview and download URLs', function (): void {
    Storage::disk('testing')->put('tenant-a/example.txt', 'Hello Nova');

    $this->getJson('/nova-vendor/unifilemanager/nova-file-manager/items')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'example.txt')
        ->assertJsonPath('data.0.type', 'file')
        ->assertJsonPath('data.0.preview_url', route('nova-file-manager.items.preview', [
            'area' => 'private',
            'path' => 'example.txt',
        ]))
        ->assertJsonPath('data.0.download_url', route('nova-file-manager.items.download', [
            'area' => 'private',
            'path' => 'example.txt',
        ]));
});

it('returns enabled storage areas for the browser UI', function (): void {
    config()->set('nova-file-manager.storage_areas.public', [
        'enabled' => true,
        'disk' => 'testing',
        'root' => 'tenant-a-public',
        'visibility' => 'public',
    ]);

    $this->getJson('/nova-vendor/unifilemanager/nova-file-manager/storage-areas')
        ->assertOk()
        ->assertJsonPath('data.0.key', 'private')
        ->assertJsonPath('data.1.key', 'public')
        ->assertJsonPath('data.1.label', 'Public files');
});

it('lists files from a custom configured storage area', function (): void {
    config()->set('nova-file-manager.storage_areas.documents', [
        'enabled' => true,
        'disk' => 'testing',
        'root' => 'tenant-documents',
        'visibility' => 'private',
    ]);

    Storage::disk('testing')->put('tenant-documents/contract.txt', 'Custom area');

    $this->getJson('/nova-vendor/unifilemanager/nova-file-manager/items?area=documents')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'contract.txt')
        ->assertJsonPath('data.0.path', 'contract.txt');
});

it('returns validation errors for traversal attempts', function (): void {
    $this->getJson('/nova-vendor/unifilemanager/nova-file-manager/items?path=../secrets')
        ->assertStatus(422)
        ->assertJsonPath('message', 'Paths must stay within the configured root.');
});

it('uploads files without overwriting existing names', function (): void {
    Storage::disk('testing')->put('tenant-a/report.txt', 'Old');

    $file = UploadedFile::fake()->createWithContent('report.txt', 'New');

    $this->postJson('/nova-vendor/unifilemanager/nova-file-manager/uploads', [
        'file' => $file,
    ])
        ->assertCreated()
        ->assertJsonPath('path', 'report (2).txt');

    Storage::disk('testing')->assertExists('tenant-a/report.txt');
    Storage::disk('testing')->assertExists('tenant-a/report (2).txt');
});

it('returns safe move destinations from the core package', function (): void {
    Storage::disk('testing')->makeDirectory('tenant-a/Source');
    Storage::disk('testing')->makeDirectory('tenant-a/Target');

    $this->getJson('/nova-vendor/unifilemanager/nova-file-manager/items/move-destinations?path=Source')
        ->assertOk()
        ->assertJsonFragment([
            'path' => 'Target',
            'label' => 'Target',
        ])
        ->assertJsonMissing([
            'path' => 'Source',
        ]);
});
