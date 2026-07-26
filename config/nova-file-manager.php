<?php

declare(strict_types=1);

use UniFileManager\Core\Support\ConfigStorageAreaResolver;
use UniFileManager\Core\Support\DefaultFileManagerAuthorizer;

return [
    /*
     * API routes used by the Nova tool and field. These routes are loaded with
     * Nova's middleware by default, so only authenticated Nova users can call
     * them unless you deliberately change the middleware stack.
     */
    'route_prefix' => 'nova-vendor/unifilemanager/nova-file-manager',

    'middleware' => ['nova'],

    /*
     * Storage areas are resolved on the server. The browser may request an area
     * key, but it can never provide disk, root, or visibility values.
     */
    'storage_areas' => [
        'private' => [
            'enabled' => true,
            'disk' => env('NOVA_FILE_MANAGER_DISK', 'local'),
            'root' => env('NOVA_FILE_MANAGER_ROOT', 'nova-file-manager/private'),
            'visibility' => 'private',
        ],
        'public' => [
            'enabled' => false,
            'disk' => env('NOVA_FILE_MANAGER_PUBLIC_DISK', 'public'),
            'root' => env('NOVA_FILE_MANAGER_PUBLIC_ROOT', 'nova-file-manager/public'),
            'visibility' => 'public',
        ],
    ],

    'storage_area_resolver' => ConfigStorageAreaResolver::class,

    'default_area' => 'private',

    'max_upload_size' => 10 * 1024, // KiB

    'max_upload_files' => 10,

    'max_directory_depth' => 7,

    'allowed_mimes' => [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf',
        'text/plain', 'text/csv', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ],

    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx'],

    'preview_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf', 'text/plain'],

    'preview_rate_limit' => 60,

    'thumbnails' => [
        'enabled' => true,
        'directory' => '.thumbnails',
        'max_dimension' => 360,
        'max_source_pixels' => 8_000_000,
    ],

    'authorizer' => DefaultFileManagerAuthorizer::class,
];
