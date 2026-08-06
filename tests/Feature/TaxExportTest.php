<?php

use App\Support\TaxExportService;
use Illuminate\Support\Facades\DB;

/**
 * The filings must foot to the documents. Nothing is recomputed at export time,
 * so what these check is selection (right period, right documents) and that the
 * per-line split still adds up to the header the accountant reconciled.
 */
function taxCustomer(): int
{
    $id = DB::table('m_contacts')->value('id');
    DB::table('m_contacts')->where('id', $id)->update(['npwp' => '0012345678901000']);

    return (int) $id;
}

function taxItem(): int
{
    return (int) DB::table('m_item')->insertGetId([
        'code' => 'TX-'.substr(uniqid(), -8), 'part_name' => 'Tax Item', 'type' => 'Pipe',
        'category_id' => DB::table('m_i_category')->value('id'), 'min_stock' => 0, 'max_stock' => 0, 'active' => 1,
    ]);
}

it('exports posted sales invoices as Coretax XML that foots to the document', function () {
    $item = taxItem();
    $code = 'SI-'.substr(uniqid(), -8);
    $inv = DB::table('sls_inv_main')->insertGetId([
        'code' => $code, 'date' => '2026-07-10', 'cus_id' => taxCustomer(),
        'dpp' => 1_000_000, 'dpp_nilai_lain' => 916_667, 'vat' => 110_000, 'total' => 1_110_000,
        'tax_inv_no' => '0100002512345678', 'user_id' => admin()->id, 'status' => 'POSTED',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('sls_inv_detail')->insert([
        ['main_id' => $inv, 'do_detail_id' => 0, 'item_id' => $item, 'qty' => 4, 'price' => 100_000, 'amount' => 400_000],
        ['main_id' => $inv, 'do_detail_id' => 0, 'item_id' => $item, 'qty' => 6, 'price' => 100_000, 'amount' => 600_000],
    ]);

    $xml = simplexml_load_string((new TaxExportService)->efakturXml('2026-07-01', '2026-07-31'));

    // Siblings share an element name, so they have to be walked rather than
    // keyed — iterator_to_array would collapse them onto one another.
    $node = null;
    foreach ($xml->ListOfTaxInvoice->TaxInvoice as $t) {
        if ((string) $t->RefDesc === $code) {
            $node = $t;
        }
    }

    $lines = [];
    foreach ($node->ListOfGoodService->GoodService as $g) {
        $lines[] = ['base' => (float) $g->TaxBase, 'vat' => (float) $g->VAT];
    }

    expect((string) $node->BuyerTin)->toBe('0012345678901000')
        ->and($lines)->toHaveCount(2)
        ->and(array_sum(array_column($lines, 'base')))->toBeMoney(1_000_000)
        ->and(array_sum(array_column($lines, 'vat')))->toBeMoney(110_000);
});

it('leaves out invoices with no tax invoice number', function () {
    DB::table('sls_inv_main')->insert([
        'code' => 'SI-'.substr(uniqid(), -8), 'date' => '2026-07-11', 'cus_id' => taxCustomer(),
        'dpp' => 500_000, 'vat' => 55_000, 'total' => 555_000, 'tax_inv_no' => null,
        'user_id' => admin()->id, 'status' => 'DRAFT', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $svc = new TaxExportService;

    expect($svc->vatInvoices('2026-07-11', '2026-07-11'))->toHaveCount(0);
});

it('exports one e-Bupot row per vendor invoice that withheld PPh 23', function () {
    $ven = DB::table('m_contacts')->value('id');
    DB::table('m_contacts')->where('id', $ven)->update(['npwp' => '0098765432109000']);

    DB::table('prc_inv_main')->insert([
        'code' => 'PI-'.substr(uniqid(), -8), 'date' => '2026-07-15', 'ven_id' => $ven,
        'inv_no' => 'INV/VEN/001', 'dpp' => 2_000_000, 'vat' => 220_000, 'wht23' => 40_000,
        'wht23_code' => '24-104-27', 'wht23_rate' => 2, 'total' => 2_180_000,
        'user_id' => admin()->id, 'status' => 'POSTED', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $csv = (new TaxExportService)->ebupot23Csv('2026-07-15', '2026-07-15');
    $lines = explode("\n", trim($csv));

    expect($lines[0])->toContain('Kode Objek Pajak')
        ->and($lines)->toHaveCount(2)
        ->and($lines[1])->toContain('0098765432109000')
        ->and($lines[1])->toContain('24-104-27')
        ->and($lines[1])->toContain('40000.00')
        ->and($lines[1])->toContain('INV/VEN/001');
});

it('ignores vendor invoices with nothing withheld', function () {
    DB::table('prc_inv_main')->insert([
        'code' => 'PI-'.substr(uniqid(), -8), 'date' => '2026-07-16', 'ven_id' => DB::table('m_contacts')->value('id'),
        'inv_no' => 'INV/VEN/002', 'dpp' => 1_000_000, 'vat' => 110_000, 'wht23' => 0, 'total' => 1_110_000,
        'user_id' => admin()->id, 'status' => 'POSTED', 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect((new TaxExportService)->wht23Invoices('2026-07-16', '2026-07-16'))->toHaveCount(0);
});
