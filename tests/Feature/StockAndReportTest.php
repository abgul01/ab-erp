<?php

use App\Models\wh_adj_main;
use App\Support\FinancialReportService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Opname has to reach the ledger, and the statements have to hold together —
 * a balance sheet that does not balance is worse than no balance sheet.
 */
beforeEach(function () {
    Sanctum::actingAs(admin());
    DB::table('acc_period')->updateOrInsert(['period' => now()->format('Ym')], ['status' => 'OPEN']);
});

function adjSheet(array $lines): int
{
    $r = test()->postJson('/api/v1/stock-adjustments', [
        'date' => now()->toDateString(),
        'adj_type' => 'OPNAME',
        'warehouse' => 'RM',
        'reason' => 'Uji opname',
        'lines' => $lines,
    ])->assertCreated();

    return $r->json('data.id');
}

it('computes the variance from counted against system', function () {
    $item = (int) DB::table('m_item')->value('id');

    $id = adjSheet([
        ['item_id' => $item, 'qty_system' => 100, 'qty_counted' => 97, 'unit_cost' => 50_000],
    ]);

    $line = DB::table('wh_adj_detail')->where('main_id', $id)->first();

    expect((float) $line->qty_diff)->toBe(-3.0);
});

it('posts the shortfall to the ledger and locks the sheet', function () {
    $item = (int) DB::table('m_item')->value('id');
    $id = adjSheet([['item_id' => $item, 'qty_system' => 100, 'qty_counted' => 97, 'unit_cost' => 50_000]]);

    $r = $this->postJson("/api/v1/stock-adjustments/{$id}/post")->assertOk();

    expect($r->json('data.variance_value'))->toEqual(-150000)
        ->and($r->json('data.journal_id'))->not->toBeNull()
        ->and(wh_adj_main::find($id)->status)->toBe('POSTED');

    // A posted sheet is final.
    $this->postJson("/api/v1/stock-adjustments/{$id}/post")->assertStatus(423);
});

it('writes no journal when the count matched exactly', function () {
    $item = (int) DB::table('m_item')->value('id');
    $id = adjSheet([['item_id' => $item, 'qty_system' => 40, 'qty_counted' => 40, 'unit_cost' => 10_000]]);

    $r = $this->postJson("/api/v1/stock-adjustments/{$id}/post")->assertOk();

    expect($r->json('data.variance_value'))->toEqual(0)
        ->and($r->json('data.journal_id'))->toBeNull();
});

it('offers the system figure to count against', function () {
    $this->getJson('/api/v1/stock-adjustments/system-stock?warehouse=RM')->assertOk();
    $this->getJson('/api/v1/stock-adjustments/system-stock?warehouse=FG')->assertOk();
});

it('produces a balance sheet that balances', function () {
    $bs = (new FinancialReportService)->balanceSheet(now()->format('Ym'));

    expect($bs['balanced'])->toBeTrue()
        ->and(abs($bs['difference']))->toBeLessThan(0.01);
});

it('keeps the income statement to its own period', function () {
    $svc = new FinancialReportService;

    $thisMonth = $svc->incomeStatement(now()->format('Ym'));
    $cumulative = $svc->incomeStatement(now()->format('Ym'), '200001');

    // A cumulative window can only contain more, never less.
    expect(abs($cumulative['total_revenue']))->toBeGreaterThanOrEqual(abs($thisMonth['total_revenue']));
});

it('buckets ageing by days past due, not days since issue', function () {
    $ven = (int) DB::table('m_contacts')->value('id');
    DB::table('prc_inv_main')->insert([
        'code' => 'PI-AGE-'.substr(uniqid(), -8), 'date' => '2026-01-01',
        'due_date' => now()->addDays(30)->toDateString(),     // long terms, not yet due
        'ven_id' => $ven, 'inv_no' => 'INV-AGE', 'dpp' => 1_000_000, 'vat' => 0, 'wht23' => 0,
        'total' => 1_000_000, 'user_id' => admin()->id, 'status' => 'POSTED',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $aging = (new FinancialReportService)->apAging();
    $row = collect($aging['items'])->firstWhere('code', 'like', 'PI-AGE-%')
        ?? collect($aging['items'])->first(fn ($i) => str_starts_with($i['code'], 'PI-AGE-'));

    expect($row['bucket'])->toBe('current')      // six months old but not overdue
        ->and($row['days_late'])->toBe(0);
});

it('answers the report endpoints', function () {
    $this->getJson('/api/v1/reports/balance-sheet')->assertOk()->assertJsonStructure(['data' => ['total_assets', 'balanced']]);
    $this->getJson('/api/v1/reports/income-statement')->assertOk()->assertJsonStructure(['data' => ['net_income']]);
    $this->getJson('/api/v1/reports/ap-aging')->assertOk()->assertJsonStructure(['data' => ['buckets', 'items']]);
    $this->getJson('/api/v1/reports/ar-aging')->assertOk()->assertJsonStructure(['data' => ['buckets', 'items']]);
});

it('retires an asset once and books the gain or loss', function () {
    $asset = DB::table('ast_main')->insertGetId([
        'code' => 'AST-RET-'.substr(uniqid(), -6), 'categ_id' => DB::table('m_asset_categ')->value('id'),
        'name' => 'Mesin uji', 'acq_date' => '2024-01-01', 'acq_cost' => 100_000_000,
        'useful_life' => 60, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $r = $this->postJson("/api/v1/assets/{$asset}/retire", [
        'action' => 'DISPOSED', 'date' => now()->toDateString(),
        'proceeds' => 20_000_000, 'reason' => 'Dijual ke pihak ketiga',
    ])->assertOk();

    expect($r->json('data.book_value'))->toEqual(100000000)
        ->and($r->json('data.gain_loss'))->toEqual(-80000000)      // sold below book value
        ->and(DB::table('ast_main')->where('id', $asset)->value('status'))->toBe('DISPOSED');

    // Retiring twice must not be possible.
    $this->postJson("/api/v1/assets/{$asset}/retire", [
        'action' => 'DISPOSED', 'date' => now()->toDateString(), 'reason' => 'lagi',
    ])->assertStatus(422);
});
