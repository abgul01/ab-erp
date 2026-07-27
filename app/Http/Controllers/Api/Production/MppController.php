<?php

namespace App\Http\Controllers\Api\Production;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\prd_mpp;
use App\Models\prd_mps;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\PlanningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Monthly Production Planning (Fase 4 APS). One planned qty per FG per period
 * (YYYYMM). Lifecycle: DRAFT → APPROVED (only approved MPP can seed MPS).
 */
class MppController extends Controller
{
    public function index(Request $request)
    {
        $query = prd_mpp::with('item');
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where('period', 'like', "%{$q}%")
                ->orWhereHas('item', fn ($s) => $s->where('code', 'like', "%{$q}%")->orWhere('part_name', 'like', "%{$q}%"));
        }
        if ($p = $request->query('period')) {
            $query->where('period', $p);
        }
        if ($st = $request->query('status')) {
            $query->where('status', $st);
        }
        $query->orderByDesc('period')->orderBy('item_id');

        return ApiResponse::paginated($query->paginate(min(max((int) $request->query('per_page', 20), 1), 200)));
    }

    public function show(int $id)
    {
        $mpp = prd_mpp::with('item')->findOrFail($id);
        $data = $mpp->toArray();
        $data['mps_qty'] = (int) prd_mps::where('item_id', $mpp->item_id)
            ->where('plan_date', 'like', substr($mpp->period, 0, 4) . '-' . substr($mpp->period, 4, 2) . '-%')->sum('qty');

        return ApiResponse::item($data);
    }

    public function store(Request $request)
    {
        $data = $this->validateMpp($request);
        $mpp = prd_mpp::create([...$data, 'status' => 'DRAFT']);
        AuditLogger::record($request, "Create MPP {$mpp->period} item#{$mpp->item_id}");

        return ApiResponse::item($mpp->load('item'), 201);
    }

    public function update(Request $request, int $id)
    {
        $mpp = prd_mpp::findOrFail($id);
        $this->assertDraft($mpp);
        $data = $this->validateMpp($request, $id);
        $mpp->update($data);
        AuditLogger::record($request, "Update MPP {$mpp->period} item#{$mpp->item_id}");

        return ApiResponse::item($mpp->load('item'));
    }

    public function destroy(Request $request, int $id)
    {
        $mpp = prd_mpp::findOrFail($id);
        $this->assertDraft($mpp);
        $mpp->delete();
        AuditLogger::record($request, "Delete MPP #{$id}");

        return ApiResponse::item(['message' => 'MPP berhasil dihapus.']);
    }

    public function approve(Request $request, int $id)
    {
        $mpp = prd_mpp::findOrFail($id);
        if ($mpp->status !== 'DRAFT') {
            throw BizException::make('MPP_STATE', 'Hanya MPP DRAFT yang dapat di-approve.');
        }
        $mpp->update(['status' => 'APPROVED']);
        AuditLogger::record($request, "Approve MPP {$mpp->period} item#{$mpp->item_id}");

        return ApiResponse::item($mpp->load('item'));
    }

    /**
     * Generate/refresh MPP for one or more periods from net demand:
     *   demand   = max(Σ approved-SO qty, Σ FINAL-forecast qty) per FG,
     *   plan_qty = max(0, demand − on_process).
     * on_process = pieces already started at the first process for open WOs
     * (PlanningService::onProcess). Only DRAFT rows are (re)written; APPROVED
     * MPP is never overwritten. Accepts either a single `period` or a list of
     * `periods` (YYYYMM) so the whole quarter can be built in one click.
     */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'period' => ['required_without:periods', 'string', 'regex:/^\d{6}$/'],
            'periods' => ['required_without:period', 'array'],
            'periods.*' => ['string', 'regex:/^\d{6}$/'],
            'source' => ['nullable', 'in:MAX,SO,FORECAST'],
        ]);
        $periods = $data['periods'] ?? [$data['period']];
        $source = $data['source'] ?? 'MAX';
        $planner = new PlanningService;
        $fg = new \App\Support\FgStockService;

        $created = 0; $updated = 0; $skipped = 0;

        foreach ($periods as $period) {
            $so = DB::table('sls_so_detail as d')->join('sls_so_main as m', 'm.id', '=', 'd.main_id')
                ->where('m.status', 'APPROVED')
                ->whereRaw("DATE_FORMAT(m.date, '%Y%m') = ?", [$period])
                ->groupBy('d.item_id')->selectRaw('d.item_id, SUM(d.qty) as q')->pluck('q', 'item_id');

            $fc = DB::table('sls_forecast')->where('period', $period)->where('version', 'FINAL')
                ->groupBy('item_id')->selectRaw('item_id, SUM(qty) as q')->pluck('q', 'item_id');

            $itemIds = collect($so->keys())->merge($fc->keys())->unique();

            foreach ($itemIds as $itemId) {
                $soQ = (int) ($so[$itemId] ?? 0);
                $fcQ = (int) ($fc[$itemId] ?? 0);
                $demand = match ($source) {
                    'SO' => $soQ,
                    'FORECAST' => $fcQ,
                    default => max($soQ, $fcQ),
                };
                $onProcess = $planner->onProcess((int) $itemId);
                $fgStock = $fg->stock((int) $itemId);
                // net requirement: demand not already covered by WIP or finished stock
                $plan = max(0, $demand - $onProcess - $fgStock);
                if ($plan <= 0) {
                    continue;
                }
                $existing = prd_mpp::where('period', $period)->where('item_id', $itemId)->first();
                if (! $existing) {
                    prd_mpp::create(['period' => $period, 'item_id' => $itemId, 'plan_qty' => $plan, 'status' => 'DRAFT']);
                    $created++;
                } elseif ($existing->status === 'DRAFT') {
                    $existing->update(['plan_qty' => $plan]);
                    $updated++;
                } else {
                    $skipped++;
                }
            }
        }

        $label = implode(',', $periods);
        AuditLogger::record($request, "Generate MPP {$label} ({$source}): +{$created} ~{$updated}");

        return ApiResponse::item(['periods' => $periods, 'created' => $created, 'updated' => $updated, 'skipped_approved' => $skipped]);
    }

    private function assertDraft(prd_mpp $mpp): void
    {
        if ($mpp->status !== 'DRAFT') {
            throw BizException::make('MPP_LOCKED', 'MPP yang sudah approved tidak dapat diubah.');
        }
    }

    private function validateMpp(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{6}$/',
                Rule::unique('prd_mpp', 'period')->where(fn ($q) => $q->where('item_id', $request->input('item_id')))->ignore($id)],
            'item_id' => ['required', 'integer', 'exists:m_item,id'],
            'plan_qty' => ['required', 'integer', 'min:1'],
        ], [], ['period' => 'periode (YYYYMM)']);
    }
}
