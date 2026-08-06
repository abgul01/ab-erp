<?php

namespace Database\Seeders;

use Illuminate\Support\Carbon;

/**
 * Calendar helper for demo seeding.
 *
 * Everything is dated inside the CURRENT calendar month. That matters because
 * the planning screens open on the month you are in — MPP shows a three-month
 * matrix starting today, MPS a calendar of this month — so data seeded into
 * last month would leave both looking empty until the user went hunting for it.
 */
class DemoCalendar
{
    public static function month(): Carbon
    {
        return Carbon::now()->startOfMonth();
    }

    /** The month after the seeded one, used for forward-looking plans. */
    public static function nextPeriod(int $ahead = 1): string
    {
        return static::month()->copy()->addMonths($ahead)->format('Ym');
    }

    public static function date(int $day = 1, int $hour = 8, int $minute = 0): Carbon
    {
        $base = static::month();
        $daysInMonth = $base->daysInMonth;
        $actualDay = min(max(1, $day), $daysInMonth);

        return $base->copy()->addDays($actualDay - 1)->setHour($hour)->setMinute($minute)->setSecond(0);
    }

    public static function period(): string
    {
        return static::month()->format('Ym');
    }
}
