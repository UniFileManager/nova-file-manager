<?php

declare(strict_types=1);

namespace UniFileManager\NovaFileManager\Nova\Fields;

use Laravel\Nova\Fields\Field;
use UniFileManager\Core\Support\MimeTypeMatcher;

final class UniFilePicker extends Field
{
    public const DEFAULT_ALLOWED_MIME_TYPES = MimeTypeMatcher::DEFAULT_FILE_PICKER_MIME_TYPES;

    public $component = 'uni-file-picker';

    public function __construct($name, mixed $attribute = null, ?callable $resolveCallback = null)
    {
        parent::__construct($name, $attribute, $resolveCallback);

        $this->withMeta([
            'allowedMimeTypes' => self::DEFAULT_ALLOWED_MIME_TYPES,
            'clearable' => true,
        ]);
    }

    public function multiple(bool $condition = true): static
    {
        return $this->withMeta(['multiple' => $condition]);
    }

    public function clearable(bool $condition = true): static
    {
        return $this->withMeta(['clearable' => $condition]);
    }

    public function allowDuplicateSelection(bool $condition = true): static
    {
        return $this->withMeta(['allowDuplicateSelection' => $condition]);
    }

    public function maxFiles(?int $maximum): static
    {
        return $this->withMeta(['maxFiles' => $maximum]);
    }

    public function directory(?string $directory): static
    {
        return $this->withMeta(['directory' => $directory]);
    }

    public function storageArea(?string $area): static
    {
        return $this->withMeta(['storageArea' => $area]);
    }

    public function publicMedia(bool $condition = true): static
    {
        if (! $condition) {
            return $this;
        }

        return $this->withMeta(['storageArea' => 'public']);
    }

    public function privateMedia(bool $condition = true): static
    {
        if (! $condition) {
            return $this;
        }

        return $this->withMeta(['storageArea' => 'private']);
    }

    /** @param list<string> $mimeTypes */
    public function allowedMimeTypes(array $mimeTypes): static
    {
        return $this->withMeta(['allowedMimeTypes' => array_values($mimeTypes)]);
    }

    public function imageCardView(bool $condition = true): static
    {
        return $this->withMeta(['imageCardView' => $condition]);
    }

    public function uploadHeading(?string $heading): static
    {
        return $this->withMeta(['uploadHeading' => $heading]);
    }

    public function uploadDescription(?string $description): static
    {
        return $this->withMeta(['uploadDescription' => $description]);
    }
}
