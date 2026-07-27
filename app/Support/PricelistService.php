<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Which customer price applies. Prices are renegotiated per period, so a line
 * only counts when its pricelist is ACTIVE, the document date falls inside
 * valid_from..valid_to, and the ordered qty meets min_qty. Overlapping windows
 * are resolved by the highest min_qty met (volume tier), then the newest window.
 */
class PricelistService
{
    public static function find(int $cusId, int $itemId, string $date, int $qty = 1): ?object
    {
        return DB::table('m_pricelist_det as d')
            ->join('m_pricelist_main as m', 'm.id', '=', 'd.main_id')
            ->leftJoin('m_currency as c', 'c.id', '=', 'd.currency_id')
            ->where('m.cus_id', $cusId)
            ->where('m.status', 'ACTIVE')
            ->where('d.item_id', $itemId)
            ->whereDate('d.valid_from', '<=', $date)
            ->whereDate('d.valid_to', '>=', $date)
            ->where(fn ($q) => $q->whereNull('d.min_qty')->orWhere('d.min_qty', '<=', $qty))
            ->orderByDesc('d.min_qty')->orderByDesc('d.valid_from')
            ->first([
                'd.id', 'd.price', 'd.currency_id', 'd.valid_from', 'd.valid_to', 'd.min_qty',
                'm.code as pricelist_code', DB::raw('c.code as currency'),
            ]);
    }
}
