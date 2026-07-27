<?php

declare(strict_types=1);

namespace UniFileManager\NovaFileManager\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Tool;

final class FileManagerTool extends Tool
{
    public function menu(Request $request): MenuSection
    {
        return MenuSection::make('File Manager')
            ->path('/file-manager')
            ->canSee(fn (Request $request): bool => (bool) $request->user()?->can('manageFileManager'))
            ->icon('folder');
    }
}
