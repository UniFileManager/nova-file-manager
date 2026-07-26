# UniFileManager for Laravel Nova

> File management for Laravel Nova, powered by `unifilemanager/core`.

This package is the Nova adapter for UniFileManager. It will provide a Nova
File Manager tool and a `UniFilePicker` field while sharing the same storage,
path validation, upload, rename, move, delete, and thumbnail logic used by the
Filament package.

## Status

This package is in early development. The backend adapter, configuration, routes,
Nova Tool skeleton, and Nova Field skeleton are scaffolded. The Vue interface is
the next major step.

## Requirements

- PHP 8.2 or later
- Laravel 11, 12, or 13
- Laravel Nova 4 or 5
- `unifilemanager/core`

## Installation

```bash
composer require unifilemanager/nova-file-manager
php artisan vendor:publish --tag=nova-file-manager-config
```

Register the Nova tool in your Nova service provider:

```php
use Laravel\Nova\Nova;
use UniFileManager\NovaFileManager\Nova\FileManagerTool;

Nova::tools([
    new FileManagerTool(),
]);
```

## Field usage

```php
use UniFileManager\NovaFileManager\Nova\Fields\UniFilePicker;

UniFilePicker::make('Thumbnail')
    ->directory('course-thumbnails')
    ->publicMedia()
    ->allowedMimeTypes(['image/*']);
```

Multiple files:

```php
UniFilePicker::make('Gallery')
    ->multiple()
    ->maxFiles(10)
    ->directory('course-gallery')
    ->publicMedia();
```

## Configuration

The package publishes `config/nova-file-manager.php`. It maps Nova-specific
settings into `unifilemanager/core`, so the shared backend service always uses
the active Nova configuration.

Private media is enabled by default. Public media should be enabled only when
your application has a public disk or CDN configured deliberately.

## Development

```bash
composer install
composer test
composer lint
```

Nova itself is a licensed package, so local installation requires access to
Nova's Composer repository.
