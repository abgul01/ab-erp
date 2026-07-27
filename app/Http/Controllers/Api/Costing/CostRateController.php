<?php

namespace App\Http\Controllers\Api\Costing;

use App\Http\Controllers\Controller;
use App\Models\cst_rate;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

/**
 * Cost rate master (cst_rate): labor / factory-overhead rate per hour, by period
 * and optionally per process. COGM uses the process-specific rate first, then a
 * process-agnostic one, then a built-in default.
 */
class CostRateController extends Controller
{
    public function index(Request $request)
    {
        $q = cst_rate::query();
        if ($p = $request->query('period')) {
            $q->where('period', $p);
        }
        $rows = $q->orderByDesc('period')->orderBy('rate_type')->orderBy('process_id')
            ->paginate(min(max((int) $request->query('per_page', 50), 1), 500));
        // enrich with process code for display
        $procs = \DB::table('m_process')->pluck('code', 'id');
        $rows->getCollection()->transform(function ($r) use ($procs) {
            $r->process_code = $r->process_id ? ($procs[$r->process_id] ?? "#{$r->process_id}") : 'SEMUA';

            return $r;
        });

        return ApiResponse::paginated($rows);
    }

    public function store(Request $request)
    {
        $data = $this->validateRow($request);
        $row = cst_rate::create($data);
        AuditLogger::record($request, "Create cost rate {$data['rate_type']} {$data['period']}");

        return ApiResponse::item($row, 201);
    }

    public function update(Request $request, int $id)
    {
        $row = cst_rate::findOrFail($id);
        $row->update($this->validateRow($request));
        AuditLogger::record($request, "Update cost rate #{$id}");

        return ApiResponse::item($row);
    }

    public function destroy(Request $request, int $id)
    {
        cst_rate::findOrFail($id)->delete();
        AuditLogger::record($request, "Delete cost rate #{$id}");

        return ApiResponse::item(['message' => 'Tarif dihapus.']);
    }

    private function validateRow(Request $request): array
    {
        return $request->validate([
            'period' => ['required', 'regex:/^\d{6}$/'],
            'rate_type' => ['required', 'in:LABOR,FOH'],
            'process_id' => ['nullable', 'integer', 'exists:m_process,id'],
            'rate_per_hour' => ['required', 'numeric', 'min:0'],
        ]);
    }
}
