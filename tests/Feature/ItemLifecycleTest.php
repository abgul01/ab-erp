<?php

use App\Support\FgStockService;
use App\Support\ItemLifecycle;
use App\Support\MrpService;
use App\Support\NpdCostingService;
use App\Support\NpdService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Pemisahan part uji coba dari part produksi massal.
 *
 * Yang dipisahkan adalah statusnya, bukan tabelnya — satu part harus punya satu
 * identitas seumur hidupnya, supaya lot trial dan hasil inspeksinya tetap
 * menunjuk baris yang sama setelah ia naik ke produksi massal. Yang membuat
 * pemisahan itu berarti adalah larangan-larangan di bawah ini; tanpanya,
 * `lifecycle` hanya kolom yang terlihat rapi.
 */
function trialItem(): int
{
    $cat = DB::table('m_i_category')->value('id');

    return DB::table('m_item')->insertGetId([
        'code' => 'FG-TRIAL-'.substr(uniqid(), -6),
        'part_name' => 'Part uji coba',
        'type' => 'FG',
        'lifecycle' => ItemLifecycle::TRIAL,
        'category_id' => $cat,
        'o_d' => 0, 'thick' => 0, 'length' => 0, 'weight' => 0, 'tolerance' => '',
        'min_stock' => 0, 'max_stock' => 0, 'active' => 0, 'status' => 'DRAFT',
    ]);
}

it('menganggap seluruh part lama sebagai produksi massal', function () {
    // Part yang sudah ada di master memang sudah diproduksi — itu sebabnya ia ada.
    expect(DB::table('m_item')->where('lifecycle', ItemLifecycle::MASSPRO)->count())
        ->toBeGreaterThan(0)
        ->and(DB::table('m_item')->whereNull('lifecycle')->count())->toBe(0);
});

it('menolak part uji coba masuk rencana produksi bulanan', function () {
    Sanctum::actingAs(admin());
    $item = trialItem();

    $res = $this->postJson('/api/v1/mpp', [
        'period' => now()->addMonth()->format('Ym'),
        'item_id' => $item,
        'plan_qty' => 500,
    ])->assertStatus(422);

    // Pesannya menyebut kode part-nya, supaya orang tahu harus ke mana.
    expect($res->json('errors.0.message'))->toContain('uji coba');
});

it('menolak part uji coba masuk forecast', function () {
    Sanctum::actingAs(admin());
    $item = trialItem();

    $this->postJson('/api/v1/forecasts', [
        'cus_id' => DB::table('m_contacts')->where('category_id', 3)->value('id'),
        'item_id' => $item,
        'version' => 'FINAL',
        'qty' => 100,
        'period' => now()->addMonth()->format('Ym'),
    ])->assertStatus(422);
});

it('menolak part uji coba dijual lewat sales order', function () {
    Sanctum::actingAs(admin());
    $item = trialItem();
    $cus = DB::table('m_contacts')->where('category_id', 3)->value('id');

    // Sengaja didaftarkan ke pelanggan supaya yang menahan benar-benar
    // lifecycle-nya, bukan penjagaan m_item_customer yang sudah ada.
    DB::table('m_item_customer')->insert([
        'item_id' => $item, 'cus_id' => $cus, 'priority' => 1, 'active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->postJson('/api/v1/sales-orders', [
        'code' => 'SO-UJI-'.substr(uniqid(), -6),
        'date' => now()->toDateString(),
        'cus_id' => $cus,
        'due_date' => now()->addMonth()->toDateString(),
        'lines' => [['item_id' => $item, 'qty' => 10, 'price' => 50000]],
    ])->assertStatus(422);
});

it('menolak Work Order produksi untuk part uji coba', function () {
    Sanctum::actingAs(admin());
    $item = trialItem();

    $this->postJson('/api/v1/work-orders', [
        'date' => now()->toDateString(),
        'customer_id' => DB::table('m_contacts')->where('category_id', 3)->value('id'),
        'fg_id' => $item,
        'qty' => 100,
        'mps_id' => DB::table('prd_mps')->value('id'),
    ])->assertStatus(422);
});

it('tidak menghitung Work Order uji coba sebagai pasokan di MRP', function () {
    $f = mrpFixture(['plan' => 100]);

    // WO uji coba sebesar seluruh rencana. Kalau terhitung, kebutuhan akan
    // terlihat nol dan materialnya tidak akan pernah dibeli.
    DB::table('prd_wo_main')->insert([
        'code' => 'WO-TRIAL-'.substr(uniqid(), -6),
        'wo_kind' => 'NPD_TRIAL',
        'date' => now()->toDateString(),
        'customer_id' => DB::table('m_contacts')->value('id'),
        'so_id' => '-', 'fg_id' => $f['fg'], 'mps_id' => 0,
        'user_id' => admin()->id, 'qty' => 100, 'status' => 2,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $rows = (new MrpService(app(FgStockService::class)))->explode([$f['period']]);
    $fg = mrpRow($rows, $f['fg']);

    expect($fg['open_wo'])->toBe(0)
        ->and($fg['net_req'])->toBe(100);
});

it('menghitung Work Order produksi seperti biasa', function () {
    $f = mrpFixture(['plan' => 100]);

    DB::table('prd_wo_main')->insert([
        'code' => 'WO-PROD-'.substr(uniqid(), -6),
        'wo_kind' => 'PROD',
        'date' => now()->toDateString(),
        'customer_id' => DB::table('m_contacts')->value('id'),
        'so_id' => '-', 'fg_id' => $f['fg'], 'mps_id' => 0,
        'user_id' => admin()->id, 'qty' => 40, 'status' => 2,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $rows = (new MrpService(app(FgStockService::class)))->explode([$f['period']]);
    $fg = mrpRow($rows, $f['fg']);

    // Pembandingnya: yang PROD memang mengurangi kebutuhan.
    expect($fg['open_wo'])->toBe(40)
        ->and($fg['net_req'])->toBe(60);
});

it('menyembunyikan part uji coba dari pemilih item layar operasional', function () {
    Sanctum::actingAs(admin());
    $item = trialItem();

    $masspro = collect($this->getJson('/api/v1/items?lifecycle=MASSPRO&per_page=500')->assertOk()->json('data'))
        ->pluck('id');
    expect($masspro)->not->toContain($item);

    // Tetapi di master item ia harus tetap terlihat — justru di sanalah ia diurus.
    $trial = collect($this->getJson('/api/v1/items?lifecycle=TRIAL&per_page=500')->assertOk()->json('data'))
        ->pluck('id');
    expect($trial)->toContain($item);
});

it('memberi part NPD status uji coba saat didaftarkan', function () {
    $rfq = rfqFor();
    feasFor($rfq);
    $project = app(NpdService::class)->createFromRfq($rfq, [], admin()->id);

    $item = app(NpdCostingService::class)->registerPart($project, [
        'code' => 'FG-LC-'.substr(uniqid(), -6),
        'category_id' => DB::table('m_i_category')->value('id'),
    ], admin()->id);

    expect($item->lifecycle)->toBe(ItemLifecycle::TRIAL);
});

it('membuka rencana bulanan begitu part naik ke produksi massal', function () {
    Sanctum::actingAs(admin());
    $rfq = rfqFor();
    feasFor($rfq);
    $project = app(NpdService::class)->createFromRfq($rfq, [], admin()->id);

    $item = app(NpdCostingService::class)->registerPart($project, [
        'code' => 'FG-GRAD-'.substr(uniqid(), -6),
        'category_id' => DB::table('m_i_category')->value('id'),
    ], admin()->id);

    $period = now()->addMonth()->format('Ym');

    // Selama masih uji coba: ditolak.
    $this->postJson('/api/v1/mpp', ['period' => $period, 'item_id' => $item->id, 'plan_qty' => 100])
        ->assertStatus(422);

    /*
     * Kenaikannya sendiri terjadi lewat serah terima, dan syarat lengkapnya
     * diuji di NpdHandoverTest. Yang diuji di sini adalah akibatnya: pintu-pintu
     * yang tadinya tertutup langsung terbuka.
     */
    ItemLifecycle::graduate($item->id);

    $fresh = DB::table('m_item')->where('id', $item->id)->first();
    expect($fresh->lifecycle)->toBe(ItemLifecycle::MASSPRO)
        ->and((int) $fresh->active)->toBe(1);

    $this->postJson('/api/v1/mpp', ['period' => $period, 'item_id' => $item->id, 'plan_qty' => 100])
        ->assertCreated();
});

it('menolak serah terima proyek yang partnya belum didaftarkan', function () {
    Sanctum::actingAs(admin());
    $rfq = rfqFor();
    feasFor($rfq);
    $project = app(NpdService::class)->createFromRfq($rfq, [], admin()->id);
    $project->update(['status' => 'HANDOVER']);

    $this->postJson("/api/v1/npd-projects/{$project->id}/handover")->assertStatus(422);
});
