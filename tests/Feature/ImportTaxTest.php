<?php

use App\Exceptions\BizException;
use App\Models\m_rate;
use App\Models\prc_cost_main;
use App\Support\ImportTaxService;
use App\Support\KursService;
use Illuminate\Support\Facades\DB;

/**
 * Import taxes hang on two things being right: the rate in force on the
 * document's date, and a customs base that includes freight and duty but not
 * charges incurred after the goods cleared.
 */
function kmk(string $date, float $rate, ?int $currencyId = null): int
{
    $cur = $currencyId ?: (int) DB::table('m_currency')->value('id');
    m_rate::create(['currency_id' => $cur, 'rate_type' => 'KMK', 'valid_date' => $date, 'rate' => $rate]);

    return $cur;
}

it('uses the last rate published on or before the document date', function () {
    $cur = (int) DB::table('m_currency')->insertGetId(['code' => 'TST', 'name' => 'Test Currency']);
    kmk('2026-03-04', 15000, $cur);
    kmk('2026-03-11', 15500, $cur);

    $svc = new KursService;

    expect($svc->rateOn($cur, '2026-03-14'))->toBe(15500.0)   // Wednesday's rate still governs Saturday
        ->and($svc->rateOn($cur, '2026-03-11'))->toBe(15500.0)
        ->and($svc->rateOn($cur, '2026-03-10'))->toBe(15000.0)
        ->and($svc->rateOn($cur, '2026-03-01'))->toBeNull();   // nothing published yet
});

it('refuses to guess a rate where a wrong one is a tax error', function () {
    $cur = (int) DB::table('m_currency')->insertGetId(['code' => 'TS2', 'name' => 'Test Currency 2']);

    expect(fn () => (new KursService)->requireRateOn($cur, '2026-01-01'))
        ->toThrow(BizException::class);
});

it('converts at the governing rate', function () {
    $cur = (int) DB::table('m_currency')->insertGetId(['code' => 'TS3', 'name' => 'Test Currency 3']);
    kmk('2026-05-06', 16000, $cur);

    expect((new KursService)->toIdr(1250, $cur, '2026-05-09'))->toBe(20_000_000.0);
});

/** Cost sheet with the given cost lines, not persisted beyond this test. */
function costSheet(array $lines, bool $hasApi = true): prc_cost_main
{
    $inv = DB::table('prc_inv_main')->insertGetId([
        'code' => 'PI-IMP-'.substr(uniqid(), -8), 'date' => '2026-06-10',
        'ven_id' => DB::table('m_contacts')->value('id'), 'inv_no' => 'INV-IMP-1',
        'dpp' => 100_000_000, 'vat' => 0, 'wht23' => 0, 'total' => 100_000_000,
        'user_id' => admin()->id, 'status' => 'POSTED', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $sheet = prc_cost_main::create([
        'code' => 'LC-'.substr(uniqid(), -8), 'date' => '2026-06-10',
        'inv_id' => $inv, 'po_id' => DB::table('prc_po_main')->value('id'),
        'alloc_basis' => 'WEIGHT', 'has_api' => $hasApi, 'status' => 'DRAFT', 'user_id' => admin()->id,
    ]);

    foreach ($lines as [$type, $amount]) {
        $sheet->detail()->create([
            'cost_type' => $type, 'amount' => $amount, 'rate' => 1, 'amount_idr' => $amount,
        ]);
    }

    return $sheet->load('detail');
}

it('builds the customs base from CIF plus duty and leaves local charges out', function () {
    $sheet = costSheet([
        ['FREIGHT', 5_000_000],
        ['INSURANCE', 1_000_000],
        ['DUTY', 4_000_000],
        ['EMKL', 3_000_000],       // incurred after clearance — not customs value
        ['PIB', 500_000],
    ]);

    $r = (new ImportTaxService)->compute($sheet, 100_000_000);

    expect($r['customs_add'])->toBeMoney(10_000_000)
        ->and($r['base'])->toBeMoney(110_000_000);
});

it('charges 2.5% with an API licence and 7.5% without', function () {
    $withApi = (new ImportTaxService)->compute(costSheet([['DUTY', 0]], true), 100_000_000);
    $without = (new ImportTaxService)->compute(costSheet([['DUTY', 0]], false), 100_000_000);

    expect($withApi['rate'])->toBe(2.5)
        ->and($withApi['amount'])->toBeMoney(2_500_000)
        ->and($without['rate'])->toBe(7.5)
        ->and($without['amount'])->toBeMoney(7_500_000);
});

it('keeps PPh 22 out of the amount allocated to inventory', function () {
    $sheet = costSheet([['FREIGHT', 5_000_000], ['DUTY', 5_000_000]]);
    $svc = new ImportTaxService;

    $pph22 = $svc->compute($sheet, 100_000_000);

    // Allocatable cost is the freight and duty only; the tax credit is separate.
    expect($svc->allocatableTotal($sheet))->toBeMoney(10_000_000)
        ->and($pph22['amount'])->toBeMoney(2_750_000);
});
