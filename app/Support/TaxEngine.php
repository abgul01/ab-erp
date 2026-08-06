<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Centralised tax calculation for Indonesian VAT (PPN) and withholding (PPh).
 *
 * Handles:
 * - DPP Nilai Lain (PMK 131/2024): non-luxury DPP = gross × 11/12, rate = 12%
 * - Luxury goods: DPP = gross (factor=1), rate = 12%
 * - PPh 22 (import) / PPh 23 (services) / PPh 4(2) (final)
 * - Effective tariff per date (future-proof for rate changes)
 * - SPT reporting breakdown
 *
 * LLD §4.3
 */
class TaxEngine
{
    /**
     * Compute full tax breakdown for a transaction line.
     *
     * @param  float  $subtotal  Gross line amount (before any tax)
     * @param  object|null  $ppnTax  m_tax row for PPN, or null
     * @param  object|null  $pphTax  m_tax row for PPh, or null
     * @param  string|null  $date  Effective date (for future rate resolution)
     * @return array{dpp:float, ppn_value:float, pph_value:float, ppn_rate:float, pph_rate:float, is_luxury:bool}
     */
    public function calcVat(
        float $subtotal,
        ?object $ppnTax,
        ?object $pphTax,
        ?string $date = null,
    ): array {
        $ppnRate = $ppnTax ? (float) $ppnTax->rate_pct : 0;
        $dppFactor = $ppnTax ? (float) ($ppnTax->dpp_factor ?? 1.0) : 1.0;
        $isLuxury = $ppnTax ? (bool) ($ppnTax->is_luxury ?? false) : false;
        $pphRate = $pphTax ? (float) $pphTax->rate_pct : 0;

        $dpp = $ppnTax ? round($subtotal * $dppFactor, 2) : $subtotal;

        return [
            'dpp' => $dpp,
            'ppn_value' => $ppnTax ? round($dpp * $ppnRate / 100, 2) : 0.0,
            'pph_value' => $pphTax ? round($subtotal * $pphRate / 100, 2) : 0.0,
            'ppn_rate' => $ppnRate,
            'pph_rate' => $pphRate,
            'is_luxury' => $isLuxury,
        ];
    }

    /**
     * Static convenience wrapper matching the old LineTax::compute signature
     * for backward compatibility. Returns only {dpp, ppn_value, pph_value}.
     */
    public static function compute(float $subtotal, ?object $ppnTax, ?object $pphTax): array
    {
        $r = (new self)->calcVat($subtotal, $ppnTax, $pphTax);

        return [
            'dpp' => $r['dpp'],
            'ppn_value' => $r['ppn_value'],
            'pph_value' => $r['pph_value'],
        ];
    }

    /**
     * Resolve the effective tax rate for a code on a given date.
     * Returns null when the code is not found.
     */
    public function resolveRate(string $code, ?string $date = null): ?object
    {
        $date = $date ?? date('Y-m-d');

        $row = DB::table('m_tax')
            ->where('code', $code)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $date))
            ->orderByDesc('effective_from')
            ->first();

        return $row;
    }

    /**
     * Map a purchase transaction type to its applicable PPh code.
     */
    public function pphCodeFor(string $poType, string $source): ?string
    {
        return match (true) {
            $poType === 'RM' && $source === 'IMPORT' => 'PPH-22',
            $poType === 'SERVICE' => 'PPH-23',
            $poType === 'ASSET' && $source === 'IMPORT' => 'PPH-22',
            default => null,
        };
    }

    /**
     * SPT export data for a document.
     */
    public function sptData(string $refType, int $refId): array
    {
        $main = match ($refType) {
            'AP_INVOICE' => DB::table('prc_inv_main')->where('id', $refId)->first(),
            'SALES_INVOICE' => DB::table('sls_inv_main')->where('id', $refId)->first(),
            default => null,
        };

        if (! $main) {
            return [];
        }

        $details = DB::table(($refType === 'AP_INVOICE' ? 'prc_inv_detail' : 'sls_inv_detail'))
            ->where('main_id', $refId)->get();

        $ppn = 0;
        $pph23 = 0;
        $dpp = 0;

        foreach ($details as $d) {
            $ppnTax = $d->tax_id ? DB::table('m_tax')->find($d->tax_id) : null;
            $pphTax = $d->pph_tax_id ? DB::table('m_tax')->find($d->pph_tax_id) : null;

            if ($ppnTax) {
                $computed = $this->calcVat((float) $d->subtotal, $ppnTax, null);
                $ppn += $computed['ppn_value'];
                $dpp += $computed['dpp'];
            }
            if ($pphTax) {
                $computed = $this->calcVat((float) $d->subtotal, null, $pphTax);
                $pph23 += $computed['pph_value'];
            }
        }

        return [
            'ref_type' => $refType,
            'ref_code' => $main->code ?? null,
            'date' => $main->date ?? $main->created_at,
            'vendor' => $main->ven_id ?? null,
            'dpp' => round($dpp, 2),
            'ppn' => round($ppn, 2),
            'pph_23' => round($pph23, 2),
        ];
    }
}
