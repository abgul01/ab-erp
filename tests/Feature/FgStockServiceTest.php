<?php

use App\Support\FgStockService;
use Illuminate\Support\Facades\DB;

/**
 * FG stock is tracked per lot: each tr_inc_fg_det is a batch whose remaining is
 * its qty minus the tr_out_fg_det that reference its code. availableLots lists
 * batches with remaining > 0, oldest first (FIFO).
 */
function stockLot(int $item, string $lotCode, int $qty, string $date): void
{
    $main = DB::table('tr_inc_fg_main')->insertGetId([
        'code' => 'FGI-T-'.uniqid(), 'date' => $date, 'user_id' => 'U0001',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('tr_inc_fg_det')->insert([
        'code' => $lotCode, 'main_id' => $main, 'pal_pro_code' => 'PAL-'.$lotCode,
        'item_id' => $item, 'cut_id' => 0, 'qty' => $qty, 'wip_id' => null,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function issueLot(int $item, string $lotCode, int $qty): void
{
    $main = DB::table('tr_out_fg_main')->insertGetId([
        'code' => 'FGO-T-'.uniqid(), 'code_do' => 'DO-T', 'date' => now()->toDateString(),
        'user_id' => 'U0001', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('tr_out_fg_det')->insert([
        'main_id' => $main, 'item_id' => $item, 'fg_code' => $lotCode,
        'code' => 'OUT-'.uniqid(), 'qty' => $qty, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

beforeEach(function () {
    // an isolated item with no prior FG movements, so counts are deterministic
    $this->item = DB::table('m_item')->orderByDesc('id')->value('id');
    DB::table('tr_out_fg_det')->where('item_id', $this->item)->delete();
    DB::table('tr_inc_fg_det')->where('item_id', $this->item)->delete();
});

it('reports per-lot remaining after partial issue, FIFO order', function () {
    $fg = new FgStockService;
    stockLot($this->item, 'LOT-A', 100, '2026-07-01');   // older
    stockLot($this->item, 'LOT-B', 60, '2026-07-05');    // newer
    issueLot($this->item, 'LOT-A', 30);

    $lots = $fg->availableLots($this->item);

    expect($lots)->toHaveCount(2)
        ->and($lots->first()->lot_code)->toBe('LOT-A')      // FIFO: oldest first
        ->and((int) $lots->first()->remaining)->toBe(70)    // 100 − 30
        ->and((int) $lots->last()->remaining)->toBe(60)
        ->and($fg->stock($this->item))->toBe(130);          // Σin − Σout
});

it('drops a lot from the list once fully issued', function () {
    $fg = new FgStockService;
    stockLot($this->item, 'LOT-C', 40, '2026-07-01');
    issueLot($this->item, 'LOT-C', 40);

    expect($fg->availableLots($this->item))->toHaveCount(0)
        ->and($fg->stock($this->item))->toBe(0);
});
