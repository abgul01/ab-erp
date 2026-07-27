<?php

namespace App\Support;

/**
 * Tax arithmetic for a sales line, kept in one place because the figures are
 * frozen onto the document: PPN is charged on the DPP (the tariff's "nilai
 * lain" factor — 11/12 for non-luxury under PMK 131/2024), while PPh is
 * withheld from the gross amount.
 *
 * Mirrored by calcLine() in resources/js/features/sales/SoPage.jsx so the
 * operator sees the same numbers that get stored — change both together.
 */
class LineTax
{
    /**
     * @param  object|null  $ppnTax  m_tax row, or null when the line carries no PPN
     * @param  object|null  $pphTax  m_tax row, or null when nothing is withheld
     * @return array{dpp: float, ppn_value: float, pph_value: float}
     */
    public static function compute(float $subtotal, ?object $ppnTax, ?object $pphTax): array
    {
        $dpp = $ppnTax ? round($subtotal * (float) $ppnTax->dpp_factor, 2) : $subtotal;

        return [
            'dpp' => $dpp,
            'ppn_value' => $ppnTax ? round($dpp * (float) $ppnTax->rate_pct / 100, 2) : 0.0,
            'pph_value' => $pphTax ? round($subtotal * (float) $pphTax->rate_pct / 100, 2) : 0.0,
        ];
    }
}
