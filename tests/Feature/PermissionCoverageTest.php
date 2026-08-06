<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

/**
 * Permission keys are menu links: CheckPermission asks whether the user has a
 * right on the menu whose link equals the key. A key that matches no menu can
 * therefore never be granted to anyone, and the screen behind it is dead for
 * everybody except the super admin — who, being exempt, never notices.
 *
 * That is exactly what happened to General Store: every route guarded
 * `general-store`, while the menus were `general-store-requests` and
 * `general-store-issues`.
 */
/**
 * Setiap menu harus punya halaman.
 *
 * Seeder menu hanya menambah dan memperbarui, jadi modul yang dibuang dari kode
 * meninggalkan baris menunya di database — persis yang terjadi pada General
 * Store setelah digantikan WHS Tools. Pengguna menekannya dan sampai di
 * "Halaman tidak ditemukan"; tidak ada yang error, tidak ada yang tahu.
 */
it('memberi setiap menu sebuah halaman', function () {
    $links = DB::table('menus')->where('link', '<>', '0')->pluck('name', 'link');

    // Halaman React: rute eksplisit di App.jsx, plus layar CRUD generik yang
    // dibangkitkan dari resources.js.
    preg_match_all('/<Route path="([^"*]+)"/', file_get_contents(base_path('resources/js/app/App.jsx')), $routes);
    preg_match_all(
        '/^\s{4}[\'"]?([a-z0-9-]+)[\'"]?:\s*\{/m',
        file_get_contents(base_path('resources/js/features/crud/resources.js')),
        $crud
    );
    $pages = array_merge($routes[1], $crud[1]);

    $dead = $links->reject(fn ($name, $link) => in_array($link, $pages, true));

    expect($dead->all())->toBe(
        [],
        'Menu tanpa halaman: '.$dead->keys()->implode(', ')
    );
});

it('guards every route with a key that is a real menu', function () {
    $menuLinks = DB::table('menus')->pluck('link')->filter()->all();

    $orphans = [];
    foreach (Route::getRoutes() as $route) {
        foreach ($route->gatherMiddleware() as $mw) {
            if (! is_string($mw) || ! str_contains($mw, 'CheckPermission:')) {
                continue;
            }
            [, $args] = explode(':', $mw, 2);
            $key = explode(',', $args)[0];

            if (! in_array($key, $menuLinks, true)) {
                $orphans[$key][] = $route->uri();
            }
        }
    }

    expect($orphans)->toBe(
        [],
        'Kunci izin tanpa menu (tak seorang pun bisa diberi hak ini): '
        .json_encode(array_map(fn ($u) => array_slice($u, 0, 3), $orphans))
    );
});

it('lets a non-super-admin actually use the WHS warehouse', function () {
    $operator = User::where('username', 'operator')->first();

    // Bug yang ditutup tes ini tidak terlihat oleh akun admin, jadi tesnya
    // harus memakai orang yang tidak dikecualikan.
    expect($operator)->not->toBeNull()
        ->and($operator->isSuperAdmin())->toBeFalse();

    Sanctum::actingAs($operator);

    $this->getJson('/api/v1/whs-items')->assertOk();
    $this->getJson('/api/v1/whs-outgoing')->assertOk();
    $this->getJson('/api/v1/whs-stock')->assertOk();
});

it('gives every newly added screen a menu and a working endpoint', function () {
    $operator = User::where('username', 'operator')->first();
    Sanctum::actingAs($operator);

    foreach ([
        'ecn', 'inventory-valuation', 'packing-lists', 'shipping-orders',
        'whs-items', 'whs-po', 'whs-incoming', 'whs-outgoing', 'whs-returns', 'whs-stock',
    ] as $link) {
        expect(DB::table('menus')->where('link', $link)->exists())->toBeTrue("menu {$link} hilang");
    }

    $this->getJson('/api/v1/ecn')->assertOk();
    $this->getJson('/api/v1/inventory-valuation')->assertOk();
    $this->getJson('/api/v1/packing-lists')->assertOk();
    $this->getJson('/api/v1/shipping-orders')->assertOk();
});
