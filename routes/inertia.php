<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Nova\Http\Requests\NovaRequest;

/*
|--------------------------------------------------------------------------
| Tool Routes
|--------------------------------------------------------------------------
|
| This route renders the Nova tool shell. The Vue component is registered in
| resources/js/tool.js as "UniFileManager".
|
*/

Route::get('/', function (NovaRequest $request) {
    return inertia('UniFileManager');
});
