<?php

use App\Exceptions\BizException;
use App\Models\prd_wo_serial_rm;
use App\Support\ScrapService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * A leftover bar shorter than the smallest cut any active BOM needs cannot feed
 * a product, so the system proposes scrapping it. The proposal is not binding:
 * a supervisor may rule either way, and the reason has to survive.
 */
function scrapFixture(float $lengthRem): array
{
    Cache::flush();

    $cat = DB::table('m_i_category')->value('id');
    $mat = DB::table('m_item')->insertGetId([
        'code' => 'RM-SCR-'.substr(uniqid(), -8), 'part_name' => 'Pipa uji scrap', 'type' => 'RM',
        'category_id' => $cat, 'min_stock' => 0, 'max_stock' => 0, 'active' => 1,
    ]);
    $fg = DB::table('m_item')->insertGetId([
        'code' => 'FG-SCR-'.substr(uniqid(), -8), 'part_name' => 'FG uji scrap', 'type' => 'FG',
        'category_id' => $cat, 'min_stock' => 0, 'max_stock' => 0, 'active' => 1,
    ]);

    // Active BOM whose shortest cut is 1000 mm.
    $bom = DB::table('m_bom')->insertGetId(['item_id' => $fg, 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('m_bom_det_rm')->insert([
        'id_prim' => $bom, 'mat_id' => $mat, 'length_cut' => 1000, 'length_use' => 1000,
        'priority' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $wo = DB::table('prd_wo_main')->insertGetId([
        'code' => 'WO-SCR-'.substr(uniqid(), -6), 'fg_id' => $fg, 'qty' => 1,
        'date' => now()->toDateString(), 'user_id' => admin()->id, 'status' => 2,
        'customer_id' => DB::table('m_contacts')->value('id'), 'so_id' => '-',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $det = DB::table('prd_wo_detail_rm')->insertGetId([
        'main_id' => $wo, 'rm_id' => $mat, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $serial = DB::table('prd_wo_serial_rm')->insertGetId([
        'detail_id' => $det, 'serial_id' => 'SN-SCR-'.substr(uniqid(), -8),
        'length_asal' => 6000, 'length_book' => 6000, 'length_rem' => $lengthRem,
        'qty_per_serial' => 1, 'qty_serial' => 1, 'scrap' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return ['mat' => $mat, 'wo' => $wo, 'serial' => $serial];
}

it('reads the shortest cut an active BOM needs from that material', function () {
    $f = scrapFixture(500);

    expect((new ScrapService)->minBomLength($f['mat']))->toBe(1000.0);
});

it('flags a leftover shorter than the shortest cut', function () {
    $f = scrapFixture(400);
    $serial = prd_wo_serial_rm::with('detail')->find($f['serial']);

    expect((new ScrapService)->evaluate($serial))->toBeTrue()
        ->and((int) $serial->fresh()->scrap)->toBe(1);
});

it('leaves a leftover that still fits a product alone', function () {
    $f = scrapFixture(2500);
    $serial = prd_wo_serial_rm::with('detail')->find($f['serial']);

    expect((new ScrapService)->evaluate($serial))->toBeFalse()
        ->and((int) $serial->fresh()->scrap)->toBe(0);
});

it('lets a supervisor overrule the flag and keeps the reason', function () {
    $f = scrapFixture(400);
    $serial = prd_wo_serial_rm::with('detail')->find($f['serial']);
    (new ScrapService)->evaluate($serial);

    (new ScrapService)->decide($serial->fresh()->load('detail'), 'USABLE', 'Dipakai untuk sampel uji tarik', admin());

    $row = DB::table('prd_scrap_decisions')->where('wo_serial_rm_id', $f['serial'])->first();

    expect((int) $serial->fresh()->scrap)->toBe(0)
        ->and($row->decision)->toBe('USABLE')
        ->and($row->reason)->toBe('Dipakai untuk sampel uji tarik')
        ->and((bool) $row->auto_flag)->toBeTrue();     // system had said scrap
});

it('will not record a decision without a reason', function () {
    $f = scrapFixture(400);
    $serial = prd_wo_serial_rm::with('detail')->find($f['serial']);

    expect(fn () => (new ScrapService)->decide($serial, 'SCRAP', '   ', admin()))
        ->toThrow(BizException::class);
});

it('exposes candidates and the decision endpoint', function () {
    $f = scrapFixture(400);
    Sanctum::actingAs(admin());

    $codes = collect($this->getJson('/api/v1/scrap-rm')->assertOk()->json('data'))->pluck('id');
    expect($codes)->toContain($f['serial']);

    $this->postJson("/api/v1/scrap-rm/{$f['serial']}/decide", ['decision' => 'SCRAP', 'reason' => 'Terlalu pendek'])
        ->assertOk();

    $this->getJson("/api/v1/scrap-rm/{$f['serial']}/history")
        ->assertOk()
        ->assertJsonPath('data.0.decision', 'SCRAP');
});
