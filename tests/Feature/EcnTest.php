<?php

use App\Exceptions\BizException;
use App\Models\eng_ecn_main;
use App\Support\EcnService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * An ECN is a dated, agreed instruction to change a master — so it must not be
 * able to change anything else, to take effect early, or to overwrite an edit
 * its approver never saw.
 */
function ecnFor(array $lines, array $opts = []): eng_ecn_main
{
    $f = mrpFixture(['plan' => 10]);

    $ecn = eng_ecn_main::create([
        'code' => 'ECN-TEST-'.substr(uniqid(), -6),
        'date' => now()->toDateString(),
        'change_type' => $opts['change_type'] ?? 'ITEM',
        'item_id' => $opts['item_id'] ?? $f['rm'],
        'reason' => 'Uji perubahan teknik',
        'effective_date' => $opts['effective_date'] ?? now()->toDateString(),
        'status' => $opts['status'] ?? 'APPROVED',
        'user_id' => admin()->id,
    ]);

    foreach ($lines as $l) {
        $ecn->detail()->create($l + ['action' => 'UPDATE']);
    }

    return $ecn->load('detail')->setAttribute('fixture', $f);
}

it('writes the agreed change into the master and bumps the revision', function () {
    $f = mrpFixture(['plan' => 10]);
    DB::table('m_item')->where('id', $f['rm'])->update(['weight' => 17.3]);

    $ecn = eng_ecn_main::create([
        'code' => 'ECN-'.substr(uniqid(), -8), 'date' => now()->toDateString(),
        'change_type' => 'ITEM', 'item_id' => $f['rm'], 'reason' => 'Ganti berat nominal',
        'effective_date' => now()->toDateString(), 'status' => 'APPROVED', 'user_id' => admin()->id,
    ]);
    $ecn->detail()->create([
        'action' => 'UPDATE', 'target_table' => 'm_item', 'target_id' => $f['rm'],
        'field' => 'weight', 'old_value' => '17.3', 'new_value' => '18.5',
    ]);

    app(EcnService::class)->apply($ecn->load('detail'), admin()->id);

    $item = DB::table('m_item')->where('id', $f['rm'])->first();

    expect((float) $item->weight)->toBe(18.5)
        // A drawing that says "rev 1" now matches something in the system.
        ->and((int) $item->rev)->toBe(1)
        ->and($ecn->fresh()->status)->toBe('APPLIED');
});

it('refuses to apply before the effective date', function () {
    $ecn = ecnFor(
        [['target_table' => 'm_item', 'target_id' => 1, 'field' => 'weight', 'old_value' => '1', 'new_value' => '2']],
        ['effective_date' => now()->addWeek()->toDateString()]
    );

    expect(fn () => app(EcnService::class)->apply($ecn, admin()->id))
        ->toThrow(BizException::class);
});

it('refuses to apply over an edit made after approval', function () {
    $f = mrpFixture(['plan' => 10]);
    DB::table('m_item')->where('id', $f['rm'])->update(['min_stock' => 100]);

    $ecn = eng_ecn_main::create([
        'code' => 'ECN-'.substr(uniqid(), -8), 'date' => now()->toDateString(),
        'change_type' => 'ITEM', 'item_id' => $f['rm'], 'reason' => 'Naikkan stok minimum',
        'effective_date' => now()->toDateString(), 'status' => 'APPROVED', 'user_id' => admin()->id,
    ]);
    $ecn->detail()->create([
        'action' => 'UPDATE', 'target_table' => 'm_item', 'target_id' => $f['rm'],
        'field' => 'min_stock', 'old_value' => '100', 'new_value' => '250',
    ]);

    // Someone edits the item after the approver signed it off.
    DB::table('m_item')->where('id', $f['rm'])->update(['min_stock' => 180]);

    expect(fn () => app(EcnService::class)->apply($ecn->load('detail'), admin()->id))
        ->toThrow(BizException::class);

    // And the edit survives untouched — silently replacing it is the failure.
    expect((int) DB::table('m_item')->where('id', $f['rm'])->value('min_stock'))->toBe(180);
});

it('refuses a field outside the whitelist', function () {
    expect(fn () => app(EcnService::class)->assertLineAllowed('ITEM', [
        'target_table' => 'm_item', 'target_id' => 1, 'field' => 'status',
    ]))->toThrow(BizException::class);

    // …and a table the notice's own type has no business touching.
    expect(fn () => app(EcnService::class)->assertLineAllowed('ITEM', [
        'target_table' => 'm_bom_det_rm', 'target_id' => 1, 'field' => 'length_use',
    ]))->toThrow(BizException::class);
});

it('changes a BOM line and bumps the BOM revision', function () {
    $f = mrpFixture(['plan' => 10]);
    $bomLine = DB::table('m_bom_det_rm as d')->join('m_bom as b', 'b.id', '=', 'd.id_prim')
        ->where('b.item_id', $f['fg'])->select('d.id')->value('d.id');

    $ecn = eng_ecn_main::create([
        'code' => 'ECN-'.substr(uniqid(), -8), 'date' => now()->toDateString(),
        'change_type' => 'BOM', 'item_id' => $f['fg'], 'reason' => 'Perpanjang potongan',
        'effective_date' => now()->toDateString(), 'status' => 'APPROVED', 'user_id' => admin()->id,
    ]);
    $ecn->detail()->create([
        'action' => 'UPDATE', 'target_table' => 'm_bom_det_rm', 'target_id' => $bomLine,
        'field' => 'length_use', 'old_value' => '600', 'new_value' => '650',
    ]);

    app(EcnService::class)->apply($ecn->load('detail'), admin()->id);

    expect((float) DB::table('m_bom_det_rm')->where('id', $bomLine)->value('length_use'))->toBe(650.0)
        ->and((int) DB::table('m_bom')->where('item_id', $f['fg'])->value('rev'))->toBe(1);
});

it('reports what a change would touch', function () {
    $f = mrpFixture(['plan' => 10]);

    $impact = app(EcnService::class)->impact($f['rm']);

    // The material is used by the product's BOM, and the approver should see it.
    expect(collect($impact['where_used'])->pluck('item_id'))->toContain($f['fg']);
});

it('runs the whole notice over HTTP: draft, submit, approve, apply', function () {
    Sanctum::actingAs(admin());
    $f = mrpFixture(['plan' => 10]);
    DB::table('m_item')->where('id', $f['rm'])->update(['lead_time_days' => 30]);

    $ecn = $this->postJson('/api/v1/ecn', [
        'date' => now()->toDateString(),
        'change_type' => 'ITEM',
        'item_id' => $f['rm'],
        'reason' => 'Pemasok baru, lead time lebih panjang',
        'effective_date' => now()->toDateString(),
        'lines' => [[
            'action' => 'UPDATE', 'target_table' => 'm_item', 'target_id' => $f['rm'],
            'field' => 'lead_time_days', 'new_value' => '45',
        ]],
    ])->assertCreated()->json('data');

    // Submitting records the value the approver is being shown.
    $this->postJson("/api/v1/ecn/{$ecn['id']}/submit")->assertOk()
        ->assertJsonPath('data.status', 'SUBMITTED')
        ->assertJsonPath('data.detail.0.old_value', '30');

    // Two levels of sign-off: engineering, then PPIC.
    $this->postJson("/api/v1/ecn/{$ecn['id']}/approve")->assertOk();
    $this->postJson("/api/v1/ecn/{$ecn['id']}/approve")->assertOk()
        ->assertJsonPath('data.status', 'APPROVED');

    // Approved is not applied — the master is still on the old figure.
    expect((int) DB::table('m_item')->where('id', $f['rm'])->value('lead_time_days'))->toBe(30);

    $this->postJson("/api/v1/ecn/{$ecn['id']}/apply")->assertOk()
        ->assertJsonPath('data.status', 'APPLIED');

    expect((int) DB::table('m_item')->where('id', $f['rm'])->value('lead_time_days'))->toBe(45);
});

it('rejects an ECN that names a column it may not write', function () {
    Sanctum::actingAs(admin());
    $f = mrpFixture(['plan' => 10]);

    $this->postJson('/api/v1/ecn', [
        'date' => now()->toDateString(), 'change_type' => 'ITEM', 'item_id' => $f['rm'],
        'reason' => 'Coba ubah kolom terlarang', 'effective_date' => now()->toDateString(),
        'lines' => [['target_table' => 'm_item', 'target_id' => $f['rm'], 'field' => 'code', 'new_value' => 'X']],
    ])->assertStatus(422);

    expect(DB::table('m_item')->where('id', $f['rm'])->value('code'))->not->toBe('X');
});
