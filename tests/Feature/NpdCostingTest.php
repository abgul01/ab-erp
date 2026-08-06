<?php

use App\Exceptions\BizException;
use App\Models\npd_cost_main;
use App\Models\npd_project;
use App\Support\NpdCostingService;
use App\Support\NpdService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * NPD Tahap 2 — preliminary BOM, estimasi biaya, dan quotation.
 *
 * Nilai modul ini bergantung pada satu hal: angkanya harus datang dari sumber
 * yang sama dengan yang dipakai pengadaan dan costing produksi. Estimasi yang
 * tarifnya diketik sendiri akan selalu berbeda dengan COGM yang dihitung setelah
 * barangnya jadi, dan selisihnya tidak akan pernah bisa dijelaskan.
 */
function npdProject(): npd_project
{
    $rfq = rfqFor();
    feasFor($rfq);

    return app(NpdService::class)->createFromRfq($rfq, ['name' => 'Proyek costing'], admin()->id);
}

/** Preliminary BOM satu baris atas material yang sudah ada di master. */
function npdBom(npd_project $project, int $itemId, float $qty = 2): int
{
    $bomId = DB::table('npd_bom_main')->insertGetId([
        'main_id' => $project->id, 'version' => 'v1', 'status' => 'DRAFT',
        'user_id' => admin()->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('npd_bom_det')->insert([
        'main_id' => $bomId, 'item_id' => $itemId, 'role' => 'RM',
        'qty' => $qty, 'unit_cost' => 0, 'cost_source' => 'MANUAL',
    ]);

    return $bomId;
}

function npdCost(npd_project $project, int $bomId, array $attrs = []): npd_cost_main
{
    return npd_cost_main::create(array_merge([
        'main_id' => $project->id,
        'bom_id' => $bomId,
        'version' => 'v1',
        'period' => now()->format('Ym'),
        'margin_pct' => 20,
        'status' => 'DRAFT',
        'user_id' => admin()->id,
    ], $attrs));
}

it('mencatat golongan part yang didaftarkan, bukan memaksanya jadi barang jadi', function () {
    $project = npdProject();

    // Proyek modifikasi sering melahirkan komponen, bukan produk baru — dan
    // golongan itulah yang menentukan perlakuan seluruh sistem terhadapnya.
    $pm = app(NpdCostingService::class)->registerPart($project, [
        'code' => 'PM-NPD-'.substr(uniqid(), -6),
        'type' => 'PM',
        'category_id' => DB::table('m_i_category')->value('id'),
    ], admin()->id);

    expect($pm->type)->toBe('PM');

    $other = npdProject();
    $fg = app(NpdCostingService::class)->registerPart($other, [
        'code' => 'FG-NPD-'.substr(uniqid(), -6),
        'type' => 'FG',
        'category_id' => DB::table('m_i_category')->value('id'),
    ], admin()->id);

    expect($fg->type)->toBe('FG');
});

it('menurunkan peran baris BOM dari golongan barangnya, bukan dari pilihan di layar', function () {
    Sanctum::actingAs(admin());
    $project = npdProject();
    $f = mrpFixture(['plan' => 10]);            // rm bertipe RM, fg bertipe FG

    $bom = $this->postJson("/api/v1/npd-costing/project/{$project->id}/bom", [
        'version' => 'v1',
        'lines' => [
            // Sengaja disalahtandai sebagai PM; server harus mengoreksinya.
            ['item_id' => $f['rm'], 'role' => 'PM', 'qty' => 2],
            // Barang jadi yang dipakai sebagai sub-rakitan: dikonsumsi per buah.
            ['item_id' => $f['fg'], 'role' => 'RM', 'qty' => 1],
            // Part yang belum terdaftar belum punya golongan — pilihan pengguna dipakai.
            ['new_item_name' => 'Bracket baru', 'role' => 'PM', 'qty' => 4],
        ],
    ])->assertCreated()->json('data');

    $roles = collect($bom['detail'])->pluck('role')->all();

    expect($roles)->toBe(['RM', 'PM', 'PM']);
});

it('mendaftarkan part proyek sebagai item non-aktif', function () {
    $project = npdProject();
    $code = 'FG-NPD-'.substr(uniqid(), -6);

    $item = app(NpdCostingService::class)->registerPart($project, [
        'code' => $code,
        'type' => 'FG',
        'category_id' => DB::table('m_i_category')->value('id'),
    ], admin()->id);

    // Sudah bisa dipakai Work Order trial, tapi belum boleh masuk perencanaan
    // produksi atau dijual seolah sudah siap.
    expect((int) $item->active)->toBe(0)
        ->and($item->status)->toBe('DRAFT')
        ->and($item->type)->toBe('FG')
        ->and($project->fresh()->item_id)->toBe($item->id);

    // Sekali saja: part kedua untuk proyek yang sama adalah tanda salah layar.
    expect(fn () => app(NpdCostingService::class)->registerPart($project->fresh(), [
        'code' => $code.'-X', 'type' => 'FG', 'category_id' => DB::table('m_i_category')->value('id'),
    ], admin()->id))->toThrow(BizException::class);
});

it('mengambil harga material dari kesepakatan supplier, bukan dari ketikan', function () {
    $project = npdProject();
    $f = mrpFixture(['plan' => 10]);
    supplierFor($f['rm'], ['price' => 777000, 'priority' => 1]);

    $bomId = npdBom($project, $f['rm'], 3);
    $cost = app(NpdCostingService::class)->recalculate(npdCost($project, $bomId), admin()->id);

    $line = $cost->detail->firstWhere('cost_type', 'MATERIAL');

    expect((float) $line->rate)->toBe(777000.0)
        ->and((float) $line->amount)->toBe(2331000.0)
        ->and($cost->material_cost)->toBe(2331000.0)
        // Harga yang tadinya nol ikut terisi di BOM, lengkap dengan asal-usulnya.
        ->and(DB::table('npd_bom_det')->where('main_id', $bomId)->value('cost_source'))->toBe('SUPPLIER');
});

it('menghitung harga penawaran dari total biaya dan margin', function () {
    $project = npdProject();
    $f = mrpFixture(['plan' => 10]);
    supplierFor($f['rm'], ['price' => 100000]);

    $bomId = npdBom($project, $f['rm'], 2);           // material 200.000
    $cost = npdCost($project, $bomId, ['margin_pct' => 25, 'overhead' => 50000]);
    $cost = app(NpdCostingService::class)->recalculate($cost, admin()->id);

    // 200.000 material + 50.000 overhead = 250.000; margin 25% → 312.500
    expect($cost->total_cost)->toBe(250000.0)
        ->and($cost->quoted_price)->toBe(312500.0);
});

it('mempertahankan biaya tooling saat estimasi dihitung ulang', function () {
    $project = npdProject();
    $f = mrpFixture(['plan' => 10]);
    supplierFor($f['rm'], ['price' => 100000]);
    $bomId = npdBom($project, $f['rm'], 1);
    $cost = npdCost($project, $bomId);

    $cost->detail()->create([
        'cost_type' => 'TOOLING', 'descrip' => 'Dies bending', 'qty' => 1,
        'rate' => 15000000, 'amount' => 15000000,
    ]);

    $cost = app(NpdCostingService::class)->recalculate($cost, admin()->id);

    // Tooling adalah angka yang dimasukkan orang, bukan hasil hitungan — ia
    // tidak boleh hilang setiap kali tombol hitung ulang ditekan.
    expect($cost->tooling_cost)->toBe(15000000.0)
        ->and($cost->detail->where('cost_type', 'TOOLING')->count())->toBe(1)
        ->and($cost->total_cost)->toBe(15100000.0);
});

it('memberi biaya proses nol saat part atau cycle time belum ada — bukan angka karangan', function () {
    $project = npdProject();
    $f = mrpFixture(['plan' => 10]);
    supplierFor($f['rm'], ['price' => 50000]);

    $cost = app(NpdCostingService::class)->recalculate(npdCost($project, npdBom($project, $f['rm'])), admin()->id);

    expect($cost->process_cost)->toBe(0.0)
        ->and($cost->detail->whereIn('cost_type', ['LABOR', 'FOH'])->count())->toBe(0);
});

it('menghitung upah dan overhead dari cycle time routing dan tarif periode', function () {
    $project = npdProject();
    $f = mrpFixture(['plan' => 10]);
    supplierFor($f['rm'], ['price' => 10000]);

    // Part terdaftar + cycle time + tarif periode → biaya proses ada isinya.
    $item = app(NpdCostingService::class)->registerPart($project, [
        'code' => 'FG-RT-'.substr(uniqid(), -6),
        'category_id' => DB::table('m_i_category')->value('id'),
    ], admin()->id);

    $proc = DB::table('m_process')->value('id');
    DB::table('m_route_time')->insert([
        'item_id' => $item->id, 'proc_id' => $proc, 'machine_id' => DB::table('m_machine')->value('id'),
        'cycle_sec' => 36, 'setup_min' => 10, 'priority' => 1, 'active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $period = now()->format('Ym');
    DB::table('cst_rate')->where('period', $period)->where('process_id', $proc)->delete();
    DB::table('cst_rate')->insert([
        ['period' => $period, 'rate_type' => 'LABOR', 'process_id' => $proc, 'rate_per_hour' => 50000],
        ['period' => $period, 'rate_type' => 'FOH', 'process_id' => $proc, 'rate_per_hour' => 30000],
    ]);

    $cost = app(NpdCostingService::class)->recalculate(
        npdCost($project->fresh(), npdBom($project, $f['rm'], 1), ['period' => $period]),
        admin()->id
    );

    // 36 detik = 0,01 jam → upah 500, overhead 300.
    expect($cost->process_cost)->toBe(800.0)
        ->and((float) $cost->detail->firstWhere('cost_type', 'LABOR')->amount)->toBe(500.0)
        ->and((float) $cost->detail->firstWhere('cost_type', 'FOH')->amount)->toBe(300.0);
});

it('menolak quotation yang belum disetujui dijadikan pricelist', function () {
    $project = npdProject();
    $f = mrpFixture(['plan' => 10]);
    supplierFor($f['rm'], ['price' => 100000]);
    $cost = app(NpdCostingService::class)->recalculate(npdCost($project, npdBom($project, $f['rm'])), admin()->id);

    expect(fn () => app(NpdCostingService::class)->toPricelist($cost, [], admin()->id))
        ->toThrow(BizException::class);
});

it('mencatat quotation yang disetujui sebagai pricelist DRAFT, sekali saja', function () {
    Sanctum::actingAs(admin());
    $project = npdProject();
    $f = mrpFixture(['plan' => 10]);
    supplierFor($f['rm'], ['price' => 100000]);

    app(NpdCostingService::class)->registerPart($project, [
        'code' => 'FG-PL-'.substr(uniqid(), -6),
        'category_id' => DB::table('m_i_category')->value('id'),
    ], admin()->id);

    $cost = app(NpdCostingService::class)->recalculate(npdCost($project->fresh(), npdBom($project, $f['rm'])), admin()->id);

    // Dua level persetujuan, sama seperti gate.
    $this->postJson("/api/v1/npd-costing/cost/{$cost->id}/submit")->assertOk();
    $this->postJson("/api/v1/npd-costing/cost/{$cost->id}/approve")->assertOk();
    expect($cost->fresh()->status)->toBe('SUBMITTED');

    $this->postJson("/api/v1/npd-costing/cost/{$cost->id}/approve")->assertOk();
    expect($cost->fresh()->status)->toBe('APPROVED');

    $this->postJson("/api/v1/npd-costing/cost/{$cost->id}/to-pricelist", ['min_qty' => 100])
        ->assertCreated();

    $det = DB::table('m_pricelist_det')->where('id', $cost->fresh()->pricelist_det_id)->first();
    $main = DB::table('m_pricelist_main')->where('id', $det->main_id)->first();

    expect((float) $det->price)->toBe($cost->fresh()->quoted_price)
        // Harga baru tetap melewati approval pricelist yang sudah ada.
        ->and($main->status)->toBe('DRAFT')
        ->and((int) $main->cus_id)->toBe((int) $project->cus_id);

    // Dan tidak boleh dicatat dua kali.
    $this->postJson("/api/v1/npd-costing/cost/{$cost->id}/to-pricelist")->assertStatus(422);
});

it('menolak pricelist untuk proyek yang partnya belum terdaftar', function () {
    $project = npdProject();
    $f = mrpFixture(['plan' => 10]);
    supplierFor($f['rm'], ['price' => 100000]);
    $cost = app(NpdCostingService::class)->recalculate(npdCost($project, npdBom($project, $f['rm'])), admin()->id);
    $cost->update(['status' => 'APPROVED']);

    expect(fn () => app(NpdCostingService::class)->toPricelist($cost, [], admin()->id))
        ->toThrow(BizException::class);
});

it('mengunci estimasi yang sudah diajukan', function () {
    Sanctum::actingAs(admin());
    $project = npdProject();
    $f = mrpFixture(['plan' => 10]);
    supplierFor($f['rm'], ['price' => 100000]);
    $cost = app(NpdCostingService::class)->recalculate(npdCost($project, npdBom($project, $f['rm'])), admin()->id);

    $this->postJson("/api/v1/npd-costing/cost/{$cost->id}/submit")->assertOk();

    // 423: yang sedang dinilai tidak boleh berubah di tangan penilainya.
    $this->putJson("/api/v1/npd-costing/cost/{$cost->id}", [
        'period' => now()->format('Ym'), 'margin_pct' => 90,
    ])->assertStatus(423);
});

it('menolak baris BOM yang tidak menunjuk item maupun part baru', function () {
    Sanctum::actingAs(admin());
    $project = npdProject();

    $this->postJson("/api/v1/npd-costing/project/{$project->id}/bom", [
        'version' => 'v1',
        'lines' => [['qty' => 1, 'role' => 'RM']],
    ])->assertStatus(422);
});

it('menyajikan BOM dan estimasi satu proyek lewat HTTP', function () {
    Sanctum::actingAs(admin());
    $project = npdProject();

    $this->getJson("/api/v1/npd-costing/project/{$project->id}")->assertOk()
        ->assertJsonStructure(['data' => ['project', 'boms', 'costs']]);
});
