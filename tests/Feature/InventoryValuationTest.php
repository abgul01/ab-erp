<?php

use App\Support\InventoryValuationService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * The balance sheet says how much inventory is worth in total. This says which
 * items it sits in, and which products earn their keep — so the numbers have to
 * be traceable to a lot, not averaged into something nobody can check.
 */
it('values raw material at landed cost per kilogram', function () {
    $svc = app(InventoryValuationService::class);
    $costs = $svc->rmUnitCosts();

    $landed = collect($costs)->firstWhere('basis', 'LANDED');

    expect($landed)->not->toBeNull()
        ->and($landed['unit_cost'])->toBeGreaterThan(0);

    // Every valued row is qty × its own unit cost — no rounding drift creeping
    // in between the line and the total.
    foreach (array_slice($svc->rm(), 0, 10) as $row) {
        expect($row['value'])->toBe(round($row['qty'] * $row['unit_cost'], 2));
    }
});

it('marks material that has never been costed rather than valuing it at zero silently', function () {
    $cat = DB::table('m_i_category')->value('id');
    $item = DB::table('m_item')->insertGetId([
        'code' => 'RM-VAL-'.substr(uniqid(), -6), 'part_name' => 'Bahan tanpa biaya', 'type' => 'RM',
        'category_id' => $cat, 'weight' => 10, 'min_stock' => 0, 'max_stock' => 0, 'active' => 1,
    ]);
    $inc = DB::table('wh_inc_main')->insertGetId([
        'code' => 'IN-VAL-'.substr(uniqid(), -6), 'user_id' => admin()->id,
        'gr_id' => DB::table('prc_gr_main')->value('id'),
        'date' => now()->toDateString(), 'shift_id' => DB::table('m_shift')->value('id') ?? 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('wh_inc_detail')->insert([
        'id_prim' => $inc, 'serial_id' => 'SN-VAL-'.substr(uniqid(), -8), 'length' => 6000,
        'qty' => 5, 'item_id' => $item, 'rack_id' => DB::table('m_rack')->value('id'),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $row = collect(app(InventoryValuationService::class)->rm())->firstWhere('item_id', $item);

    expect($row)->not->toBeNull()
        ->and($row['qty'])->toBe(5)
        // Zero value is a fact about the data, and the report has to say so.
        ->and($row['basis'])->toBe('NONE');
});

it('counts work in progress once, not once per process it passes', function () {
    $wip = app(InventoryValuationService::class)->wip();

    foreach ($wip as $row) {
        $cut = (int) DB::table('tr_cut_pal_pr as cp')
            ->join('tr_cut_main as cm', 'cm.id', '=', 'cp.cut_id')
            ->join('prd_wip as w', 'w.id', '=', 'cm.wip_id')
            ->where('w.wo_id', $row['wo_id'])->sum('cp.qty');

        // A piece on a pallet at three processes is still one piece: WIP can
        // never exceed what was cut for the order.
        expect($row['qty'])->toBeLessThanOrEqual($cut)->toBeGreaterThan(0);
    }
});

it('takes the cost of the lot that was actually shipped', function () {
    $period = DB::table('sls_inv_main')->whereNotIn('status', ['CANCELLED', 'DRAFT'])
        ->orderByDesc('date')->value(DB::raw("DATE_FORMAT(date, '%Y%m')"));

    $margin = app(InventoryValuationService::class)->margin($period);

    expect($margin['items'])->not->toBeEmpty()
        ->and($margin['total']['revenue'])->toBeGreaterThan(0)
        // Costed from real lots, so nothing is left unpriced in the demo month.
        ->and($margin['total']['qty_uncosted'])->toBe(0);

    foreach ($margin['items'] as $row) {
        expect($row['margin'])->toBe(round($row['revenue'] - $row['cogs'], 2));
    }
});

it('puts the worst margin first — that is the row management asks about', function () {
    $period = DB::table('sls_inv_main')->whereNotIn('status', ['CANCELLED', 'DRAFT'])
        ->orderByDesc('date')->value(DB::raw("DATE_FORMAT(date, '%Y%m')"));

    $pcts = collect(app(InventoryValuationService::class)->margin($period)['items'])
        ->pluck('margin_pct')->filter()->values();

    expect($pcts->all())->toBe($pcts->sort()->values()->all());
});

it('serves valuation and margin over HTTP', function () {
    Sanctum::actingAs(admin());

    $this->getJson('/api/v1/inventory-valuation')->assertOk()
        ->assertJsonStructure(['data' => ['as_of', 'rm', 'wip', 'fg', 'total' => ['rm', 'wip', 'fg', 'all']]]);

    $this->getJson('/api/v1/inventory-valuation/margin?period='.now()->format('Ym'))->assertOk()
        ->assertJsonStructure(['data' => ['period', 'items', 'total' => ['revenue', 'cogs', 'margin']]]);

    // A period that is not a period is a mistake worth reporting, not guessing.
    $this->getJson('/api/v1/inventory-valuation/margin?period=juli')->assertStatus(422);
});
