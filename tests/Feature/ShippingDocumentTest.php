<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * The documents that travel with the goods: what is in each box, and what left
 * the gate on which truck. Both have to agree with the delivery they belong to.
 */

/** A delivery order in the demo data, with its lines. */
function anyDo(string $status = 'SHIPPED'): object
{
    $do = DB::table('sls_do_main')->where('status', $status)->orderByDesc('id')->first();

    if (! $do) {
        $do = DB::table('sls_do_main')->orderByDesc('id')->first();
        DB::table('sls_do_main')->where('id', $do->id)->update(['status' => $status]);
        $do->status = $status;
    }

    return $do;
}

it('packs a delivery into boxes and refuses to pack more than was sold', function () {
    Sanctum::actingAs(admin());
    $do = anyDo();
    $line = DB::table('sls_do_detail')->where('main_id', $do->id)->first();

    $pack = $this->postJson('/api/v1/packing-lists', [
        'date' => now()->toDateString(),
        'do_id' => $do->id,
        'lines' => [
            ['box_no' => 'BOX-1', 'do_detail_id' => $line->id, 'item_id' => $line->item_id,
                'qty' => (int) $line->qty, 'net_weight' => 120.5, 'gross_weight' => 128, 'dimension' => '1200x800x600'],
        ],
    ])->assertCreated()->json('data');

    expect($pack['detail'])->toHaveCount(1);

    // The line is fully boxed, so a second list has nothing left to pack.
    $this->postJson('/api/v1/packing-lists', [
        'date' => now()->toDateString(),
        'do_id' => $do->id,
        'lines' => [['box_no' => 'BOX-2', 'do_detail_id' => $line->id, 'item_id' => $line->item_id, 'qty' => 1]],
    ])->assertStatus(422);
});

it('reports how much of a delivery is still unpacked', function () {
    Sanctum::actingAs(admin());
    $do = anyDo();
    $line = DB::table('sls_do_detail')->where('main_id', $do->id)->first();
    $half = max(1, intdiv((int) $line->qty, 2));

    $this->postJson('/api/v1/packing-lists', [
        'date' => now()->toDateString(), 'do_id' => $do->id,
        'lines' => [['box_no' => 'BOX-1', 'do_detail_id' => $line->id, 'item_id' => $line->item_id, 'qty' => $half]],
    ])->assertCreated();

    $lines = $this->getJson("/api/v1/packing-lists/do-lines/{$do->id}")->assertOk()->json('data.lines');
    $row = collect($lines)->firstWhere('do_detail_id', $line->id);

    // Packing happens in more than one sitting; the screen must show the rest.
    expect($row['qty_packed'])->toBe($half)
        ->and($row['outstanding'])->toBe((int) $line->qty - $half);
});

it('locks a packing list once it is final', function () {
    Sanctum::actingAs(admin());
    $do = anyDo();
    $line = DB::table('sls_do_detail')->where('main_id', $do->id)->first();

    $pack = $this->postJson('/api/v1/packing-lists', [
        'date' => now()->toDateString(), 'do_id' => $do->id,
        'lines' => [['box_no' => 'BOX-1', 'do_detail_id' => $line->id, 'item_id' => $line->item_id, 'qty' => 1]],
    ])->assertCreated()->json('data');

    $this->postJson("/api/v1/packing-lists/{$pack['id']}/finalize")->assertOk()
        ->assertJsonPath('data.status', 'FINAL');

    // 423 Locked: the boxes are sealed and the document has gone with the goods.
    $this->putJson("/api/v1/packing-lists/{$pack['id']}", [
        'date' => now()->toDateString(), 'do_id' => $do->id,
        'lines' => [['box_no' => 'BOX-9', 'do_detail_id' => $line->id, 'item_id' => $line->item_id, 'qty' => 1]],
    ])->assertStatus(423);
});

it('loads deliveries onto one truck and will not load them onto a second', function () {
    Sanctum::actingAs(admin());
    $do = anyDo();

    $ship = $this->postJson('/api/v1/shipping-orders', [
        'date' => now()->toDateString(),
        'vehicle_no' => 'B 1234 XYZ',
        'driver' => 'Sutrisno',
        'destination' => 'Karawang',
        'do_ids' => [$do->id],
    ])->assertCreated()->json('data');

    expect($ship['detail'])->toHaveCount(1);

    // The same delivery cannot ride on two trucks — nobody would know where it is.
    $this->postJson('/api/v1/shipping-orders', [
        'date' => now()->toDateString(), 'vehicle_no' => 'B 5678 ABC', 'do_ids' => [$do->id],
    ])->assertStatus(422);
});

it('refuses to dispatch a delivery whose stock has not left the warehouse', function () {
    Sanctum::actingAs(admin());
    $do = anyDo('DRAFT');

    $ship = $this->postJson('/api/v1/shipping-orders', [
        'date' => now()->toDateString(), 'vehicle_no' => 'B 1111 AA', 'do_ids' => [$do->id],
    ])->assertCreated()->json('data');

    // A truck that leaves with goods the FG warehouse still thinks it holds is
    // how stock and reality part company.
    $this->postJson("/api/v1/shipping-orders/{$ship['id']}/dispatch")->assertStatus(422);

    expect(DB::table('sls_ship_main')->where('id', $ship['id'])->value('status'))->toBe('DRAFT');
});

it('marks the deliveries received when the truck arrives', function () {
    Sanctum::actingAs(admin());
    $do = anyDo('SHIPPED');

    $ship = $this->postJson('/api/v1/shipping-orders', [
        'date' => now()->toDateString(), 'vehicle_no' => 'B 2222 BB', 'do_ids' => [$do->id],
    ])->assertCreated()->json('data');

    $this->postJson("/api/v1/shipping-orders/{$ship['id']}/dispatch")->assertOk()
        ->assertJsonPath('data.status', 'DISPATCHED');

    $this->postJson("/api/v1/shipping-orders/{$ship['id']}/deliver")->assertOk()
        ->assertJsonPath('data.status', 'DELIVERED');

    expect(DB::table('sls_do_main')->where('id', $do->id)->value('status'))->toBe('RECEIVED');
});

it('needs a vehicle before anything can leave', function () {
    Sanctum::actingAs(admin());
    $do = anyDo('SHIPPED');

    $ship = $this->postJson('/api/v1/shipping-orders', [
        'date' => now()->toDateString(), 'do_ids' => [$do->id],
    ])->assertCreated()->json('data');

    $this->postJson("/api/v1/shipping-orders/{$ship['id']}/dispatch")->assertStatus(422);
});

it('hands the supplier a delivery-schedule template carrying the real order lines', function () {
    Sanctum::actingAs(admin());
    $po = DB::table('prc_po_main')->whereIn('status', ['OPEN', 'INPROGRESS'])->orderByDesc('id')->first();

    $csv = $this->get("/api/v1/po-schedules/template?po_id={$po->id}")->assertOk()->getContent();

    $lines = array_filter(explode("\n", trim($csv)));

    expect($lines[0])->toContain('po_detail_id')->toContain('plan_date')
        // Not an invented example row: the buyer's own order is in the file.
        ->and(count($lines))->toBeGreaterThan(1)
        ->and($csv)->toContain($po->code);
});

it('takes the filled-in schedule back as a CSV and skips lines with no date', function () {
    Sanctum::actingAs(admin());
    $line = DB::table('prc_po_detail as d')->join('prc_po_main as m', 'm.id', '=', 'd.main_id')
        ->whereIn('m.status', ['OPEN', 'INPROGRESS'])->value('d.id');

    $csv = "po_detail_id,po_code,item_code,part_name,plan_date,qty\n"
        ."{$line},PO-X,IT-1,\"Pipa\",2026-09-10,40\n"
        ."{$line},PO-X,IT-1,\"Pipa\",,60\n";       // supplier left this one undated

    $res = $this->post('/api/v1/po-schedules/import', [
        'file' => UploadedFile::fake()->createWithContent('jadwal.csv', $csv),
    ])->assertOk()->json('data');

    expect($res['imported'])->toBe(1);
    expect(DB::table('prc_po_schedule')->where('po_detail_id', $line)->where('plan_date', '2026-09-10')->value('qty'))->toBe(40);
});
