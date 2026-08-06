<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Controller;
use App\Models\m_work_calendar;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\WorkCalendarService;
use Illuminate\Http\Request;

/**
 * Working calendar — which dates the plant runs and for how long.
 *
 * MPS schedules onto these dates and CRP measures capacity against these hours,
 * so a wrong calendar quietly makes both wrong.
 *
 * PRD §4.2
 */
class WorkCalendarController extends Controller
{
    public function __construct(private WorkCalendarService $svc) {}

    public function index(Request $request)
    {
        $q = m_work_calendar::orderBy('date');

        if ($period = $request->query('period')) {
            $q->whereRaw("DATE_FORMAT(date, '%Y%m') = ?", [$period]);
        }

        return ApiResponse::collection($q->limit(400)->get());
    }

    /** What the schedulers will actually see for a period, fallback included. */
    public function effective(Request $request)
    {
        $data = $request->validate(['period' => ['required', 'regex:/^\d{6}$/']]);
        $days = $this->svc->daysIn($data['period']);

        return ApiResponse::item([
            'period' => $data['period'],
            'working_days' => count($days),
            'total_hours' => round(array_sum($days), 2),
            // True when nobody has filled this month in and the weekday
            // assumption is standing in — worth surfacing, not hiding.
            'is_fallback' => ! m_work_calendar::whereRaw("DATE_FORMAT(date, '%Y%m') = ?", [$data['period']])->exists(),
            'days' => $days,
        ]);
    }

    /** Fill a month from a template; holidays are the part worth typing. */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{6}$/'],
            'hours' => ['nullable', 'numeric', 'min:1', 'max:24'],
            'saturday_works' => ['nullable', 'boolean'],
            'holidays' => ['nullable', 'array'],
            'holidays.*' => ['date'],
        ]);

        $written = $this->svc->generate(
            $data['period'],
            $data['holidays'] ?? [],
            (float) ($data['hours'] ?? WorkCalendarService::DEFAULT_HOURS),
            (bool) ($data['saturday_works'] ?? false),
        );

        AuditLogger::record($request, "Generate kalender kerja {$data['period']}: {$written} hari");

        return ApiResponse::item([
            'period' => $data['period'],
            'days_written' => $written,
            'working_days' => count($this->svc->daysIn($data['period'])),
        ]);
    }

    public function store(Request $request)
    {
        $row = m_work_calendar::updateOrCreate(
            ['date' => $request->validate(['date' => ['required', 'date']])['date']],
            $this->validated($request)
        );
        AuditLogger::record($request, "Set hari kerja {$row->date->toDateString()}");

        return ApiResponse::item($row, 201);
    }

    public function update(Request $request, int $id)
    {
        $row = m_work_calendar::findOrFail($id);
        $row->update($this->validated($request));
        AuditLogger::record($request, "Ubah hari kerja {$row->date->toDateString()}");

        return ApiResponse::item($row->fresh());
    }

    public function destroy(Request $request, int $id)
    {
        m_work_calendar::findOrFail($id)->delete();

        return ApiResponse::empty();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'is_working' => ['required', 'boolean'],
            'hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'note' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
