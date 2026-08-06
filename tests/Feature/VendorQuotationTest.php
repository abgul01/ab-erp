<?php

use App\Exceptions\BizException;
use App\Models\m_supplier_item;
use App\Models\prc_contract_main;
use App\Models\prc_quot_main;
use App\Support\MrpService;
use App\Support\VendorQuotationService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Penawaran vendor & kontrak (PRD §4.7).
 *
 * Sebelum ini harga di `m_supplier_item` muncul tanpa riwayat: tidak ada
 * penawaran yang bisa ditunjuk, tidak ada pembanding, tidak ada masa berlaku.
 * Yang diuji di sini adalah bahwa jaraknya benar-benar tertutup — memilih satu
 * penawaran mengubah harga yang dipakai MRP, dan kontrak yang berjalan tidak
 * bisa ditimpa penawaran lepas.
 */
function quotFor(int $venId, int $itemId, array $line = [], array $head = []): prc_quot_main
{
    $quot = prc_quot_main::create(array_merge([
        'code' => 'QT-'.substr(uniqid(), -8),
        'date' => now()->toDateString(),
        'ven_id' => $venId,
        'valid_from' => now()->subDay()->toDateString(),
        'valid_to' => now()->addMonths(3)->toDateString(),
        'status' => 'RECEIVED',
        'user_id' => admin()->id,
    ], $head));

    $quot->detail()->create(array_merge([
        'item_id' => $itemId,
        'price' => 500000,
        'moq' => 10,
        'order_lot' => 5,
        'lead_time_days' => 30,
    ], $line));

    return $quot->fresh()->load('detail');
}

/** Dua vendor berbeda dari master kontak. */
function twoVendors(): array
{
    $ids = DB::table('m_contacts')->orderBy('id')->limit(2)->pluck('id');

    return [(int) $ids[0], (int) $ids[1]];
}

it('menjadikan penawaran terpilih sebagai syarat beli, lengkap dengan asal-usulnya', function () {
    [$venA] = twoVendors();
    $f = mrpFixture(['plan' => 10]);
    $quot = quotFor($venA, $f['rm'], ['price' => 777000, 'lead_time_days' => 45, 'moq' => 25]);

    $terms = app(VendorQuotationService::class)->select($quot->detail->first(), admin()->id);

    expect($terms->price)->toBe(777000.0)
        ->and($terms->lead_time_days)->toBe(45)
        ->and($terms->moq)->toBe(25)
        ->and($terms->priority)->toBe(1)
        // Inilah yang menjawab "harga ini dari mana".
        ->and($terms->quot_det_id)->toBe($quot->detail->first()->id);

    // Dan MRP langsung memakainya.
    $mrpTerms = app(MrpService::class)->supplierTerms($f['rm']);
    expect($mrpTerms['price'])->toBe(777000.0)
        ->and($mrpTerms['source'])->toBe('SUPPLIER');
});

it('menurunkan vendor lain jadi cadangan, bukan menghapusnya', function () {
    [$venA, $venB] = twoVendors();
    $f = mrpFixture(['plan' => 10]);

    app(VendorQuotationService::class)->select(quotFor($venA, $f['rm'], ['price' => 600000])->detail->first(), admin()->id);
    app(VendorQuotationService::class)->select(quotFor($venB, $f['rm'], ['price' => 550000])->detail->first(), admin()->id);

    $a = m_supplier_item::where('item_id', $f['rm'])->where('ven_id', $venA)->first();
    $b = m_supplier_item::where('item_id', $f['rm'])->where('ven_id', $venB)->first();

    // Yang kalah tetap ada — ia cadangan yang sah bila yang menang kehabisan stok.
    expect($b->priority)->toBe(1)
        ->and($a->priority)->toBe(2)
        ->and($a->active)->toBeTrue();
});

it('menolak penawaran yang sudah lewat masa berlaku', function () {
    [$venA] = twoVendors();
    $f = mrpFixture(['plan' => 10]);
    $quot = quotFor($venA, $f['rm'], [], ['valid_to' => now()->subDay()->toDateString()]);

    expect(fn () => app(VendorQuotationService::class)->select($quot->detail->first(), admin()->id))
        ->toThrow(BizException::class);
});

it('menampilkan perbandingan dengan penanda termurah dan tercepat', function () {
    [$venA, $venB] = twoVendors();
    $f = mrpFixture(['plan' => 10]);

    quotFor($venA, $f['rm'], ['price' => 500000, 'lead_time_days' => 45]);   // murah, lambat
    quotFor($venB, $f['rm'], ['price' => 620000, 'lead_time_days' => 7]);    // mahal, cepat

    $rows = app(VendorQuotationService::class)->comparison($f['rm']);
    $quotes = collect($rows[0]['quotes']);

    // Termurah belum tentu terbaik — keduanya ditandai supaya pembeli menimbang.
    expect($quotes->firstWhere('is_cheapest', true)['price'])->toBe(500000.0)
        ->and($quotes->firstWhere('is_fastest', true)['lead_time_days'])->toBe(7);
});

it('mengunci harga selama kontrak berjalan', function () {
    [$venA, $venB] = twoVendors();
    $f = mrpFixture(['plan' => 10]);

    $contract = prc_contract_main::create([
        'code' => 'CTR-'.substr(uniqid(), -8),
        'date' => now()->toDateString(),
        'ven_id' => $venA,
        'valid_from' => now()->subDay()->toDateString(),
        'valid_to' => now()->addYear()->toDateString(),
        'status' => 'DRAFT',
        'user_id' => admin()->id,
    ]);
    $contract->detail()->create(['item_id' => $f['rm'], 'price' => 480000, 'moq' => 50, 'lead_time_days' => 21]);

    app(VendorQuotationService::class)->activateContract($contract->fresh(), admin()->id);

    $terms = m_supplier_item::where('item_id', $f['rm'])->where('ven_id', $venA)->first();
    expect($terms->price)->toBe(480000.0)
        ->and($terms->contract_id)->toBe($contract->id);

    // Penawaran lepas yang lebih murah sekalipun tidak boleh menggantikannya —
    // itulah gunanya kontrak ditandatangani.
    $quot = quotFor($venB, $f['rm'], ['price' => 400000]);
    expect(fn () => app(VendorQuotationService::class)->select($quot->detail->first(), admin()->id))
        ->toThrow(BizException::class);
});

it('menolak dua kontrak berjalan atas material yang sama', function () {
    [$venA, $venB] = twoVendors();
    $f = mrpFixture(['plan' => 10]);

    foreach ([$venA, $venB] as $i => $ven) {
        $c = prc_contract_main::create([
            'code' => 'CTR-'.substr(uniqid(), -8), 'date' => now()->toDateString(), 'ven_id' => $ven,
            'valid_from' => now()->toDateString(), 'valid_to' => now()->addMonths(6)->toDateString(),
            'status' => 'DRAFT', 'user_id' => admin()->id,
        ]);
        $c->detail()->create(['item_id' => $f['rm'], 'price' => 500000]);

        if ($i === 0) {
            app(VendorQuotationService::class)->activateContract($c->fresh(), admin()->id);

            continue;
        }

        // Harga mana yang berlaku menjadi pertanyaan tanpa jawaban.
        expect(fn () => app(VendorQuotationService::class)->activateContract($c->fresh(), admin()->id))
            ->toThrow(BizException::class);
    }
});

it('melepas ikatan kontrak yang masa berlakunya habis, tanpa menghapus harganya', function () {
    [$venA] = twoVendors();
    $f = mrpFixture(['plan' => 10]);

    $c = prc_contract_main::create([
        'code' => 'CTR-'.substr(uniqid(), -8), 'date' => now()->subYear()->toDateString(), 'ven_id' => $venA,
        'valid_from' => now()->subYear()->toDateString(), 'valid_to' => now()->subDay()->toDateString(),
        'status' => 'DRAFT', 'user_id' => admin()->id,
    ]);
    $c->detail()->create(['item_id' => $f['rm'], 'price' => 490000]);
    app(VendorQuotationService::class)->activateContract($c->fresh(), admin()->id);

    $closed = app(VendorQuotationService::class)->expireContracts();

    $terms = m_supplier_item::where('item_id', $f['rm'])->where('ven_id', $venA)->first();

    // Harga terakhir tetap yang paling masuk akal dipakai sampai ada yang baru.
    expect($closed)->toBe(1)
        ->and($c->fresh()->status)->toBe('EXPIRED')
        ->and($terms->price)->toBe(490000.0)
        ->and($terms->contract_id)->toBeNull();
});

it('mengunci penawaran yang sudah dipilih dari perubahan', function () {
    Sanctum::actingAs(admin());
    [$venA] = twoVendors();
    $f = mrpFixture(['plan' => 10]);
    $quot = quotFor($venA, $f['rm']);

    app(VendorQuotationService::class)->select($quot->detail->first(), admin()->id);

    $this->putJson("/api/v1/quotations/{$quot->id}", [
        'date' => now()->toDateString(), 'ven_id' => $venA,
        'lines' => [['item_id' => $f['rm'], 'price' => 1]],
    ])->assertStatus(423);
});

it('menyajikan penawaran, perbandingan, dan kontrak lewat HTTP', function () {
    Sanctum::actingAs(admin());
    [$venA] = twoVendors();
    $f = mrpFixture(['plan' => 10]);

    $this->postJson('/api/v1/quotations', [
        'date' => now()->toDateString(),
        'ven_id' => $venA,
        'valid_to' => now()->addMonth()->toDateString(),
        'lines' => [['item_id' => $f['rm'], 'price' => 510000, 'moq' => 10, 'lead_time_days' => 20]],
    ])->assertCreated();

    $this->getJson('/api/v1/quotations')->assertOk();
    $this->getJson("/api/v1/quotations/comparison?item_id={$f['rm']}")->assertOk();
    $this->getJson('/api/v1/contracts')->assertOk();
});
