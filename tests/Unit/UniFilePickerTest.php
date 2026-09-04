<?php

declare(strict_types=1);

use UniFileManager\NovaFileManager\Nova\Fields\UniFilePicker;

it('can target a custom storage area', function (): void {
    $field = UniFilePicker::make('Attachment')->storageArea('documents');

    expect($field->meta['storageArea'])->toBe('documents');
});

it('can still target the built-in storage areas', function (): void {
    expect(UniFilePicker::make('Avatar')->publicMedia()->meta['storageArea'])->toBe('public')
        ->and(UniFilePicker::make('Invoice')->privateMedia()->meta['storageArea'])->toBe('private');
});
