<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * The portal's whole purpose is isolation: a supplier sees its own documents
 * and nothing else, and cannot cross into the internal application even if it
 * holds permission rows.
 */
function vendorUser(int $venId): User
{
    return User::forceCreate([
        'username' => 'ven-'.substr(uniqid(), -8),
        'name' => 'Vendor User',
        'email' => uniqid().'@vendor.test',
        'identity' => 'VEN-'.substr(uniqid(), -5),
        'password' => bcrypt('password'),
        'ven_id' => $venId,
    ]);
}

it('shows a vendor only its own purchase orders', function () {
    $mine = (int) DB::table('m_contacts')->value('id');
    $other = (int) DB::table('m_contacts')->where('id', '!=', $mine)->value('id');

    $ownCode = 'PO-OWN-'.substr(uniqid(), -6);
    $otherCode = 'PO-OTH-'.substr(uniqid(), -6);
    foreach ([[$mine, $ownCode], [$other, $otherCode]] as [$ven, $code]) {
        DB::table('prc_po_main')->insert([
            'code' => $code, 'date' => now()->toDateString(), 'po_type' => 'LOCAL', 'source' => 'MANUAL',
            'ven_id' => $ven, 'user_id' => admin()->id, 'status' => 'APPROVED',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    Sanctum::actingAs(vendorUser($mine));
    $codes = collect($this->getJson('/api/v1/vendor/purchase-orders')->json('data.purchase'))->pluck('code');

    expect($codes)->toContain($ownCode)
        ->and($codes)->not->toContain($otherCode);
});

it('keeps a vendor account out of the internal application', function () {
    Sanctum::actingAs(vendorUser((int) DB::table('m_contacts')->value('id')));

    $this->getJson('/api/v1/journals')
        ->assertStatus(403)
        ->assertJsonPath('errors.0.code', 'VENDOR_SCOPE');
});

it('keeps a staff account out of the vendor portal', function () {
    Sanctum::actingAs(admin());

    $this->getJson('/api/v1/vendor/me')
        ->assertStatus(403)
        ->assertJsonPath('errors.0.code', 'NOT_VENDOR');
});

it('refuses to confirm a schedule belonging to another supplier', function () {
    $mine = (int) DB::table('m_contacts')->value('id');
    $other = (int) DB::table('m_contacts')->where('id', '!=', $mine)->value('id');

    $po = DB::table('prc_po_main')->insertGetId([
        'code' => 'PO-X-'.substr(uniqid(), -6), 'date' => now()->toDateString(), 'po_type' => 'LOCAL',
        'source' => 'MANUAL', 'ven_id' => $other, 'user_id' => admin()->id, 'status' => 'APPROVED',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $det = DB::table('prc_po_detail')->insertGetId([
        'main_id' => $po, 'item_id' => DB::table('m_item')->value('id'), 'qty' => 10, 'price' => 1000,
    ]);
    $sch = DB::table('prc_po_schedule')->insertGetId([
        'po_detail_id' => $det, 'plan_date' => now()->addWeek()->toDateString(), 'qty' => 10,
    ]);

    Sanctum::actingAs(vendorUser($mine));

    $this->postJson("/api/v1/vendor/schedules/{$sch}/confirm")
        ->assertStatus(403)
        ->assertJsonPath('errors.0.code', 'VEN_SCOPE');

    expect(DB::table('prc_po_schedule')->where('id', $sch)->value('confirmed_at'))->toBeNull();
});
