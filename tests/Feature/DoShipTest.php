<?php

use App\Http\Controllers\Api\Sales\DoController;
use App\Support\FgStockService;
use Illuminate\Support\Facades\DB;

/**
 * Shipping a Delivery Order draws FG down per lot (FIFO by default), bumps the
 * SO line's delivered qty, and closes the SO once every line is fully delivered.
 */
it('ships a DO FIFO across lots, bumps qty_delivered, closes the SO', function () {
    $cat = DB::table('m_i_category')->value('id');
    $cus = DB::table('m_contacts')->value('id');
    $item = DB::table('m_item')->insertGetId([
        'code' => 'FG-SHIP-'.uniqid(), 'part_name' => 'Ship FG', 'type' => 'Pipe',
        'category_id' => $cat, 'min_stock' => 0, 'max_stock' => 0, 'active' => 1,
    ]);

    // two FG lots: 60 (older) + 60 (newer)
    foreach ([['L1', '2026-07-01', 60], ['L2', '2026-07-05', 60]] as [$lot, $date, $qty]) {
        $m = DB::table('tr_inc_fg_main')->insertGetId(['code' => 'FGI-'.uniqid(), 'date' => $date, 'user_id' => 'U0001', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tr_inc_fg_det')->insert(['code' => $lot.'-'.uniqid(), 'main_id' => $m, 'pal_pro_code' => 'P-'.$lot, 'item_id' => $item, 'cut_id' => 0, 'qty' => $qty, 'wip_id' => null, 'created_at' => now(), 'updated_at' => now()]);
    }

    // approved SO with one line of 100
    $so = DB::table('sls_so_main')->insertGetId(['code' => 'SO-SHIP-'.uniqid(), 'date' => now()->toDateString(), 'cus_id' => $cus, 'user_id' => admin()->id, 'status' => 'APPROVED', 'created_at' => now(), 'updated_at' => now()]);
    $sd = DB::table('sls_so_detail')->insertGetId(['main_id' => $so, 'item_id' => $item, 'qty' => 100, 'price' => 10000, 'qty_delivered' => 0]);

    // a DRAFT DO delivering the full 100
    // code flows into tr_out_fg_main.code_do (varchar20), so keep it short
    $do = DB::table('sls_do_main')->insertGetId(['code' => 'DOT-'.substr(uniqid(), -8), 'date' => now()->toDateString(), 'so_id' => $so, 'user_id' => admin()->id, 'status' => 'DRAFT', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('sls_do_detail')->insert(['main_id' => $do, 'so_detail_id' => $sd, 'item_id' => $item, 'qty' => 100, 'fg_code' => null]);

    $fg = new FgStockService;
    expect($fg->stock($item))->toBe(120);

    app(DoController::class)->ship(req('POST', ['auto' => true]), $do);

    // 100 shipped FIFO: 60 from L1 (emptied) + 40 from L2 (20 left)
    $lots = $fg->availableLots($item);
    expect($fg->stock($item))->toBe(20)
        ->and($lots)->toHaveCount(1)
        ->and((int) $lots->first()->remaining)->toBe(20)
        ->and((int) DB::table('sls_so_detail')->where('id', $sd)->value('qty_delivered'))->toBe(100)
        ->and(DB::table('sls_do_main')->where('id', $do)->value('status'))->toBe('SHIPPED')
        ->and(DB::table('sls_so_main')->where('id', $so)->value('status'))->toBe('CLOSED');   // fully delivered
});
