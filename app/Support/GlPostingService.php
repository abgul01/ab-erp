<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Maps source documents to double-entry journals and posts them through
 * JournalEngine (which enforces the period lock, balance and idempotency).
 *
 * Standard accounts (COA codes):
 *   1100 Kas/Bank · 1200 Piutang Usaha (AR) · 1210 PPN Masukan · 1300 Persediaan
 *   1500 Aset Tetap · 1590 Akum. Penyusutan · 2100 Utang Usaha (AP)
 *   2210 PPN Keluaran · 2220 Utang PPh · 4100 Penjualan · 5100 HPP · 6100 Beban Penyusutan
 */
class GlPostingService
{
    /**
     * Post every not-yet-journaled source document dated in the period:
     * sales invoices, AP invoices, and depreciation.
     *
     * @return array{sales_inv:int, ap_inv:int, depreciation:int}
     */
    public function generateForPeriod(string $period, int $userId): array
    {
        return [
            'sales_inv' => $this->postSalesInvoices($period, $userId),
            'ap_inv' => $this->postApInvoices($period, $userId),
            'depreciation' => $this->postDepreciation($period, $userId),
        ];
    }

    private function postSalesInvoices(string $period, int $userId): int
    {
        $rows = DB::table('sls_inv_main')->where('status', 'POSTED')
            ->whereRaw("DATE_FORMAT(date, '%Y%m') = ?", [$period])->get();
        $n = 0;
        foreach ($rows as $inv) {
            $before = $this->journalExists('SALES_INV', $inv->id);
            JournalEngine::post('SALES_INV', $inv->id, $inv->date, 'AR', [
                ['coa' => '1200', 'debit' => (float) $inv->total, 'memo' => "AR {$inv->code}"],
                ['coa' => '4100', 'credit' => (float) $inv->dpp, 'memo' => 'Penjualan'],
                ['coa' => '2210', 'credit' => (float) $inv->vat, 'memo' => 'PPN Keluaran'],
            ], "Sales Invoice {$inv->code}", $userId);
            $n += $before ? 0 : 1;
        }

        return $n;
    }

    private function postApInvoices(string $period, int $userId): int
    {
        $rows = DB::table('prc_inv_main')->where('status', 'POSTED')
            ->whereRaw("DATE_FORMAT(date, '%Y%m') = ?", [$period])->get();
        $n = 0;
        foreach ($rows as $inv) {
            $before = $this->journalExists('AP_INV', $inv->id);
            JournalEngine::post('AP_INV', $inv->id, $inv->date, 'AP', [
                ['coa' => '1300', 'debit' => (float) $inv->dpp, 'memo' => 'Persediaan'],
                ['coa' => '1210', 'debit' => (float) $inv->vat, 'memo' => 'PPN Masukan'],
                ['coa' => '2100', 'credit' => (float) $inv->total, 'memo' => "AP {$inv->code}"],
                ['coa' => '2220', 'credit' => (float) $inv->wht23, 'memo' => 'Utang PPh 23'],
            ], "AP Invoice {$inv->code}", $userId);
            $n += $before ? 0 : 1;
        }

        return $n;
    }

    private function postDepreciation(string $period, int $userId): int
    {
        $rows = DB::table('ast_depre as d')->join('ast_main as a', 'a.id', '=', 'd.ast_id')
            ->where('d.period', $period)->get(['d.id', 'd.amount', 'a.code']);
        $n = 0;
        foreach ($rows as $dep) {
            $before = $this->journalExists('DEPRECIATION', $dep->id);
            $jrn = JournalEngine::post('DEPRECIATION', $dep->id, $this->periodDate($period), 'DEP', [
                ['coa' => '6100', 'debit' => (float) $dep->amount, 'memo' => "Penyusutan {$dep->code}"],
                ['coa' => '1590', 'credit' => (float) $dep->amount, 'memo' => 'Akum. Penyusutan'],
            ], "Depresiasi {$dep->code} {$period}", $userId);
            if (! $before && $jrn) {
                DB::table('ast_depre')->where('id', $dep->id)->update(['journal_id' => $jrn->id]);
                $n++;
            }
        }

        return $n;
    }

    private function journalExists(string $refType, int $refId): bool
    {
        return DB::table('acc_journal_main')->where('ref_type', $refType)->where('ref_id', $refId)->exists();
    }

    private function periodDate(string $period): string
    {
        return substr($period, 0, 4) . '-' . substr($period, 4, 2) . '-28';
    }
}
