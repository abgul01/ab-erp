<?php

namespace App\Support;

use App\Models\m_holiday;
use App\Models\m_machine;
use App\Models\m_work_calendar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * When the plant actually runs.
 *
 * Everything that schedules or measures capacity has to agree on this, so the
 * answer lives in one place: which dates are worked, and how many productive
 * hours each machine has on them.
 *
 * A month with no calendar rows falls back to "weekdays, two shifts". That
 * keeps a fresh install and the existing data working, but it is a default and
 * not the truth — holidays only become visible once the calendar is filled in.
 *
 * PRD §4.2 (Working Calendar), §4.5 (CRP)
 */
class WorkCalendarService
{
    /** Two shifts of eight hours, the plant's normal day. */
    public const DEFAULT_HOURS = 16.0;

    /**
     * Working dates in a period with their productive hours.
     *
     * Deliberately not memoised: other code writes to this calendar, and a
     * cached copy inside a long-running request would keep answering with the
     * old month after a holiday was added. It is one indexed lookup of at most
     * 31 rows — cheap enough that stale answers are not worth the risk.
     *
     * @return array<string, float> `Y-m-d` => hours
     */
    public function daysIn(string $period): array
    {
        return $this->load($period);
    }

    /** Just the dates, for schedulers that only need the sequence. */
    public function workingDates(string $period): array
    {
        return array_keys($this->daysIn($period));
    }

    /** Whether the plant runs on a given date. */
    public function isWorkingDay(string $date): bool
    {
        $period = str_replace('-', '', substr($date, 0, 7));

        return array_key_exists($date, $this->daysIn($period));
    }

    /**
     * Capacity of one machine over a period, in hours.
     *
     * A machine with its own daily_hours uses that on every working day —
     * a single-shift press does not gain hours because the plant runs two.
     * Otherwise it follows the plant's hours for each day, so a half-day
     * before a holiday counts as a half day.
     */
    public function machineHours(int $machineId, string $period): float
    {
        $days = $this->daysIn($period);
        if (! $days) {
            return 0.0;
        }

        $own = (float) (m_machine::where('id', $machineId)->value('daily_hours') ?? 0);

        return $own > 0
            ? round($own * count($days), 2)
            : round(array_sum($days), 2);
    }

    /** Plant capacity for a period, used where no machine is named. */
    public function plantHours(string $period): float
    {
        return round(array_sum($this->daysIn($period)), 2);
    }

    /**
     * Fill a period from a template, so maintaining the calendar is picking
     * holidays rather than typing thirty rows.
     *
     * Holidays recorded in the master (m_holiday, active) are applied
     * automatically — that is the point of having a master. A holiday that
     * leaves the plant running (is_working) sets a shorter day when it has
     * hours of its own, otherwise the day keeps its normal hours. Dates passed
     * directly in `$holidays` are shutdowns and win over the master, which
     * keeps the one-off call and the tests honest.
     *
     * @param  array<int, string>  $holidays  `Y-m-d` dates that are not worked
     * @return int rows written
     */
    public function generate(string $period, array $holidays = [], float $hours = self::DEFAULT_HOURS, bool $saturdayWorks = false): int
    {
        $start = Carbon::createFromFormat('Ymd', $period.'01')->startOfDay();
        $extra = array_flip($holidays);
        $written = 0;

        $master = m_holiday::whereRaw("DATE_FORMAT(date, '%Y%m') = ?", [$period])
            ->where('active', 1)
            ->get()
            ->keyBy(fn ($h) => $h->date->toDateString());

        for ($d = $start->copy(); $d->format('Ym') === $period; $d->addDay()) {
            $date = $d->toDateString();
            $isRestDay = $d->isSunday() || ($d->isSaturday() && ! $saturdayWorks);

            $isWorking = ! $isRestDay;
            $dayHours = $isWorking ? $hours : 0;
            $note = null;

            if (isset($extra[$date])) {
                $isWorking = false;
                $dayHours = 0;
                $note = 'Libur';
            } elseif ($masterHoliday = $master->get($date)) {
                $note = $masterHoliday->name;
                if ($masterHoliday->is_working) {
                    // Cuti bersama with a skeleton crew: the plant runs, shorter.
                    $dayHours = $isWorking && (float) $masterHoliday->hours > 0
                        ? (float) $masterHoliday->hours
                        : $hours;
                } else {
                    $isWorking = false;
                    $dayHours = 0;
                }
            }

            m_work_calendar::updateOrCreate(
                ['date' => $date],
                [
                    'is_working' => $isWorking,
                    'hours' => $dayHours,
                    'note' => $note,
                ]
            );
            $written++;
        }

        return $written;
    }

    /** @return array<string, float> */
    private function load(string $period): array
    {
        $rows = DB::table('m_work_calendar')
            ->whereRaw("DATE_FORMAT(date, '%Y%m') = ?", [$period])
            ->orderBy('date')
            ->get(['date', 'is_working', 'hours']);

        if ($rows->isEmpty()) {
            return $this->weekdayFallback($period);
        }

        $out = [];
        foreach ($rows as $r) {
            if ($r->is_working && (float) $r->hours > 0) {
                $out[substr((string) $r->date, 0, 10)] = (float) $r->hours;
            }
        }

        return $out;
    }

    /** Weekdays at two shifts — the assumption that used to be hardcoded. */
    private function weekdayFallback(string $period): array
    {
        $start = Carbon::createFromFormat('Ymd', $period.'01')->startOfDay();
        $out = [];

        for ($d = $start->copy(); $d->format('Ym') === $period; $d->addDay()) {
            if (! $d->isWeekend()) {
                $out[$d->toDateString()] = self::DEFAULT_HOURS;
            }
        }

        return $out;
    }
}
