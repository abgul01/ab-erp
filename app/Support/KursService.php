<?php

namespace App\Support;

use App\Exceptions\BizException;
use App\Models\m_rate;

/**
 * Exchange rates, including the weekly KMK rate the tax office publishes.
 *
 * Customs and VAT on an import must be converted at the KMK rate in force on
 * the document's date — not at today's rate and not at the bank rate — so the
 * lookup is always "the latest rate published on or before this date" rather
 * than an exact-date match. A rate published on Wednesday still governs Friday.
 *
 * PRD §6 (Kurs pajak)
 */
class KursService
{
    public const KMK = 'KMK';

    public const BANK = 'BANK';

    /** Rate in force on $date, or null when nothing has been published yet. */
    public function rateOn(int $currencyId, string $date, string $type = self::KMK): ?float
    {
        $rate = m_rate::where('currency_id', $currencyId)
            ->where('rate_type', $type)
            ->whereDate('valid_date', '<=', $date)
            ->orderByDesc('valid_date')
            ->orderByDesc('id')
            ->value('rate');

        return $rate === null ? null : (float) $rate;
    }

    /** Same lookup, but refuses to guess — used where a wrong rate is a tax error. */
    public function requireRateOn(int $currencyId, string $date, string $type = self::KMK): float
    {
        $rate = $this->rateOn($currencyId, $date, $type);

        if ($rate === null || $rate <= 0) {
            throw BizException::make(
                'KURS_MISSING',
                "Kurs {$type} untuk mata uang #{$currencyId} pada {$date} belum diinput.",
            );
        }

        return $rate;
    }

    /** Convert a foreign-currency amount to rupiah at the governing rate. */
    public function toIdr(float $amount, int $currencyId, string $date, string $type = self::KMK): float
    {
        return round($amount * $this->requireRateOn($currencyId, $date, $type), 2);
    }

    /** The rate table as of a date, for the screen that maintains it. */
    public function published(string $type = self::KMK, int $limit = 200): array
    {
        return m_rate::with('currency')
            ->where('rate_type', $type)
            ->orderByDesc('valid_date')
            ->limit($limit)
            ->get()
            ->all();
    }
}
