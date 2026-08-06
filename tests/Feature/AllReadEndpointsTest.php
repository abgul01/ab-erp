<?php

use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

/**
 * Sapuan seluruh endpoint baca.
 *
 * Daftar endpoint yang ditulis tangan hanya menjaga layar yang sempat teringat.
 * Yang paling sering rusak justru layar yang jarang dibuka: kolom salah ketik,
 * relasi yang tidak ada, atau query yang hanya jalan saat tabelnya kosong.
 * Gejalanya di layar cuma "gagal memuat", dan tidak ada yang melaporkannya.
 *
 * Sapuan ini memanggil setiap GET tanpa parameter wajib sebagai admin, dan
 * menuntut satu hal saja: server tidak boleh meledak. 422 karena query string
 * kurang lengkap tetap dianggap sehat — itu jawaban, bukan kerusakan.
 */
it('menjawab tanpa meledak di seluruh endpoint baca', function () {
    Sanctum::actingAs(admin());

    $failed = [];
    $checked = 0;

    foreach (Route::getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true)) {
            continue;
        }

        $uri = $route->uri();

        // Hanya API v1 yang berpenjaga sanctum; portal vendor punya autentikasi
        // sendiri dan tidak bisa dipanggil dengan akun ini.
        if (! str_starts_with($uri, 'api/v1/') || str_contains($uri, 'api/v1/vendor/')) {
            continue;
        }

        // Rute berparameter perlu id yang nyata; itu urusan tes masing-masing.
        if (str_contains($uri, '{')) {
            continue;
        }

        $checked++;
        $status = $this->get('/'.$uri)->getStatusCode();

        if ($status >= 500) {
            $failed[] = "{$uri} → {$status}";
        }
    }

    expect($checked)->toBeGreaterThan(80)
        ->and($failed)->toBe([], 'Endpoint yang meledak: '.implode('; ', $failed));
});
