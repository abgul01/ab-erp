<?php

use Illuminate\Support\Facades\Route;

// Serve the React SPA for every non-API, non-asset route.
Route::get('/{any?}', fn () => view('app'))
    ->where('any', '^(?!api|build|storage|up).*$');
