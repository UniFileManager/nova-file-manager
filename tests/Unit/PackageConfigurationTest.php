<?php

declare(strict_types=1);

it('loads the Nova File Manager configuration', function (): void {
    expect(config('nova-file-manager.default_area'))->toBe('private')
        ->and(config('nova-file-manager.storage_areas.private.enabled'))->toBeTrue();
});
