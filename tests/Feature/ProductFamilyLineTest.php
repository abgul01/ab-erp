<?php

use App\Support\CrpService;
use App\Support\InventoryValuationService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Product Family & Production Line.
 *
 * Keduanya hanya berguna kalau ada yang membacanya. Yang diuji di sini bukan
 * CRUD-nya, melainkan dua pertanyaan yang sebelumnya tidak punya jawaban:
 * "keluarga produk ini untung atau tidak" dan "lintasan mana yang penuh".
 */
it('mengelompokkan margin per keluarga produk', function () {
    $period = DB::table('sls_inv_main')->whereNotIn('status', ['CANCELLED', 'DRAFT'])
        ->selectRaw("DATE_FORMAT(date, '%Y%m') as p")->value('p');

    $margin = app(InventoryValuationService::class)->margin($period);

    expect($margin)->toHaveKey('families')
        ->and($margin['families'])->not->toBeEmpty();

    // Jumlah per keluarga harus sama dengan total keseluruhan — kalau tidak,
    // ada baris yang hilang dari pengelompokan.
    $sum = round(collect($margin['families'])->sum('revenue'), 2);
    expect($sum)->toBe($margin['total']['revenue']);
});

it('mengumpulkan part tanpa keluarga apa adanya, bukan menyembunyikannya', function () {
    $period = DB::table('sls_inv_main')->whereNotIn('status', ['CANCELLED', 'DRAFT'])
        ->selectRaw("DATE_FORMAT(date, '%Y%m') as p")->value('p');

    // Satu part sengaja dilepas dari keluarganya.
    $itemId = DB::table('sls_inv_detail')->value('item_id');
    DB::table('m_item')->where('id', $itemId)->update(['family_id' => null]);

    $margin = app(InventoryValuationService::class)->margin($period);
    $names = collect($margin['families'])->pluck('family');

    expect($names)->toContain('(tanpa keluarga)')
        // Totalnya tetap cocok — itulah gunanya tidak disembunyikan.
        ->and(round(collect($margin['families'])->sum('revenue'), 2))->toBe($margin['total']['revenue']);
});

it('menghitung beban kapasitas per lintasan produksi', function () {
    $period = DB::table('prd_mps')->where('status', 'APPROVED')
        ->selectRaw("DATE_FORMAT(plan_date, '%Y%m') as p")->value('p');

    if (! $period) {
        $this->markTestSkipped('Tidak ada MPS approved pada data demo.');
    }

    $result = app(CrpService::class)->run($period);

    expect($result)->toHaveKey('lines');

    if (empty($result['loads'])) {
        return;
    }

    // Jam beban per lintasan harus sama dengan jumlah beban mesin di dalamnya.
    $perMachine = round(collect($result['loads'])->sum('load_hours'), 2);
    $perLine = round(collect($result['lines'])->sum('load_hours'), 2);

    expect($perLine)->toBe($perMachine);
});

it('menandai lintasan yang bebannya melebihi kapasitas', function () {
    $period = DB::table('prd_mps')->where('status', 'APPROVED')
        ->selectRaw("DATE_FORMAT(plan_date, '%Y%m') as p")->value('p');

    if (! $period) {
        $this->markTestSkipped('Tidak ada MPS approved pada data demo.');
    }

    $lines = app(CrpService::class)->run($period)['lines'];

    foreach ($lines as $l) {
        // Status diturunkan dari persentasenya, bukan disimpan terpisah.
        $expected = $l['load_pct'] > 100 ? 'OVERLOAD' : ($l['load_pct'] > 80 ? 'WARNING' : 'OK');
        expect($l['status'])->toBe($expected);
    }
});

it('menyediakan master keluarga dan lintasan lewat HTTP', function () {
    Sanctum::actingAs(admin());

    $this->getJson('/api/v1/product-families')->assertOk();
    $this->getJson('/api/v1/production-lines')->assertOk();

    $fam = $this->postJson('/api/v1/product-families', [
        'code' => 'FAM-'.substr(uniqid(), -5),
        'name' => 'Keluarga uji',
    ])->assertCreated()->json('data');

    $line = $this->postJson('/api/v1/production-lines', [
        'code' => 'LN-'.substr(uniqid(), -5),
        'name' => 'Lintasan uji',
        'daily_hours' => 8,
    ])->assertCreated()->json('data');

    expect((float) $line['daily_hours'])->toBe(8.0)
        ->and($fam['active'])->toBeTrue();
});

it('menerima keluarga produk pada master item', function () {
    Sanctum::actingAs(admin());

    $famId = DB::table('m_product_family')->value('id');

    $item = $this->postJson('/api/v1/items', [
        'code' => 'IT-FAM-'.substr(uniqid(), -6),
        'part_name' => 'Item uji keluarga',
        'group' => 'FG',
        'family_id' => $famId,
    ])->assertCreated()->json('data');

    expect((int) $item['family_id'])->toBe((int) $famId);
});
