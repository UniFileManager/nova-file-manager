<?php

declare(strict_types=1);

namespace UniFileManager\NovaFileManager\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use UniFileManager\NovaFileManager\NovaFileManagerServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            NovaFileManagerServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('nova-file-manager.storage_areas.private', [
            'enabled' => true,
            'disk' => 'testing',
            'root' => 'tenant-a',
            'visibility' => 'private',
        ]);
        $app['config']->set('filesystems.disks.testing', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/testing'),
        ]);
    }
}
