<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use UniFileManager\NovaFileManager\Http\Controllers\FileManagerController;

Route::middleware(config('nova-file-manager.middleware', ['nova']))
    ->prefix(config('nova-file-manager.route_prefix', 'nova-vendor/unifilemanager/nova-file-manager'))
    ->name('nova-file-manager.')
    ->group(function (): void {
        Route::get('/items', [FileManagerController::class, 'index'])->name('items.index');
        Route::post('/folders', [FileManagerController::class, 'storeFolder'])->name('folders.store');
        Route::post('/uploads', [FileManagerController::class, 'storeUpload'])->name('uploads.store');
        Route::patch('/items/rename', [FileManagerController::class, 'rename'])->name('items.rename');
        Route::patch('/items/move', [FileManagerController::class, 'move'])->name('items.move');
        Route::delete('/items', [FileManagerController::class, 'destroy'])->name('items.destroy');
    });
