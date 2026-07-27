<?php

use Illuminate\Support\Facades\Route;

/**
 * Serve the React SPA for every non-API, non-asset route.
 *
 * The shell is never cached: it carries the hashed bundle name, so a cached
 * copy would keep pointing browsers at a stale build after every deploy.
 * The hashed assets under /build are themselves immutable, so this costs
 * nothing but guarantees users run the current frontend.
 */
Route::get('/{any?}', fn () => response(view('app'))
    ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
    ->header('Pragma', 'no-cache'))
    ->where('any', '^(?!api|build|storage|up).*$');
