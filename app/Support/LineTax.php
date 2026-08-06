<?php

namespace App\Support;

/**
 * Tax arithmetic for a sales line, kept in one place because the figures are
 * frozen onto the document: PPN is charged on the DPP (the tariff's "nilai
 * lain" factor — 11/12 for non-luxury under PMK 131/2024), while PPh is
 * withheld from the gross amount.
 *
 * Delegates to TaxEngine (LLD §4.3). Kept as a static convenience for callers
 * that already have resolved m_tax rows in hand.
 *
 * Mirrored by calcLine() in resources/js/features/sales/SoPage.jsx so the
 * operator sees the same numbers that get stored — change both together.
 */
class LineTax
{
    /**
     * @param  object|null  $ppnTax  m_tax row, or null
     * @param  object|null  $pphTax  m_tax row, or null
     * @return array{dpp: float, ppn_value: float, pph_value: float}
     */
    public static function compute(float $subtotal, ?object $ppnTax, ?object $pphTax): array
    {
        return TaxEngine::compute($subtotal, $ppnTax, $pphTax);
    }
}
