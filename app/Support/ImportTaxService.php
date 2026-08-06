<?php

namespace App\Support;

use App\Models\prc_cost_main;

/**
 * PPh 22 on imports.
 *
 * Base is the customs value: CIF plus import duty. Freight and insurance count
 * because customs values goods delivered, while handling charged inside the
 * country (EMKL, PIB fees) does not. The rate depends on whether the importer
 * holds an API — 2.5% with, 7.5% without — which is a property of the company,
 * not of the shipment, so it is configuration with a per-sheet override.
 *
 * The result is a prepaid tax credit and deliberately never enters the per-kilo
 * landed cost allocation.
 *
 * PRD §6
 */
class ImportTaxService
{
    /** Cost types that form the customs value alongside the goods themselves. */
    private const CUSTOMS_BASE_TYPES = ['FREIGHT', 'INSURANCE', 'DUTY', 'BM', 'CIF'];

    public const RATE_WITH_API = 2.5;

    public const RATE_WITHOUT_API = 7.5;

    public function rateFor(bool $hasApi): float
    {
        return $hasApi ? self::RATE_WITH_API : self::RATE_WITHOUT_API;
    }

    /**
     * @return array{base: float, rate: float, amount: float, goods: float, customs_add: float}
     */
    public function compute(prc_cost_main $sheet, float $goodsValueIdr): array
    {
        $customsAdd = (float) $sheet->detail
            ->filter(fn ($d) => in_array(strtoupper((string) $d->cost_type), self::CUSTOMS_BASE_TYPES, true))
            ->sum('amount_idr');

        $base = round($goodsValueIdr + $customsAdd, 2);
        $rate = $sheet->pph22_rate > 0 ? (float) $sheet->pph22_rate : $this->rateFor((bool) $sheet->has_api);

        return [
            'goods' => round($goodsValueIdr, 2),
            'customs_add' => round($customsAdd, 2),
            'base' => $base,
            'rate' => $rate,
            'amount' => round($base * $rate / 100, 2),
        ];
    }

    /** Cost lines that DO belong in landed cost — everything but the tax credit. */
    public function allocatableTotal(prc_cost_main $sheet): float
    {
        return (float) $sheet->detail->sum('amount_idr');
    }
}
