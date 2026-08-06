<?php

use App\Support\FgStockService;
use App\Support\MrpService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * MRP earns its keep by asking for what is genuinely missing.
 *
 * Requirement comes from the approved plan exploded through the bill of
 * material; supply is stock on the rack plus anything already ordered or
 * requisitioned. Getting either side wrong means buying the same steel twice.
 */
function mrpFixture(array $opts = []): array
{
    $cat = DB::table('m_i_category')->value('id');
    $period = $opts['period'] ?? now()->format('Ym');

    $rm = DB::table('m_item')->insertGetId([
        'code' => 'RM-MRP-'.substr(uniqid(), -8), 'part_name' => 'Pipa uji MRP', 'type' => 'RM',
        'category_id' => $cat, 'length' => 6000, 'weight' => 17.3,
        'min_stock' => 0, 'max_stock' => 0,
        'moq' => $opts['moq'] ?? 0, 'order_lot' => $opts['lot'] ?? 0,
        'active' => 1,
    ]);
    $fg = DB::table('m_item')->insertGetId([
        'code' => 'FG-MRP-'.substr(uniqid(), -8), 'part_name' => 'FG uji MRP', 'type' => 'FG',
        'category_id' => $cat, 'min_stock' => 0, 'max_stock' => 0, 'active' => 1,
    ]);

    // One bar (6000 mm) yields 10 pieces of 600 mm.
    $bom = DB::table('m_bom')->insertGetId(['item_id' => $fg, 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('m_bom_det_rm')->insert([
        'id_prim' => $bom, 'mat_id' => $rm, 'length_cut' => 600, 'length_use' => 600,
        'priority' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('prd_mpp')->updateOrInsert(
        ['period' => $period, 'item_id' => $fg],
        ['plan_qty' => $opts['plan'] ?? 100, 'status' => 'APPROVED', 'created_at' => now(), 'updated_at' => now()]
    );

    return ['rm' => $rm, 'fg' => $fg, 'period' => $period];
}

/** Rows the engine produced for one material. */
function mrpRow(array $rows, int $itemId): ?array
{
    foreach ($rows as $r) {
        if ($r['item_id'] === $itemId) {
            return $r;
        }
    }

    return null;
}

it('explodes the approved plan through the BOM into whole bars', function () {
    $f = mrpFixture(['plan' => 100]);          // 100 pcs × 600 mm = 60 000 mm

    $rows = (new MrpService(app(FgStockService::class)))->explode([$f['period']]);
    $rm = mrpRow($rows, $f['rm']);

    // 60 000 mm over 6 000 mm bars = 10 bars; nothing on hand, so all 10 are needed.
    expect($rm['gross_req'])->toBe(10)
        ->and($rm['net_req'])->toBe(10)
        ->and($rm['suggestion'])->toBe('PR');
});

it('does not re-order material that is already on an open purchase order', function () {
    $f = mrpFixture(['plan' => 100]);
    $ven = DB::table('m_contacts')->value('id');

    $po = DB::table('prc_po_main')->insertGetId([
        'code' => 'PO-MRP-'.substr(uniqid(), -6), 'date' => now()->toDateString(), 'po_type' => 'LOCAL',
        'source' => 'MANUAL', 'ven_id' => $ven, 'user_id' => admin()->id,
        'status' => 'OPEN', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('prc_po_detail')->insert([
        'main_id' => $po, 'item_id' => $f['rm'], 'qty' => 6, 'qty_received' => 0, 'price' => 1000,
    ]);

    $rows = (new MrpService(app(FgStockService::class)))->explode([$f['period']]);
    $rm = mrpRow($rows, $f['rm']);

    // 10 needed, 6 already coming → only 4 left to buy.
    expect($rm['open_po'])->toBe(6)
        ->and($rm['net_req'])->toBe(4);
});

it('ignores a cancelled or draft purchase order as supply', function () {
    $f = mrpFixture(['plan' => 100]);
    $ven = DB::table('m_contacts')->value('id');

    foreach (['DRAFT', 'CANCELLED', 'CLOSE'] as $status) {
        $po = DB::table('prc_po_main')->insertGetId([
            'code' => 'PO-MRP-'.substr(uniqid(), -6), 'date' => now()->toDateString(), 'po_type' => 'LOCAL',
            'source' => 'MANUAL', 'ven_id' => $ven, 'user_id' => admin()->id,
            'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('prc_po_detail')->insert([
            'main_id' => $po, 'item_id' => $f['rm'], 'qty' => 50, 'qty_received' => 0, 'price' => 1000,
        ]);
    }

    $rows = (new MrpService(app(FgStockService::class)))->explode([$f['period']]);
    $rm = mrpRow($rows, $f['rm']);

    // None of those are a commitment, so the full requirement stands.
    expect($rm['open_po'])->toBe(0)
        ->and($rm['net_req'])->toBe(10);
});

it('counts a requisition that has not become a PO yet', function () {
    $f = mrpFixture(['plan' => 100]);

    $pr = DB::table('prc_pr_main')->insertGetId([
        'code' => 'PR-MRP-'.substr(uniqid(), -6), 'date' => now()->toDateString(),
        'pr_type' => 'MRP', 'user_id' => admin()->id, 'status' => 'APPROVED',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('prc_pr_detail')->insert(['main_id' => $pr, 'item_id' => $f['rm'], 'qty' => 7]);

    $rows = (new MrpService(app(FgStockService::class)))->explode([$f['period']]);
    $rm = mrpRow($rows, $f['rm']);

    // Asking again for what was already requested is the duplication this prevents.
    expect($rm['open_po'])->toBe(7)
        ->and($rm['net_req'])->toBe(3);
});

it('rounds an order up to the minimum and the supplier pack size', function () {
    // Shortage is 10 bars, but the mill sells no fewer than 25, in bundles of 25.
    $f = mrpFixture(['plan' => 100, 'moq' => 25, 'lot' => 25]);

    $rows = (new MrpService(app(FgStockService::class)))->explode([$f['period']]);
    $rm = mrpRow($rows, $f['rm']);

    expect($rm['gross_req'])->toBe(10)
        ->and($rm['net_req'])->toBe(25);
});

it('reports the weight, because steel is bought by the kilo', function () {
    $f = mrpFixture(['plan' => 100]);

    $rows = (new MrpService(app(FgStockService::class)))->explode([$f['period']]);
    $rm = mrpRow($rows, $f['rm']);

    expect($rm['net_req_kg'])->toBeMoney(10 * 17.3);
});

it('turns a run into a draft requisition dated for when the material is needed', function () {
    Sanctum::actingAs(admin());
    $f = mrpFixture(['plan' => 100, 'moq' => 25, 'lot' => 25]);

    $svc = new MrpService(app(FgStockService::class));
    $run = $svc->run([$f['period']], admin()->id);

    $result = $svc->generatePr($run->id, admin()->id);

    $line = DB::table('prc_pr_detail as d')
        ->join('prc_pr_main as m', 'm.id', '=', 'd.main_id')
        ->where('m.id', $result['pr_id'])->where('d.item_id', $f['rm'])
        ->first(['d.qty', 'd.need_date', 'm.status', 'm.pr_type']);

    /*
     * The requisition is dated when the buyer has to act, which is the date the
     * material is needed less its lead time — never a date already in the past.
     * The seeded period starts before today, so this line comes out dated today
     * rather than back-dated to the first of the month.
     */
    expect($line)->not->toBeNull()
        ->and((int) $line->qty)->toBe(25)                       // already netted and rounded
        ->and($line->status)->toBe('DRAFT')                     // needs approval before it buys anything
        ->and($line->pr_type)->toBe('MRP')
        ->and($line->need_date)->toBe(now()->toDateString());
});

it('refuses to raise an empty requisition', function () {
    $svc = new MrpService(app(FgStockService::class));

    // A run with nothing short of stock has nothing to buy.
    $run = $svc->run(['209901'], admin()->id);

    expect(fn () => $svc->generatePr($run->id, admin()->id))
        ->toThrow(RuntimeException::class);
});

it('produces rows a planner can check by hand', function () {
    $f = mrpFixture(['plan' => 100]);
    $next = Carbon::createFromFormat('Ym', $f['period'])->addMonth()->format('Ym');

    DB::table('prd_mpp')->updateOrInsert(
        ['period' => $next, 'item_id' => $f['fg']],
        ['plan_qty' => 150, 'status' => 'APPROVED', 'created_at' => now(), 'updated_at' => now()]
    );

    $rows = (new MrpService(app(FgStockService::class)))->explode([$f['period'], $next]);

    /*
     * Every row must satisfy gross − onhand − open = net. Supply is carried
     * across periods, and the row used to keep reporting the original snapshot
     * while netting against the carried balance — which showed a shortage of
     * 340 sitting next to 1 820 of open work orders.
     */
    foreach ($rows as $r) {
        $supply = $r['onhand'] + $r['open_po'] + $r['open_wo'];
        $expected = max(0, $r['gross_req'] - $supply);

        // Raw material rounds up to a purchase lot, so it may exceed the gap.
        expect($r['net_req'])->toBeGreaterThanOrEqual($expected, "baris item {$r['item_id']} periode {$r['period']} tidak konsisten");

        if ($r['suggestion'] === 'WO') {
            expect($r['net_req'])->toBe($expected);
        }
    }
});

it('never treats a negative stock balance as available supply', function () {
    $f = mrpFixture(['plan' => 100]);

    // A stock error: more shipped than was ever received.
    $main = DB::table('tr_out_fg_main')->insertGetId([
        'code' => 'OUT-NEG-'.substr(uniqid(), -6), 'code_do' => 'DO-NEG', 'date' => now()->toDateString(),
        'user_id' => admin()->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('tr_out_fg_det')->insert([
        'main_id' => $main, 'item_id' => $f['fg'], 'fg_code' => 'LOT-NEG', 'code' => 'OUT-NEG-A', 'qty' => 500,
    ]);

    $rows = (new MrpService(app(FgStockService::class)))->explode([$f['period']]);
    $fg = mrpRow($rows, $f['fg']);

    // Negative stock is a discrepancy to investigate, not supply — and it must
    // never reduce what the plan asks to be made.
    expect($fg['onhand'])->toBe(0)
        ->and($fg['net_req'])->toBe(100);
});

it('takes the highest demand signal rather than adding them up', function () {
    $f = mrpFixture(['plan' => 100]);          // MPP = 100
    $cus = DB::table('m_contacts')->value('id');

    DB::table('sls_forecast')->insert([
        'cus_id' => $cus, 'item_id' => $f['fg'], 'period' => $f['period'],
        'version' => 1, 'qty' => 250, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $demand = (new MrpService(app(FgStockService::class)))->grossDemand($f['period']);

    /*
     * 100 planned and 250 forecast is not demand for 350 — the plan is an
     * attempt to meet that same forecast. The higher figure wins.
     */
    expect($demand[$f['fg']]['qty'])->toBe(250)
        ->and($demand[$f['fg']]['src'])->toBe('FORECAST')
        ->and($demand[$f['fg']]['mpp'])->toBe(100);
});

it('lets a firm sales order outrank the plan and the forecast', function () {
    $f = mrpFixture(['plan' => 100]);
    $cus = DB::table('m_contacts')->value('id');

    $so = DB::table('sls_so_main')->insertGetId([
        'code' => 'SO-MRP-'.substr(uniqid(), -6), 'date' => now()->toDateString(),
        'cus_id' => $cus, 'user_id' => admin()->id, 'status' => 'APPROVED',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('sls_so_detail')->insert([
        'main_id' => $so, 'item_id' => $f['fg'], 'qty' => 400, 'qty_delivered' => 0,
        'price' => 50000, 'due_date' => now()->endOfMonth()->toDateString(),
    ]);

    $demand = (new MrpService(app(FgStockService::class)))->grossDemand($f['period']);

    expect($demand[$f['fg']]['qty'])->toBe(400)
        ->and($demand[$f['fg']]['src'])->toBe('SO');
});

it('counts only the undelivered part of a sales order', function () {
    $f = mrpFixture(['plan' => 0]);
    $cus = DB::table('m_contacts')->value('id');

    $so = DB::table('sls_so_main')->insertGetId([
        'code' => 'SO-MRP-'.substr(uniqid(), -6), 'date' => now()->toDateString(),
        'cus_id' => $cus, 'user_id' => admin()->id, 'status' => 'APPROVED',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('sls_so_detail')->insert([
        'main_id' => $so, 'item_id' => $f['fg'], 'qty' => 400, 'qty_delivered' => 300,
        'price' => 50000, 'due_date' => now()->endOfMonth()->toDateString(),
    ]);

    $demand = (new MrpService(app(FgStockService::class)))->grossDemand($f['period']);

    // 300 already shipped is demand that has been met.
    expect($demand[$f['fg']]['so'])->toBe(100);
});

it('ignores a cancelled sales order', function () {
    $f = mrpFixture(['plan' => 0]);
    $cus = DB::table('m_contacts')->value('id');

    $so = DB::table('sls_so_main')->insertGetId([
        'code' => 'SO-MRP-'.substr(uniqid(), -6), 'date' => now()->toDateString(),
        'cus_id' => $cus, 'user_id' => admin()->id, 'status' => 'CANCELLED',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('sls_so_detail')->insert([
        'main_id' => $so, 'item_id' => $f['fg'], 'qty' => 999, 'qty_delivered' => 0,
        'price' => 50000, 'due_date' => now()->endOfMonth()->toDateString(),
    ]);

    $demand = (new MrpService(app(FgStockService::class)))->grossDemand($f['period']);

    expect($demand)->not->toHaveKey($f['fg']);
});

it('counts a released work order only for what it still owes', function () {
    $f = mrpFixture(['plan' => 100]);

    $wo = DB::table('prd_wo_main')->insertGetId([
        'code' => 'WO-MRP-'.substr(uniqid(), -6), 'date' => now()->toDateString(),
        'customer_id' => DB::table('m_contacts')->value('id'), 'so_id' => '-',
        'fg_id' => $f['fg'], 'qty' => 60, 'user_id' => admin()->id,
        'status' => 2, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $wip = DB::table('prd_wip')->insertGetId([
        'code' => 'WIP-MRP-'.substr(uniqid(), -6), 'wo_id' => $wo, 'item_id' => $f['fg'],
        'created_at' => now(), 'updated_at' => now(),
    ]);
    // 40 of those 60 have already reached the warehouse.
    $fgMain = DB::table('tr_inc_fg_main')->insertGetId([
        'code' => 'RFG-MRP-'.substr(uniqid(), -6), 'date' => now()->toDateString(),
        'user_id' => admin()->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('tr_inc_fg_det')->insert([
        'code' => 'LOT-MRP-'.substr(uniqid(), -6), 'main_id' => $fgMain, 'pal_pro_code' => 'P1',
        'item_id' => $f['fg'], 'cut_id' => 0, 'qty' => 40, 'wip_id' => $wip,
        'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $rows = (new MrpService(app(FgStockService::class)))->explode([$f['period']]);
    $fg = mrpRow($rows, $f['fg']);

    // 20 still owed by the order, 40 already counted as stock — not 60 twice.
    expect($fg['open_wo'])->toBe(20)
        ->and($fg['onhand'])->toBe(40)
        ->and($fg['net_req'])->toBe(40);   // 100 − 40 − 20
});

it('does not treat a draft work order as committed production', function () {
    $f = mrpFixture(['plan' => 100]);

    DB::table('prd_wo_main')->insert([
        'code' => 'WO-MRP-'.substr(uniqid(), -6), 'date' => now()->toDateString(),
        'customer_id' => DB::table('m_contacts')->value('id'), 'so_id' => '-',
        'fg_id' => $f['fg'], 'qty' => 500, 'user_id' => admin()->id,
        'status' => 1, 'created_at' => now(), 'updated_at' => now(),   // DRAFT
    ]);

    $rows = (new MrpService(app(FgStockService::class)))->explode([$f['period']]);
    $fg = mrpRow($rows, $f['fg']);

    expect($fg['open_wo'])->toBe(0)
        ->and($fg['net_req'])->toBe(100);
});

it('exposes generate-PR over HTTP', function () {
    Sanctum::actingAs(admin());
    $f = mrpFixture(['plan' => 100]);

    $run = (new MrpService(app(FgStockService::class)))->run([$f['period']], admin()->id);

    $this->postJson("/api/v1/mrp/{$run->id}/generate-pr")
        ->assertCreated()
        ->assertJsonStructure(['data' => ['pr_id', 'code', 'items', 'total_qty', 'total_kg']]);
});
