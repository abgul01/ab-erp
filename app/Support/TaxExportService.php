<?php

namespace App\Support;

use App\Models\prc_inv_main;
use App\Models\sls_inv_main;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Statutory tax exports.
 *
 * - e-Faktur: output VAT on sales invoices, as the bulk XML Coretax imports.
 * - e-Bupot:  PPh 23 withheld on vendor invoices, as the CSV the DJP e-Bupot
 *             Unifikasi bulk upload expects.
 *
 * Both read what was already frozen on the document at posting time — nothing
 * is recomputed here, so a reprint years later still matches what was filed.
 *
 * PRD §6
 */
class TaxExportService
{
    /** Sales invoices in the period that carry a tax invoice number. */
    public function vatInvoices(string $from, string $to): Collection
    {
        return sls_inv_main::with(['cus', 'detail.item'])
            ->whereBetween('date', [$from, $to])
            ->whereNotNull('tax_inv_no')
            ->where('status', '!=', 'CANCELLED')
            ->orderBy('date')
            ->get();
    }

    /**
     * Coretax bulk tax-invoice XML.
     *
     * The taxable base is the frozen DPP; where PMK 131/2024 "DPP Nilai Lain"
     * applies the document also stores it, and that is what carries the 12%
     * rate while the ordinary DPP stays the contractual amount.
     */
    public function efakturXml(string $from, string $to): string
    {
        $xml = new \SimpleXMLElement('<TaxInvoiceBulk/>');
        $xml->addChild('TIN', $this->esc(config('tax.npwp')));
        $list = $xml->addChild('ListOfTaxInvoice');

        foreach ($this->vatInvoices($from, $to) as $inv) {
            $node = $list->addChild('TaxInvoice');
            $node->addChild('TaxInvoiceDate', substr((string) $inv->date, 0, 10));
            $node->addChild('TaxInvoiceOpt', 'Normal');
            $node->addChild('TrxCode', $this->esc(config('tax.trx_code')));
            $node->addChild('RefDesc', $this->esc($inv->code));
            $node->addChild('SellerIDTKU', $this->esc(config('tax.idtku')));
            $node->addChild('BuyerTin', $this->esc($inv->cus?->npwp ?: '000000000000000'));
            $node->addChild('BuyerDocument', $inv->cus?->npwp ? 'TIN' : 'Others');
            $node->addChild('BuyerCountry', 'IDN');
            $node->addChild('BuyerDocumentNumber', $this->esc($inv->cus?->nik));
            $node->addChild('BuyerName', $this->esc($inv->cus?->company_n));
            $node->addChild('BuyerAdress', $this->esc($inv->cus?->address));
            $node->addChild('BuyerEmail', $this->esc($inv->cus?->email));
            $node->addChild('BuyerIDTKU', $this->esc($inv->cus?->npwp));

            $goods = $node->addChild('ListOfGoodService');
            $base = (float) $inv->dpp;
            $otherBase = (float) ($inv->dpp_nilai_lain ?: 0);
            $vat = (float) $inv->vat;

            foreach ($inv->detail as $line) {
                $lineBase = (float) $line->amount;
                // Split the header-level "other base" and VAT across lines in
                // proportion to their value, so the file still foots to the
                // document total after rounding.
                $share = $base > 0 ? $lineBase / $base : 0;

                $g = $goods->addChild('GoodService');
                $g->addChild('Opt', 'A');
                $g->addChild('Code', '000000');
                $g->addChild('Name', $this->esc($line->item?->name ?: $line->item?->code));
                $g->addChild('Unit', 'UM.0033');
                $g->addChild('Price', $this->num($line->price));
                $g->addChild('Qty', $this->num($line->qty));
                $g->addChild('TotalDiscount', '0');
                $g->addChild('TaxBase', $this->num($lineBase));
                $g->addChild('OtherTaxBase', $this->num($otherBase * $share));
                $g->addChild('VATRate', $this->num($lineBase > 0 ? round($vat * $share / $lineBase * 100, 2) : 0));
                $g->addChild('VAT', $this->num($vat * $share));
                $g->addChild('STLGRate', '0');
                $g->addChild('STLG', '0');
            }
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        return $dom->saveXML();
    }

    /** Vendor invoices in the period that actually withheld PPh 23. */
    public function wht23Invoices(string $from, string $to): Collection
    {
        return prc_inv_main::with('ven')
            ->whereBetween('date', [$from, $to])
            ->where('wht23', '>', 0)
            ->where('status', '!=', 'CANCELLED')
            ->orderBy('date')
            ->get();
    }

    /**
     * e-Bupot Unifikasi bulk CSV for PPh 23.
     *
     * One row per withholding slip. A vendor without an NPWP is withheld at
     * double rate by law, which is already reflected in the stored amount —
     * the rate column reports what was actually applied.
     */
    public function ebupot23Csv(string $from, string $to): string
    {
        $rows = [[
            'Masa Pajak', 'Tahun Pajak', 'NPWP', 'NIK', 'Nama',
            'Kode Objek Pajak', 'DPP', 'Tarif', 'PPh Dipotong',
            'Nomor Dokumen', 'Tanggal Dokumen',
        ]];

        foreach ($this->wht23Invoices($from, $to) as $inv) {
            $date = Carbon::parse($inv->date);
            $dpp = (float) $inv->dpp;
            $wht = (float) $inv->wht23;

            $rows[] = [
                $date->month,
                $date->year,
                $inv->ven?->npwp,
                $inv->ven?->nik,
                $inv->ven?->company_n,
                $inv->wht23_code ?: config('tax.wht23_code'),
                $this->num($dpp),
                $this->num($inv->wht23_rate ?: ($dpp > 0 ? round($wht / $dpp * 100, 2) : 0)),
                $this->num($wht),
                $inv->inv_no ?: $inv->code,
                $date->toDateString(),
            ];
        }

        return collect($rows)
            ->map(fn ($r) => collect($r)->map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"')->implode(','))
            ->implode("\n");
    }

    private function num($v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

    /**
     * SimpleXMLElement::addChild() does NOT escape its value — an ampersand in
     * a company name ("PT Astra Heavy Equipment & Parts") is read as the start
     * of an entity reference and the document fails to parse. Escaping here is
     * what keeps a legitimate customer name from breaking the whole filing.
     */
    private function esc(?string $v): string
    {
        return htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
