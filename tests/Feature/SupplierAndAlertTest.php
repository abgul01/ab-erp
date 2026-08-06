<?php

use App\Support\AlertService;
use App\Support\FgStockService;
use App\Support\MrpService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Buying terms belong to a supplier, not to the steel — and the warnings that
 * give a buyer time to act have to arrive before the refusal does.
 */
function supplierFor(int $itemId, array $terms): int
{
    $ven = DB::table('m_contacts')->where('category_id', 1)->value('id')
        ?: DB::table('m_contacts')->value('id');

    return (int) DB::table('m_supplier_item')->insertGetId(array_merge([
        'ven_id' => $ven, 'item_id' => $itemId, 'priority' => 1,
        'price' => 100000, 'moq' => 0, 'order_lot' => 0, 'lead_time_days' => 0,
        'active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ], $terms));
}

it('prefers the supplier terms over the item fallback', function () {
    $f = mrpFixture(['plan' => 100, 'moq' => 25, 'lot' => 25]);   // item says 25/25
    DB::table('m_item')->where('id', $f['rm'])->update(['lead_time_days' => 60]);

    supplierFor($f['rm'], ['moq' => 5, 'order_lot' => 5, 'lead_time_days' => 7, 'price' => 12345]);

    $terms = (new MrpService(app(FgStockService::class)))->supplierTerms($f['rm']);

    expect($terms['source'])->toBe('SUPPLIER')
        ->and($terms['moq'])->toBe(5)
        ->and($terms['lot'])->toBe(5)
        ->and($terms['lead'])->toBe(7)
        ->and($terms['price'])->toBe(12345.0);
});

it('falls back to the item when no supplier is agreed', function () {
    $f = mrpFixture(['plan' => 100, 'moq' => 25, 'lot' => 25]);

    $terms = (new MrpService(app(FgStockService::class)))->supplierTerms($f['rm']);

    expect($terms['source'])->toBe('ITEM')
        ->and($terms['ven_id'])->toBeNull()
        ->and($terms['moq'])->toBe(25);
});

it('picks priority 1, and the cheaper one when priorities tie', function () {
    $f = mrpFixture(['plan' => 100]);
    $venB = DB::table('m_contacts')->where('category_id', 1)->skip(1)->value('id')
        ?: DB::table('m_contacts')->skip(1)->value('id');

    supplierFor($f['rm'], ['priority' => 2, 'price' => 50000]);          // cheap but second choice
    supplierFor($f['rm'], ['priority' => 1, 'price' => 90000, 'ven_id' => $venB]);

    $terms = (new MrpService(app(FgStockService::class)))->supplierTerms($f['rm']);

    // Priority beats price: the first-choice mill is chosen even though it costs more.
    expect($terms['price'])->toBe(90000.0)
        ->and($terms['ven_id'])->toBe((int) $venB);
});

it('ignores an expired supplier agreement', function () {
    $f = mrpFixture(['plan' => 100]);
    supplierFor($f['rm'], ['valid_to' => now()->subDay()->toDateString(), 'moq' => 999]);

    expect((new MrpService(app(FgStockService::class)))->supplierTerms($f['rm'])['source'])->toBe('ITEM');
});

it('names the supplier on the requisition it raises', function () {
    $f = mrpFixture(['plan' => 100]);
    supplierFor($f['rm'], ['moq' => 25, 'order_lot' => 25, 'price' => 77000, 'lead_time_days' => 30]);

    $svc = new MrpService(app(FgStockService::class));
    $run = $svc->run([$f['period']], admin()->id);
    $result = $svc->generatePr($run->id, admin()->id);

    $line = DB::table('prc_pr_detail')->where('main_id', $result['pr_id'])->where('item_id', $f['rm'])->first();

    // A requisition that names neither supplier nor price leaves the buyer to
    // rediscover what MRP already worked out.
    expect($line->ven_id)->not->toBeNull()
        ->and((float) $line->est_price)->toBe(77000.0)
        ->and((int) $line->qty)->toBe(25);
});

it('keeps the supplier and price on a requisition edited by hand', function () {
    Sanctum::actingAs(admin());
    $f = mrpFixture(['plan' => 100]);
    $ven = DB::table('m_contacts')->value('id');

    $pr = $this->postJson('/api/v1/pr', [
        'date' => now()->toDateString(),
        'pr_type' => 'MANUAL',
        'lines' => [['item_id' => $f['rm'], 'qty' => 10, 'ven_id' => $ven, 'est_price' => 54321]],
    ])->assertCreated()->json('data');

    // Editing used to drop both, so a requisition raised by MRP came out of its
    // first edit naming nobody.
    $this->putJson("/api/v1/pr/{$pr['id']}", [
        'date' => now()->toDateString(),
        'pr_type' => 'MANUAL',
        'lines' => [['item_id' => $f['rm'], 'qty' => 12, 'ven_id' => $ven, 'est_price' => 54321]],
    ])->assertOk()
        ->assertJsonPath('data.detail.0.ven_id', (int) $ven)
        ->assertJsonPath('data.detail.0.qty', 12);

    expect((float) DB::table('prc_pr_detail')->where('main_id', $pr['id'])->value('est_price'))->toBe(54321.0);
});

it('serves the buying terms a requisition screen needs', function () {
    Sanctum::actingAs(admin());
    $f = mrpFixture(['plan' => 100]);
    supplierFor($f['rm'], ['price' => 88000, 'lead_time_days' => 45, 'moq' => 10]);

    $this->getJson("/api/v1/supplier-items/terms?item_id={$f['rm']}")->assertOk()
        ->assertJsonPath('data.effective.source', 'SUPPLIER')
        ->assertJsonPath('data.effective.lead', 45)
        ->assertJsonCount(1, 'data.suppliers')
        ->assertJsonPath('data.effective.price', fn ($v) => (float) $v === 88000.0);
});

it('warns when import quota runs low and clears itself when topped up', function () {
    $quota = DB::table('m_quota')->insertGetId([
        'code' => 'Q-TEST-'.substr(uniqid(), -6), 'descrip' => 'Kuota uji', 'hs_code' => '7304.31',
        'total_ton' => 100, 'valid_from' => now()->startOfYear(), 'valid_to' => now()->endOfYear(),
        'active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    // 95 tonnes consumed of 100 → 5% left.
    DB::table('prc_quota_txn')->insert([
        'quota_id' => $quota, 'ref_type' => 'GR_ACTUAL', 'ref_id' => 1,
        'ton' => 95, 'sign' => -1, 'user_id' => admin()->id, 'created_at' => now(),
    ]);

    $svc = new AlertService;
    $svc->checkQuota();

    $alert = DB::table('sys_alert')->where('type', 'QUOTA')->where('ref_id', $quota)->first();
    expect($alert)->not->toBeNull()
        ->and($alert->severity)->toBe('CRITICAL')
        ->and($alert->resolved_at)->toBeNull();

    // Allocation raised — the condition no longer holds.
    DB::table('m_quota')->where('id', $quota)->update(['total_ton' => 1000]);
    $svc->checkQuota();

    expect(DB::table('sys_alert')->where('id', $alert->id)->value('resolved_at'))->not->toBeNull();
});

it('raises one alert per condition however often it is checked', function () {
    $svc = new AlertService;

    $svc->checkMinStock();
    $first = DB::table('sys_alert')->where('type', 'MIN_STOCK')->count();

    $svc->checkMinStock();
    $svc->checkMinStock();

    // An inbox that grows by a row per run stops being read.
    expect(DB::table('sys_alert')->where('type', 'MIN_STOCK')->count())->toBe($first);
});

it('does not warn about a shortage a purchase order already covers', function () {
    $cat = DB::table('m_i_category')->value('id');
    $item = DB::table('m_item')->insertGetId([
        'code' => 'RM-ALERT-'.substr(uniqid(), -6), 'part_name' => 'Uji alert', 'type' => 'RM',
        'category_id' => $cat, 'min_stock' => 100, 'max_stock' => 0, 'active' => 1,
    ]);

    $svc = new AlertService;
    $svc->checkMinStock();
    expect(DB::table('sys_alert')->where('type', 'MIN_STOCK')->where('ref_id', $item)->whereNull('resolved_at')->exists())->toBeTrue();

    // 150 already on order covers the minimum of 100.
    $po = DB::table('prc_po_main')->insertGetId([
        'code' => 'PO-ALERT-'.substr(uniqid(), -6), 'date' => now()->toDateString(), 'po_type' => 'LOCAL',
        'source' => 'MANUAL', 'ven_id' => DB::table('m_contacts')->value('id'), 'user_id' => admin()->id,
        'status' => 'OPEN', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('prc_po_detail')->insert([
        'main_id' => $po, 'item_id' => $item, 'qty' => 150, 'qty_received' => 0, 'price' => 1000,
    ]);

    $svc->checkMinStock();

    expect(DB::table('sys_alert')->where('type', 'MIN_STOCK')->where('ref_id', $item)->whereNull('resolved_at')->exists())->toBeFalse();
});

it('exposes alerts over HTTP', function () {
    Sanctum::actingAs(admin());

    $this->postJson('/api/v1/alerts/refresh')->assertOk()
        ->assertJsonStructure(['data' => ['quota', 'min_stock', 'message']]);

    $this->getJson('/api/v1/alerts')->assertOk()
        ->assertJsonStructure(['data' => ['open', 'critical', 'items']]);
});
