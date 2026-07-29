<?php

declare(strict_types=1);

namespace UniFileManager\NovaFileManager\Tests\Support;

use UniFileManager\Core\Contracts\FileManagerAuthorizer;

final class AllowAllFileManagerAuthorizer implements FileManagerAuthorizer
{
    public function can(mixed $user, string $operation, string $path = ''): bool
    {
        return true;
    }
}
