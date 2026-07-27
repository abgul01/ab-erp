<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\acc_period;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

/**
 * Accounting periods. A period must be OPEN to post journals; closing it locks
 * further posting (JournalEngine enforces this). Periods auto-open on first use.
 */
class PeriodController extends Controller
{
    public function index(Request $request)
    {
        return ApiResponse::paginated(acc_period::orderByDesc('period')->paginate(min(max((int) $request->query('per_page', 60), 1), 500)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{6}$/', 'unique:acc_period,period'],
            'status' => ['nullable', 'in:OPEN,CLOSED,LOCKED'],
        ]);
        $row = acc_period::create(['period' => $data['period'], 'status' => $data['status'] ?? 'OPEN']);
        AuditLogger::record($request, "Create period {$row->period}");

        return ApiResponse::item($row, 201);
    }

    /** Toggle open/close a period. */
    public function setStatus(Request $request, int $id)
    {
        $data = $request->validate(['status' => ['required', 'in:OPEN,CLOSED,LOCKED']]);
        $row = acc_period::findOrFail($id);
        if ($row->status === 'LOCKED') {
            throw BizException::make('PERIOD_LOCKED', 'Periode terkunci permanen tidak dapat diubah.');
        }
        $row->update(['status' => $data['status']]);
        AuditLogger::record($request, "Set period {$row->period} → {$data['status']}");

        return ApiResponse::item($row);
    }
}
