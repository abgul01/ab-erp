<?php

use App\Jobs\GenerateCogmJob;
use App\Jobs\RunCrpJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/**
 * The period-driven runs accept one month or several.
 *
 * Capacity is reviewed over a quarter and depreciation is caught up after a
 * late close, so making the planner fire the same action once per month is
 * busywork. Single-period callers must keep working unchanged.
 */
beforeEach(fn () => Sanctum::actingAs(admin()));

it('queues one CRP job per month requested', function () {
    Queue::fake();

    $this->postJson('/api/v1/crp/run', ['periods' => ['202607', '202608', '202609']])
        ->assertOk()
        ->assertJsonPath('data.queued', true)
        ->assertJsonCount(3, 'data.periods');

    Queue::assertPushed(RunCrpJob::class, 3);
});

it('still accepts a single period for CRP', function () {
    Queue::fake();

    $this->postJson('/api/v1/crp/run', ['period' => '202607'])
        ->assertOk()
        ->assertJsonPath('data.period', '202607');

    Queue::assertPushed(RunCrpJob::class, 1);
});

it('rejects a malformed month', function () {
    $this->postJson('/api/v1/crp/run', ['periods' => ['2026-07']])->assertStatus(422);
    $this->postJson('/api/v1/crp/run', ['period' => '20267'])->assertStatus(422);
});

it('refuses an unbounded range', function () {
    $periods = collect(range(1, 20))->map(fn ($i) => sprintf('2026%02d', ($i % 12) + 1))->unique()->values()->all();

    // A run per month is cheap; a run per month for ten years is not.
    $this->postJson('/api/v1/crp/run', ['periods' => array_merge($periods, ['202701', '202702', '202703'])])
        ->assertStatus(422);
});

it('queues COGM for every month in the range', function () {
    Queue::fake();

    $this->postJson('/api/v1/cogm/run', ['periods' => ['202607', '202608']])
        ->assertOk()
        ->assertJsonCount(2, 'data.periods');

    Queue::assertPushed(GenerateCogmJob::class, 2);
});

it('books depreciation month by month and reports each', function () {
    $categ = DB::table('m_asset_categ')->value('id');
    $asset = DB::table('ast_main')->insertGetId([
        'code' => 'AST-MP-'.substr(uniqid(), -6), 'categ_id' => $categ, 'name' => 'Mesin uji multi periode',
        'acq_date' => '2024-01-01', 'acq_cost' => 120_000_000, 'useful_life' => 60,
        'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $r = $this->postJson('/api/v1/assets/depreciate', ['periods' => ['202601', '202602', '202603']])
        ->assertOk();

    expect($r->json('data.periods'))->toHaveCount(3)
        ->and($r->json('data.by_period.202601'))->toBeGreaterThan(0);

    // One row per month for that asset — not three rows on one month.
    expect(DB::table('ast_depre')->where('ast_id', $asset)->count())->toBe(3)
        ->and((float) DB::table('ast_depre')->where('ast_id', $asset)->value('amount'))->toBe(2_000_000.0);
});

it('is idempotent per month, so a re-run does not double-book', function () {
    $categ = DB::table('m_asset_categ')->value('id');
    $asset = DB::table('ast_main')->insertGetId([
        'code' => 'AST-MP-'.substr(uniqid(), -6), 'categ_id' => $categ, 'name' => 'Mesin uji ulang',
        'acq_date' => '2024-01-01', 'acq_cost' => 60_000_000, 'useful_life' => 60,
        'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->postJson('/api/v1/assets/depreciate', ['periods' => ['202601', '202602']])->assertOk();
    $this->postJson('/api/v1/assets/depreciate', ['periods' => ['202601', '202602']])->assertOk();

    expect(DB::table('ast_depre')->where('ast_id', $asset)->count())->toBe(2);
});
