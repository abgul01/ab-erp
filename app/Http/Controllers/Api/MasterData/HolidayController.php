<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Controller;
use App\Models\m_holiday;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

/**
 * Holiday master — national / cuti bersama / company holidays.
 *
 * The working calendar generator applies these when it fills a month, so a
 * planner records a holiday once here instead of remembering it every month.
 * Kept under the same permission as the calendar itself: this screen is the
 * "what to type" for that one.
 *
 * PRD §4.2
 */
class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $q = m_holiday::orderByDesc('date');

        if ($period = $request->query('period')) {
            $q->whereRaw("DATE_FORMAT(date, '%Y%m') = ?", [$period]);
        }
        if ($type = $request->query('type')) {
            $q->where('type', $type);
        }
        if ($request->has('active')) {
            $q->where('active', filter_var($request->query('active'), FILTER_VALIDATE_BOOL));
        }

        return ApiResponse::collection($q->limit(400)->get());
    }

    public function store(Request $request)
    {
        $row = m_holiday::create($this->validated($request));
        AuditLogger::record($request, "Tambah hari libur {$row->name} ({$row->date->toDateString()})");

        return ApiResponse::item($row, 201);
    }

    public function update(Request $request, int $id)
    {
        $row = m_holiday::findOrFail($id);
        $row->update($this->validated($request));
        AuditLogger::record($request, "Ubah hari libur {$row->name} ({$row->date->toDateString()})");

        return ApiResponse::item($row->fresh());
    }

    public function destroy(Request $request, int $id)
    {
        $row = m_holiday::findOrFail($id);
        AuditLogger::record($request, "Hapus hari libur {$row->name} ({$row->date->toDateString()})");
        $row->delete();

        return ApiResponse::empty();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'in:NASIONAL,CUTI_BERSAMA,PERUSAHAAN'],
            'is_working' => ['required', 'boolean'],
            'hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'active' => ['nullable', 'boolean'],
        ]);
    }
}
