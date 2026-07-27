<?php

namespace App\Http\Controllers\Api\Costing;

use App\Http\Controllers\Controller;
use App\Models\ast_depre;
use App\Models\ast_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Fixed assets (ast_main) + straight-line depreciation. "Hitung Depresiasi"
 * posts one month of depreciation (acq_cost / useful_life) for every active
 * asset that is in service and not yet fully depreciated in the period.
 */
class AssetController extends Controller
{
    public function index(Request $request)
    {
        $q = ast_main::with('categ')->withSum('depre as depre_total', 'amount');
        if ($s = trim((string) $request->query('q', ''))) {
            $q->where(fn ($w) => $w->where('code', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"));
        }
        $rows = $q->orderByDesc('id')->paginate(min(max((int) $request->query('per_page', 20), 1), 200));
        $rows->getCollection()->transform(function ($a) {
            $a->depre_total = (float) ($a->depre_total ?? 0);
            $a->book_value = round((float) $a->acq_cost - $a->depre_total, 2);
            $a->monthly = (int) $a->useful_life > 0 ? round((float) $a->acq_cost / (int) $a->useful_life, 2) : 0;

            return $a;
        });

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        return ApiResponse::item(ast_main::with(['categ', 'depre'])->findOrFail($id));
    }

    public function store(Request $request)
    {
        $row = ast_main::create($this->validateRow($request));
        AuditLogger::record($request, "Create asset {$row->code}");

        return ApiResponse::item($row->load('categ'), 201);
    }

    public function update(Request $request, int $id)
    {
        $row = ast_main::findOrFail($id);
        $row->update($this->validateRow($request, $id));
        AuditLogger::record($request, "Update asset {$row->code}");

        return ApiResponse::item($row->load('categ'));
    }

    public function destroy(Request $request, int $id)
    {
        $row = ast_main::findOrFail($id);
        DB::transaction(function () use ($row, $request) {
            ast_depre::where('ast_id', $row->id)->delete();
            $row->delete();
            AuditLogger::record($request, "Delete asset {$row->code}");
        });

        return ApiResponse::item(['message' => 'Aset dihapus.']);
    }

    /** Post one month of straight-line depreciation for the period. */
    public function depreciate(Request $request)
    {
        $data = $request->validate(['period' => ['required', 'regex:/^\d{6}$/']]);
        $period = $data['period'];
        $periodEnd = Carbon::createFromFormat('Ym', $period)->endOfMonth();

        $assets = ast_main::where('status', 'ACTIVE')->get();
        $posted = DB::transaction(function () use ($assets, $period, $periodEnd, $request) {
            $count = 0;
            foreach ($assets as $a) {
                $life = (int) $a->useful_life;
                if ($life <= 0 || Carbon::parse($a->acq_date)->gt($periodEnd)) {
                    continue;   // not yet in service
                }
                $already = (int) ast_depre::where('ast_id', $a->id)->count();
                if ($already >= $life) {
                    continue;   // fully depreciated
                }
                $monthly = round((float) $a->acq_cost / $life, 2);
                ast_depre::updateOrCreate(['ast_id' => $a->id, 'period' => $period], ['amount' => $monthly]);
                $count++;
            }
            AuditLogger::record($request, "Hitung depresiasi {$period}: {$count} aset");

            return $count;
        });

        return ApiResponse::item(['period' => $period, 'posted' => $posted]);
    }

    private function validateRow(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('ast_main', 'code')->ignore($id)],
            'categ_id' => ['required', 'integer', 'exists:m_asset_categ,id'],
            'name' => ['required', 'string', 'max:150'],
            'acq_date' => ['required', 'date'],
            'acq_cost' => ['required', 'numeric', 'min:0'],
            'useful_life' => ['nullable', 'integer', 'min:1'],
            'machine_id' => ['nullable', 'integer', 'exists:m_machine,id'],
            'status' => ['nullable', 'in:ACTIVE,DISPOSED,TRANSFERRED'],
        ]);
        // default useful life from the category when not given
        if (empty($data['useful_life'])) {
            $data['useful_life'] = (int) \App\Models\m_asset_categ::whereKey($data['categ_id'])->value('useful_life');
        }
        $data['status'] = $data['status'] ?? 'ACTIVE';

        return $data;
    }
}
