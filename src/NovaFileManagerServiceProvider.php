<?php

declare(strict_types=1);

namespace UniFileManager\NovaFileManager;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Http\Middleware\Authenticate;
use Laravel\Nova\Nova;
use UniFileManager\Core\Contracts\FileManagerAuthorizer;
use UniFileManager\Core\Contracts\StorageAreaResolver;
use UniFileManager\Core\Services\FileManager;
use UniFileManager\Core\Services\ImageThumbnailer;
use UniFileManager\Core\Support\ConfigStorageAreaResolver;
use UniFileManager\Core\Support\DefaultFileManagerAuthorizer;

final class NovaFileManagerServiceProvider extends ServiceProvider
{
    private static bool $novaAssetsRegistered = false;

    public function register(): void
    {
        $this->ensureCorePackageIsAvailable();

        $this->mergeConfigFrom(__DIR__.'/../config/nova-file-manager.php', 'nova-file-manager');
        $this->syncCoreConfig();

        $this->app->bind(
            FileManagerAuthorizer::class,
            function (): FileManagerAuthorizer {
                $authorizer = $this->resolveConfigClass(
                    config('nova-file-manager.authorizer'),
                    DefaultFileManagerAuthorizer::class,
                );

                return $authorizer === DefaultFileManagerAuthorizer::class
                    ? new DefaultFileManagerAuthorizer
                    : $this->app->make($authorizer);
            },
        );

        $this->app->bind(
            StorageAreaResolver::class,
            function (): StorageAreaResolver {
                $this->syncCoreConfig();

                $resolver = $this->resolveConfigClass(
                    config('nova-file-manager.storage_area_resolver'),
                    ConfigStorageAreaResolver::class,
                );

                return $resolver === ConfigStorageAreaResolver::class
                    ? new ConfigStorageAreaResolver
                    : $this->app->make($resolver);
            },
        );

        $this->app->singleton(FileManager::class, function (): FileManager {
            $this->syncCoreConfig();

            return new FileManager(
                $this->app->make(FileManagerAuthorizer::class),
                $this->app->make(ImageThumbnailer::class),
                $this->app->make(StorageAreaResolver::class),
            );
        });
    }

    public function boot(): void
    {
        $this->app->booted(function (): void {
            $this->routes();
        });

        $this->publishes([
            __DIR__.'/../config/nova-file-manager.php' => config_path('nova-file-manager.php'),
        ], 'nova-file-manager-config');

        RateLimiter::for('nova-file-manager-previews', static function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(max(1, (int) config('nova-file-manager.preview_rate_limit', 60)))
                ->by('nova-file-manager:preview:'.$key);
        });

        Nova::serving(function (ServingNova $event): void {
            Nova::provideToScript([
                'unifilemanager' => [
                    'apiBase' => '/'.trim((string) config('nova-file-manager.route_prefix', 'nova-vendor/unifilemanager/nova-file-manager'), '/'),
                    'defaultArea' => (string) config('nova-file-manager.default_area', 'private'),
                    'storageAreas' => $this->storageAreasForScript(),
                    'maxUploadFiles' => max(1, (int) config('nova-file-manager.max_upload_files', 10)),
                ],
            ]);
        });

        $this->registerNovaAssets();
    }

    private function routes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Nova::router(config('nova-file-manager.middleware', ['nova', Authenticate::class]), 'file-manager')
            ->group(__DIR__.'/../routes/inertia.php');

        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }

    private function registerNovaAssets(): void
    {
        if (self::$novaAssetsRegistered || ! class_exists(Nova::class)) {
            return;
        }

        $script = __DIR__.'/../dist/js/tool.js';
        $style = __DIR__.'/../dist/css/tool.css';

        if (file_exists($script)) {
            Nova::script('unifilemanager-nova-file-manager-'.filemtime($script), $script);
        }

        if (file_exists($style)) {
            Nova::style('unifilemanager-nova-file-manager-'.filemtime($style), $style);
        }

        self::$novaAssetsRegistered = true;
    }

    /**
     * @return array<int, array{key: string, label: string, visibility: string, default: bool}>
     */
    private function storageAreasForScript(): array
    {
        $areas = config('nova-file-manager.storage_areas', []);

        if (! is_array($areas)) {
            return [];
        }

        $defaultArea = (string) config('nova-file-manager.default_area', 'private');
        $enabledAreas = array_filter(
            $areas,
            static fn (mixed $area): bool => is_array($area) && ($area['enabled'] ?? true) === true,
        );

        return array_values(array_map(
            fn (string $key, array $area): array => [
                'key' => $key,
                'label' => $this->storageAreaLabel($key, $area),
                'visibility' => (string) ($area['visibility'] ?? 'private'),
                'default' => $key === $defaultArea,
            ],
            array_keys($enabledAreas),
            $enabledAreas,
        ));
    }

    /**
     * @param  array<string, mixed>  $area
     */
    private function storageAreaLabel(string $key, array $area): string
    {
        if (isset($area['label']) && is_string($area['label']) && $area['label'] !== '') {
            return $area['label'];
        }

        return match ((string) ($area['visibility'] ?? 'private')) {
            'public' => $key === 'public' ? 'Public files' : str($key)->headline()->toString(),
            default => $key === 'private' ? 'Private files' : str($key)->headline()->toString(),
        };
    }

    private function ensureCorePackageIsAvailable(): void
    {
        if (class_exists(FileManager::class)
            && class_exists(ConfigStorageAreaResolver::class)
            && class_exists(DefaultFileManagerAuthorizer::class)) {
            return;
        }

        throw new \LogicException(
            'UniFileManager for Nova requires the unifilemanager/core package. Run "composer require unifilemanager/core" or update this package with Composer.',
        );
    }

    private function syncCoreConfig(): void
    {
        config([
            'unifilemanager' => array_replace_recursive(
                config('unifilemanager', []),
                config('nova-file-manager', []),
                [
                    'file_picker_default_area' => config('nova-file-manager.default_area', 'private'),
                    'authorizer' => $this->resolveConfigClass(
                        config('nova-file-manager.authorizer'),
                        DefaultFileManagerAuthorizer::class,
                    ),
                    'storage_area_resolver' => $this->resolveConfigClass(
                        config('nova-file-manager.storage_area_resolver'),
                        ConfigStorageAreaResolver::class,
                    ),
                ],
            ),
        ]);
    }

    private function resolveConfigClass(mixed $configuredClass, string $defaultClass): string
    {
        return is_string($configuredClass) && $configuredClass !== ''
            ? $configuredClass
            : $defaultClass;
    }
}
