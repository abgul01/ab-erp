<?php

use Illuminate\Support\Facades\Route;

/**
 * The service worker is built into /build but has to be served from the root:
 * a worker may only control pages at or below its own path, and the MES pages
 * live at /mes-cutting and friends. Same for the manifest, which browsers look
 * for next to the pages it describes.
 */
Route::get('/sw.js', fn () => response(
    file_get_contents(public_path('build/sw.js')), 200,
    ['Content-Type' => 'application/javascript', 'Service-Worker-Allowed' => '/', 'Cache-Control' => 'no-cache']
));

Route::get('/manifest.webmanifest', fn () => response(
    file_get_contents(public_path('build/manifest.webmanifest')), 200,
    ['Content-Type' => 'application/manifest+json']
));

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
    ->where('any', '^(?!api|build|storage|up|sw\.js|manifest\.webmanifest).*$');
