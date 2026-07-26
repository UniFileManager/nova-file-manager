<?php

declare(strict_types=1);

namespace UniFileManager\NovaFileManager\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;

final class FileManagerTool extends Tool
{
    public function boot(): void
    {
        $script = __DIR__.'/../../dist/js/tool.js';
        $style = __DIR__.'/../../dist/css/tool.css';

        if (file_exists($script)) {
            Nova::script('unifilemanager-nova-file-manager', $script);
        }

        if (file_exists($style)) {
            Nova::style('unifilemanager-nova-file-manager', $style);
        }
    }

    public function menu(Request $request): MenuSection
    {
        return MenuSection::make('File Manager')
            ->path('/file-manager')
            ->icon('folder');
    }
}
