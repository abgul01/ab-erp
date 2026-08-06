<?php

use App\Exceptions\BizException;
use App\Models\m_whs_item;
use App\Models\whs_inc_main;
use App\Models\whs_out_main;
use App\Models\whs_ret_main;
use App\Models\whs_tool_unit;
use App\Support\WhsPostingService;
use App\Support\WhsStockService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Gudang WHS: tiga jenis barang yang perlakuannya memang berbeda.
 *
 * Sparepart dan barang habis pakai keluar sekali dan jadi beban. Alat dipinjam
 * dan harus kembali, jadi ia tidak boleh hilang dari neraca hanya karena dibawa
 * ke lantai produksi — dan tiap unitnya harus bisa ditelusuri sampai ke orangnya.
 */
function whsItem(string $type, array $attrs = []): m_whs_item
{
    return m_whs_item::create(array_merge([
        'code' => strtoupper(substr($type, 0, 2)).'-'.substr(uniqid(), -6),
        'name' => "Uji {$type}",
        'whs_type' => $type,
        'uom_id' => DB::table('m_uom')->value('id'),
        'min_stock' => 0,
        'standard_cost' => 100000,
        'active' => 1,
    ], $attrs));
}

/** Terima sejumlah barang dan langsung post — titik awal hampir semua tes di sini. */
function whsReceive(m_whs_item $item, int $qty, float $cost = 100000): whs_inc_main
{
    $inc = whs_inc_main::create([
        'code' => 'WIN-'.substr(uniqid(), -8),
        'date' => now()->toDateString(),
        'status' => 'DRAFT',
        'user_id' => admin()->id,
    ]);
    $inc->detail()->create(['item_id' => $item->id, 'qty' => $qty, 'unit_cost' => $cost]);

    return app(WhsPostingService::class)->postIncoming($inc, admin()->id);
}

function whsIssue(m_whs_item $item, int $qty, string $receiver = 'Teknisi Uji'): whs_out_main
{
    $out = whs_out_main::create([
        'code' => 'WOU-'.substr(uniqid(), -8),
        'date' => now()->toDateString(),
        'receiver' => $receiver,
        'cost_center' => 'MAINTENANCE',
        'status' => 'DRAFT',
        'user_id' => admin()->id,
    ]);
    $out->detail()->create(['item_id' => $item->id, 'qty' => $qty]);

    return app(WhsPostingService::class)->postOutgoing($out, admin()->id);
}

it('menambah stok hanya setelah penerimaan di-post', function () {
    $item = whsItem('CONSUMABLE');
    $stock = app(WhsStockService::class);

    $inc = whs_inc_main::create([
        'code' => 'WIN-'.substr(uniqid(), -8), 'date' => now()->toDateString(),
        'status' => 'DRAFT', 'user_id' => admin()->id,
    ]);
    $inc->detail()->create(['item_id' => $item->id, 'qty' => 50, 'unit_cost' => 20000]);

    // Dokumen draft tidak boleh menggerakkan apa pun.
    expect($stock->stock($item->id))->toBe(0);

    app(WhsPostingService::class)->postIncoming($inc, admin()->id);

    expect($stock->stock($item->id))->toBe(50)
        ->and($stock->unitCost($item->id))->toBe(20000.0);
});

it('menolak pengeluaran melebihi stok', function () {
    $item = whsItem('PART');
    whsReceive($item, 5);

    expect(fn () => whsIssue($item, 6))->toThrow(BizException::class);

    // Dan tidak ada yang setengah jadi: stoknya utuh.
    expect(app(WhsStockService::class)->stock($item->id))->toBe(5);
});

it('memberi tiap alat yang diterima nomor unitnya sendiri', function () {
    $tool = whsItem('TOOL', ['code' => 'TL-UJI-'.substr(uniqid(), -5)]);
    whsReceive($tool, 3, 4000000);

    $units = whs_tool_unit::where('item_id', $tool->id)->get();

    // Jumlah saja tidak bisa menjawab "unit yang mana"; tiap unit punya baris.
    expect($units)->toHaveCount(3)
        ->and($units->pluck('status')->unique()->all())->toBe(['IN_STOCK'])
        ->and($units->pluck('code')->unique())->toHaveCount(3);
});

it('mencatat alat sebagai dipinjam, bukan sebagai beban', function () {
    $tool = whsItem('TOOL');
    whsReceive($tool, 2, 5000000);

    $out = whsIssue($tool, 1, 'Budi');

    $unit = whs_tool_unit::where('item_id', $tool->id)->where('status', 'ON_LOAN')->first();

    expect($unit)->not->toBeNull()
        ->and($unit->holder)->toBe('Budi')
        // Alat yang dipinjam masih milik perusahaan: tidak ada jurnal beban.
        ->and(DB::table('acc_journal_main')->where('ref_type', 'WHS_OUT')->where('ref_id', $out->id)->exists())->toBeFalse()
        // Stok gudang berkurang karena alatnya memang tidak ada di rak.
        ->and(app(WhsStockService::class)->stock($tool->id))->toBe(1);
});

it('membebankan sparepart begitu keluar gudang', function () {
    $part = whsItem('PART');
    whsReceive($part, 10, 250000);

    $out = whsIssue($part, 4);

    $jrn = DB::table('acc_journal_main')->where('ref_type', 'WHS_OUT')->where('ref_id', $out->id)->first();
    expect($jrn)->not->toBeNull();

    $lines = DB::table('acc_journal_det as d')->join('acc_coa as c', 'c.id', '=', 'd.coa_id')
        ->where('d.main_id', $jrn->id)->get(['c.code', 'd.debit', 'd.credit']);

    // Beban perlengkapan bertambah, persediaan berkurang, dan keduanya sama besar.
    expect((float) $lines->firstWhere('code', '6400')->debit)->toBe(1000000.0)
        ->and((float) $lines->firstWhere('code', '1340')->credit)->toBe(1000000.0);
});

it('mengembalikan alat utuh ke stok tanpa membebankan apa pun', function () {
    $tool = whsItem('TOOL');
    whsReceive($tool, 2, 3000000);
    whsIssue($tool, 1, 'Budi');

    $unit = whs_tool_unit::where('item_id', $tool->id)->where('status', 'ON_LOAN')->first();

    $ret = whs_ret_main::create([
        'code' => 'WRT-'.substr(uniqid(), -8), 'date' => now()->toDateString(),
        'returner' => 'Budi', 'status' => 'DRAFT', 'user_id' => admin()->id,
    ]);
    $ret->detail()->create(['tool_unit_id' => $unit->id, 'out_det_id' => $unit->out_det_id, 'condition' => 'GOOD']);

    app(WhsPostingService::class)->postReturn($ret, admin()->id);

    expect($unit->fresh()->status)->toBe('IN_STOCK')
        ->and($unit->fresh()->holder)->toBeNull()
        ->and(app(WhsStockService::class)->stock($tool->id))->toBe(2)
        ->and(DB::table('acc_journal_main')->where('ref_type', 'WHS_RET')->where('ref_id', $ret->id)->exists())->toBeFalse();
});

it('membebankan alat yang kembali rusak — di situlah nilainya benar-benar hilang', function () {
    $tool = whsItem('TOOL');
    whsReceive($tool, 2, 3000000);
    whsIssue($tool, 1, 'Budi');

    $unit = whs_tool_unit::where('item_id', $tool->id)->where('status', 'ON_LOAN')->first();

    $ret = whs_ret_main::create([
        'code' => 'WRT-'.substr(uniqid(), -8), 'date' => now()->toDateString(),
        'returner' => 'Budi', 'status' => 'DRAFT', 'user_id' => admin()->id,
    ]);
    $ret->detail()->create(['tool_unit_id' => $unit->id, 'out_det_id' => $unit->out_det_id, 'condition' => 'DAMAGED']);

    app(WhsPostingService::class)->postReturn($ret, admin()->id);

    $jrn = DB::table('acc_journal_main')->where('ref_type', 'WHS_RET')->where('ref_id', $ret->id)->first();
    $loss = DB::table('acc_journal_det as d')->join('acc_coa as c', 'c.id', '=', 'd.coa_id')
        ->where('d.main_id', $jrn->id)->where('c.code', '6410')->value('d.debit');

    expect($unit->fresh()->status)->toBe('DAMAGED')
        ->and((float) $loss)->toBe(3000000.0)
        // Alat rusak tidak kembali ke rak.
        ->and(app(WhsStockService::class)->stock($tool->id))->toBe(1);
});

it('menolak pengembalian alat yang tidak sedang dipinjam', function () {
    $tool = whsItem('TOOL');
    whsReceive($tool, 1, 1000000);
    $unit = whs_tool_unit::where('item_id', $tool->id)->first();

    $ret = whs_ret_main::create([
        'code' => 'WRT-'.substr(uniqid(), -8), 'date' => now()->toDateString(),
        'status' => 'DRAFT', 'user_id' => admin()->id,
    ]);
    $ret->detail()->create(['tool_unit_id' => $unit->id, 'condition' => 'GOOD']);

    expect(fn () => app(WhsPostingService::class)->postReturn($ret, admin()->id))
        ->toThrow(BizException::class);
});

it('tidak bisa mem-post dokumen yang sama dua kali', function () {
    $item = whsItem('CONSUMABLE');
    $inc = whsReceive($item, 10);

    expect(fn () => app(WhsPostingService::class)->postIncoming($inc, admin()->id))
        ->toThrow(BizException::class);

    expect(app(WhsStockService::class)->stock($item->id))->toBe(10);
});

it('menutup PO WHS begitu semua barisnya diterima penuh', function () {
    Sanctum::actingAs(admin());
    $item = whsItem('PART');

    $po = $this->postJson('/api/v1/whs-po', [
        'date' => now()->toDateString(),
        'ven_id' => DB::table('m_contacts')->value('id'),
        'lines' => [['item_id' => $item->id, 'qty' => 5, 'price' => 200000]],
    ])->assertCreated()->json('data');

    $this->postJson("/api/v1/whs-po/{$po['id']}/submit")->assertOk();
    $this->postJson("/api/v1/whs-po/{$po['id']}/approve")->assertOk()
        ->assertJsonPath('data.status', 'APPROVED');

    $inc = $this->postJson('/api/v1/whs-incoming', [
        'date' => now()->toDateString(),
        'po_id' => $po['id'],
        'lines' => [[
            'po_det_id' => $po['detail'][0]['id'], 'item_id' => $item->id,
            'qty' => 5, 'unit_cost' => 200000,
        ]],
    ])->assertCreated()->json('data');

    $this->postJson("/api/v1/whs-incoming/{$inc['id']}/post")->assertOk()
        ->assertJsonPath('data.status', 'POSTED');

    expect(DB::table('whs_po_main')->where('id', $po['id'])->value('status'))->toBe('CLOSE')
        ->and(app(WhsStockService::class)->stock($item->id))->toBe(5);
});

it('memberi tiap baris penerimaan sparepart satu serial batch', function () {
    $part = whsItem('PART');
    $consum = whsItem('CONSUMABLE');

    $inc = whs_inc_main::create([
        'code' => 'WIN-'.substr(uniqid(), -8), 'date' => now()->toDateString(),
        'status' => 'DRAFT', 'user_id' => admin()->id,
    ]);
    $inc->detail()->create(['item_id' => $part->id, 'qty' => 10, 'unit_cost' => 150000]);
    $inc->detail()->create(['item_id' => $consum->id, 'qty' => 40, 'unit_cost' => 25000]);

    // Serial baru terbit saat di-post — dokumen draft belum jadi barang.
    expect($inc->detail->pluck('serial_code')->filter())->toBeEmpty();

    app(WhsPostingService::class)->postIncoming($inc, admin()->id);

    $serials = $inc->fresh()->load('detail')->detail->pluck('serial_code');

    // Satu dokumen, dua variasi barang, dua serial — persis sebanyak barisnya.
    expect($serials->filter())->toHaveCount(2)
        ->and($serials->unique())->toHaveCount(2)
        ->and($serials[0])->toStartWith($part->code.'/');
});

it('tidak memberi serial batch pada alat — alat memakai nomor unit', function () {
    $tool = whsItem('TOOL');
    $inc = whsReceive($tool, 2, 1000000);

    expect($inc->detail->first()->serial_code)->toBeNull()
        ->and(whs_tool_unit::where('item_id', $tool->id)->count())->toBe(2);
});

it('mengambil batch tertua kalau petugas tidak memilih', function () {
    $part = whsItem('PART');
    whsReceive($part, 5, 100000);      // batch pertama
    whsReceive($part, 5, 120000);      // batch kedua

    $balances = app(WhsStockService::class)->serialBalances($part->id);
    $oldest = $balances[0]['serial_code'];

    $out = whsIssue($part, 3);

    expect($out->detail->first()->serial_code)->toBe($oldest)
        ->and(app(WhsStockService::class)->serialBalances($part->id)[0]['remaining'])->toBe(2);
});

it('menolak pengambilan yang melebihi isi satu batch, dan menyarankan memecah baris', function () {
    $part = whsItem('PART');
    whsReceive($part, 5, 100000);
    whsReceive($part, 5, 100000);

    // Stok totalnya 10, tapi tidak ada satu batch pun yang berisi 8. Mengambil
    // diam-diam dari dua batch akan membuat satu baris menunjuk dua asal barang.
    expect(fn () => whsIssue($part, 8))->toThrow(BizException::class);

    expect(app(WhsStockService::class)->stock($part->id))->toBe(10);
});

it('menolak serial yang bukan milik barang itu', function () {
    $a = whsItem('PART');
    $b = whsItem('PART');
    whsReceive($a, 5);
    $incB = whsReceive($b, 5);
    $serialB = $incB->detail->first()->serial_code;

    $out = whs_out_main::create([
        'code' => 'WOU-'.substr(uniqid(), -8), 'date' => now()->toDateString(),
        'status' => 'DRAFT', 'user_id' => admin()->id,
    ]);
    $out->detail()->create(['item_id' => $a->id, 'serial_code' => $serialB, 'qty' => 1]);

    expect(fn () => app(WhsPostingService::class)->postOutgoing($out, admin()->id))
        ->toThrow(BizException::class);
});

it('menautkan serial WHS ke catatan downtime mesin, dan menolak serial karangan', function () {
    $part = whsItem('PART');
    $inc = whsReceive($part, 10, 200000);
    $serial = $inc->detail->first()->serial_code;

    $resolved = app(WhsStockService::class)->resolveCodes([['serial_item' => $serial]], 'serial_item');

    // Inilah gunanya serial: catatan downtime menunjuk barang yang benar-benar
    // pernah diterima, bukan ketikan bebas.
    expect($resolved[0]['item_id'])->toBe($part->id)
        ->and($resolved[0]['kind'])->toBe('SERIAL');

    expect(fn () => app(WhsStockService::class)->resolveCodes([['serial_item' => 'ASAL-KETIK-123']], 'serial_item'))
        ->toThrow(BizException::class);
});

it('menerima nomor unit alat sebagai serial downtime juga', function () {
    $tool = whsItem('TOOL');
    whsReceive($tool, 1, 2000000);
    $unit = whs_tool_unit::where('item_id', $tool->id)->first();

    $resolved = app(WhsStockService::class)->resolveCodes([['serial_tool' => $unit->code]], 'serial_tool');

    expect($resolved[0]['kind'])->toBe('TOOL_UNIT')
        ->and($resolved[0]['item_id'])->toBe($tool->id);
});

it('menyajikan stok, nilai, dan daftar pinjaman lewat HTTP', function () {
    Sanctum::actingAs(admin());

    $this->getJson('/api/v1/whs-stock')->assertOk()
        ->assertJsonStructure(['data' => ['summary' => ['by_type', 'total_value', 'on_loan'], 'items']]);

    $this->getJson('/api/v1/whs-stock/on-loan')->assertOk();
    $this->getJson('/api/v1/whs-stock/serials')->assertOk();
    $this->getJson('/api/v1/whs-items?whs_type=TOOL')->assertOk();

    // Daftar kode untuk layar downtime, di kedua layar MES.
    $this->getJson('/api/v1/mes/cutting/whs-codes')->assertOk();
    $this->getJson('/api/v1/mes/processing/whs-codes')->assertOk();

    // Jenis barang yang tidak dikenal adalah salah ketik, bukan filter kosong.
    $this->getJson('/api/v1/whs-stock?whs_type=MESIN')->assertStatus(422);
});
