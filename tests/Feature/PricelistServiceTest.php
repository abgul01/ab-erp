<?php

use App\Support\PricelistService;
use Illuminate\Support\Facades\DB;

/**
 * The customer price on a date/qty: only ACTIVE lists, only lines whose validity
 * covers the date and whose min_qty the order meets; the highest met min_qty
 * wins, then the newest window.
 */
function makePricelist(): array
{
    $cus = DB::table('m_contacts')->value('id');
    $item = DB::table('m_item')->value('id');
    $cur = DB::table('m_currency')->value('id');

    $main = DB::table('m_pricelist_main')->insertGetId([
        'code' => 'PL-TEST-'.uniqid(), 'cus_id' => $cus, 'status' => 'ACTIVE',
        'user_id' => admin()->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $rows = [
        ['main_id' => $main, 'item_id' => $item, 'price' => 10000, 'currency_id' => $cur, 'valid_from' => '2026-01-01', 'valid_to' => '2026-06-30', 'min_qty' => 0],
        ['main_id' => $main, 'item_id' => $item, 'price' => 12000, 'currency_id' => $cur, 'valid_from' => '2026-07-01', 'valid_to' => '2026-12-31', 'min_qty' => 0],
        ['main_id' => $main, 'item_id' => $item, 'price' => 11000, 'currency_id' => $cur, 'valid_from' => '2026-07-01', 'valid_to' => '2026-12-31', 'min_qty' => 100],
    ];
    DB::table('m_pricelist_det')->insert($rows);

    return [$cus, $item, $main];
}

it('picks the price valid on the order date', function () {
    [$cus, $item] = makePricelist();

    expect((float) PricelistService::find($cus, $item, '2026-08-10', 10)->price)->toBe(12000.0)
        ->and((float) PricelistService::find($cus, $item, '2026-03-10', 10)->price)->toBe(10000.0);
});

it('prefers the highest met volume tier (min_qty)', function () {
    [$cus, $item] = makePricelist();

    expect((float) PricelistService::find($cus, $item, '2026-08-10', 150)->price)->toBe(11000.0);
});

it('returns null when no window is valid', function () {
    [$cus, $item] = makePricelist();

    expect(PricelistService::find($cus, $item, '2027-01-10', 10))->toBeNull();
});

it('ignores INACTIVE pricelists', function () {
    [$cus, $item, $main] = makePricelist();
    DB::table('m_pricelist_main')->where('id', $main)->update(['status' => 'INACTIVE']);

    expect(PricelistService::find($cus, $item, '2026-08-10', 10))->toBeNull();
});
