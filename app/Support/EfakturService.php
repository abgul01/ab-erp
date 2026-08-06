<?php

namespace App\Support;

use App\Models\prc_inv_main;
use SimpleXMLElement;

/**
 * e-Faktur (SSPD/BSP) XML export for Coretax DJP integration.
 *
 * PRD §6: e-Faktur export via Coretax API.
 * Generates the XML envelope required by DJP's e-Faktur upload.
 *
 * LLD §4.3 (Tax compliance)
 */
class EfakturService
{
    /**
     * Generate SSPD XML for a posted invoice.
     *
     * @return array{uuid: string, xml: string}
     */
    public function generateSspd(prc_inv_main $invoice): array
    {
        $vendor = $invoice->ven;
        $npwp = $vendor?->npwp ?? $vendor?->contact?->npwp;

        if (! $npwp) {
            throw new \RuntimeException("NPWP vendor {$vendor->id} tidak terdaftar — e-Faktur tidak bisa dibuat.");
        }

        $uuid = $this->generateUuid();

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><SSPD></SSPD>');
        $xml->addAttribute('xmlns', 'http://efaktur.pajak.go.id/sspd');
        $xml->addAttribute('tglTerbit', $invoice->posted_at?->format('Y-m-d') ?? now()->format('Y-m-d'));
        $xml->addAttribute('nomorSeri', $uuid);

        $faktur = $xml->addChild('Faktur');
        $faktur->addChild('NPWPD', $this->cleanNpwp($npwp));
        $faktur->addChild('NamaWPD', $vendor->nama ?? $vendor->name ?? '');
        $faktur->addChild('MasaPajak', $invoice->period ?? now()->format('Ym'));
        $faktur->addChild('TahunPajak', substr($invoice->period ?? date('Ym'), 0, 4));
        $faktur->addChild('NomorFaktur', $invoice->invoice_number ?? $invoice->number);

        $this->appendInvoiceLines($faktur, $invoice);

        $this->appendSummary($faktur, $invoice);

        $xmlString = $xml->asXML();

        return [
            'uuid' => $uuid,
            'xml' => $xmlString,
        ];
    }

    /**
     * Store XML on the invoice record and mark it as exported.
     */
    public function export(prc_inv_main $invoice): array
    {
        $result = $this->generateSspd($invoice);

        $invoice->update([
            'efaktur_xml' => $result['xml'],
            'efaktur_uuid' => $result['uuid'],
            'efaktur_exported_at' => now(),
        ]);

        return $result;
    }

    private function appendInvoiceLines(SimpleXMLElement $faktur, prc_inv_main $invoice): void
    {
        $lines = $faktur->addChild('LineDetail');

        foreach ($invoice->detail as $detail) {
            $line = $lines->addChild('Line');
            $line->addChild('NomorUrut', (string) ($detail->line_number ?? 1));
            $line->addChild('NamaBarangJasa', $detail->description ?? '');
            $line->addChild('JumlahDPP', number_format($detail->dpp ?? $detail->qty * $detail->price, 0, ',', '.'));
            $line->addChild('PPN', number_format($detail->tax_amount ?? 0, 0, ',', '.'));
            $line->addChild('DPPNilaiLain', number_format($detail->other_amount ?? 0, 0, ',', '.'));
            $line->addChild('PPnDPPNilaiLain', number_format($detail->other_tax ?? 0, 0, ',', '.'));
        }
    }

    private function appendSummary(SimpleXMLElement $faktur, prc_inv_main $invoice): void
    {
        $summary = $faktur->addChild('Ringkasan');
        $summary->addChild('TotalDPP', number_format($invoice->total_dpp ?? $invoice->subtotal ?? 0, 0, ',', '.'));
        $summary->addChild('TotalPPN', number_format($invoice->tax_total ?? $invoice->total_tax ?? 0, 0, ',', '.'));
        $summary->addChild('TotalDPPNilaiLain', number_format($invoice->total_other_dpp ?? 0, 0, ',', '.'));
        $summary->addChild('TotalPPnDPPNilaiLain', number_format($invoice->total_other_tax ?? 0, 0, ',', '.'));
        $summary->addChild('TotalPKP', number_format($invoice->total_dpp ?? $invoice->subtotal ?? 0, 0, ',', '.'));
        $summary->addChild('TotalPPn', number_format($invoice->tax_total ?? $invoice->total_tax ?? 0, 0, ',', '.'));
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%012x',
            random_int(0, 0xFFFF),
            random_int(0, 0xFFFF),
            random_int(0, 0xFFFF),
            random_int(0, 0x0FFF) | 0x4000,
            random_int(0, 0x3FFF) | 0x8000,
            random_int(0, 0xFFFFFFFFFFFF),
        );
    }

    private function cleanNpwp(string $npwp): string
    {
        return preg_replace('/[^\d]/', '', $npwp);
    }
}
