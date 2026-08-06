<?php

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * A closed period must reject postings from every module, not only from the
 * journal screen. The date on the document decides the period, so back-dating
 * into a closed month is the case worth pinning down.
 */
beforeEach(fn () => Sanctum::actingAs(admin()));

function closePeriod(string $period): void
{
    DB::table('acc_period')->updateOrInsert(['period' => $period], ['status' => 'CLOSED']);
}

it('refuses a journal dated into a closed period', function () {
    closePeriod('202603');

    $this->postJson('/api/v1/journals', [
        'date' => '2026-03-15',
        'jrn_type' => 'JV',
        'descrip' => 'uji period lock',
        'lines' => [],
    ])
        ->assertStatus(423)
        ->assertJsonPath('errors.0.code', 'PERIOD_LOCKED');
});

it('names the period it rejected so the user knows which month to reopen', function () {
    closePeriod('202604');

    $this->postJson('/api/v1/journals', ['date' => '2026-04-02', 'lines' => []])
        ->assertStatus(423)
        ->assertJsonFragment(['code' => 'PERIOD_LOCKED']);

    expect($this->postJson('/api/v1/journals', ['date' => '2026-04-02', 'lines' => []])->json('errors.0.message'))
        ->toContain('202604');
});

it('lets the same posting through once the period is open again', function () {
    DB::table('acc_period')->updateOrInsert(['period' => '202605'], ['status' => 'OPEN']);

    // Not a valid journal, but it must fail on its own merits (validation),
    // never on the period guard.
    $this->postJson('/api/v1/journals', ['date' => '2026-05-10', 'lines' => []])
        ->assertStatus(422);
});

it('guards stock movements too, not only accounting screens', function () {
    closePeriod('202602');

    $this->postJson('/api/v1/fg-transfer', [
        'date' => '2026-02-10', 'from_det_id' => 1, 'to_item_id' => 1, 'qty' => 1,
    ])->assertStatus(423);

    $this->postJson('/api/v1/sales-invoices', ['date' => '2026-02-10'])
        ->assertStatus(423);
});

it('treats a period with no row as open', function () {
    DB::table('acc_period')->where('period', '203012')->delete();

    $this->postJson('/api/v1/journals', ['date' => '2030-12-01', 'lines' => []])
        ->assertStatus(422);
});
