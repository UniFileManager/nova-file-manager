# UniFileManager for Laravel Nova

> File management for Laravel Nova, powered by `unifilemanager/core`.

This package is the Nova adapter for UniFileManager. It provides a Nova
File Manager tool and a `UniFilePicker` field while sharing the same storage,
path validation, upload, rename, move, delete, and thumbnail logic used by the
Filament package.

## Status

This package is in active development and ready for local testing. The Nova tool,
field, API routes, upload flow, previews, move support, and storage-area browser
are implemented. Treat it as a beta until the Nova 4/5 CI matrix and more
browser-level UI testing are complete.

## Requirements

- PHP 8.2 or later
- Laravel 11, 12, or 13
- Laravel Nova 4 or 5
- `unifilemanager/core`

## Installation

Laravel Nova is a private Composer package. Make sure your Nova credentials are
configured before installing:

```bash
composer config http-basic.nova.laravel.com your-nova-account-email your-license-key
```

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

Define who can manage files. The default authorizer checks Laravel's
`manageFileManager` ability:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('manageFileManager', function ($user): bool {
    return (bool) ($user->is_admin ?? false);
});
```

For a local test app without roles, you can temporarily allow every Nova user:

```php
Gate::define('manageFileManager', fn ($user): bool => true);
```

Do not leave the temporary version in production.

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

Useful field options:

```php
UniFilePicker::make('Documents')
    ->multiple()
    ->maxFiles(5)
    ->privateMedia()
    ->directory('documents')
    ->allowedMimeTypes(['application/pdf', 'text/plain'])
    ->allowDuplicateSelection(false)
    ->clearable()
    ->uploadHeading('Choose documents')
    ->uploadDescription('Select existing files or upload new documents.');
```

- `publicMedia()` browses the configured public storage area.
- `privateMedia()` browses the configured private storage area.
- `storageArea('area-name')` browses any custom server-defined storage area.
- `directory('avatars')` limits the field browser to that folder.
- `multiple()` stores selected paths as a JSON array string.
- `imageCardView()` changes multiple selected files from compact rows to cards.
- `allowedMimeTypes()` filters files in the picker UI. Keep matching validation
  rules in your Nova resource or model request when the value is saved.

## Configuration

The package publishes `config/nova-file-manager.php`. It maps Nova-specific
settings into `unifilemanager/core`, so the shared backend service always uses
the active Nova configuration.

Private media is enabled by default. Public media should be enabled only when
your application has a public disk or CDN configured deliberately.

By default, package routes use Nova authentication middleware:

```php
'middleware' => ['nova', \Laravel\Nova\Http\Middleware\Authenticate::class],
```

Do not use the `nova.auth` middleware alias if you need Nova 4 support. Nova 4
does not register that alias, so use Nova's middleware class instead.

If you already published the config before this default changed, update your
local `config/nova-file-manager.php` manually or publish the config again and
merge your storage settings.

Enable public media when selected files should be usable on a public website:

```php
'storage_areas' => [
    'private' => [
        'enabled' => true,
        'disk' => 'local',
        'root' => 'nova-file-manager/private',
        'visibility' => 'private',
    ],

    'public' => [
        'enabled' => true,
        'disk' => 'public',
        'root' => 'media',
        'visibility' => 'public',
    ],
],
```

When more than one area is enabled, the File Manager shows a storage switcher.
A field can target one area with `publicMedia()`, `privateMedia()`, or
`storageArea('area-name')`.

## File Manager tool

The Nova tool supports:

- browsing private and public storage areas;
- creating folders;
- uploading files without overwriting existing names;
- previewing images, PDFs, and text files;
- downloading files;
- renaming files and folders;
- moving files and folders to safe destinations;
- selecting files or folders for bulk deletion;
- sorting and paginating directory contents.

## Development

```bash
composer install
composer test
composer lint
npm install
npm run build
```

Nova itself is a licensed package, so local installation requires access to
Nova's Composer repository.

## GitHub Actions

The test workflow expects Nova Composer credentials to be available as
repository secrets:

- `NOVA_USERNAME`
- `NOVA_LICENSE_KEY`

Without those secrets, Composer cannot install `laravel/nova` in CI.
