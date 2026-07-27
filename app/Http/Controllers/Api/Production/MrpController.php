<?php

namespace App\Http\Controllers\Api\Production;

use App\Http\Controllers\Controller;
use App\Models\prd_mrp_detail;
use App\Models\prd_mrp_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\FgStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Material Requirements Planning. A run explodes the approved MPP over the
 * requested periods and nets requirements time-phased:
 *   FG:  gross = MPP plan; net = gross − (FG stock + open WO) → suggest WO
 *   RM:  gross = Σ FG net × BOM length; net = gross − (RM stock + open PO)
 *        → suggest PR, with net_req_kg for the import-quota check.
 * It records the picture (prd_mrp_main/detail); creating the PR/WO stays a
 * deliberate step in those screens.
 */
class MrpController extends Controller
{
    public function __construct(private FgStockService $fg) {}

    public function index(Request $request)
    {
        $rows = prd_mrp_main::query()->withCount('detail')->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        $main = prd_mrp_main::with(['detail.item'])->findOrFail($id);
        $detail = $main->detail->map(fn ($d) => [
            'item_id' => $d->item_id,
            'item_code' => $d->item?->code,
            'part_name' => $d->item?->part_name,
            'period' => $d->period,
            'level' => $d->suggestion === 'WO' ? 'FG' : 'RM',
            'gross_req' => (int) $d->gross_req,
            'onhand' => (int) $d->onhand,
            'open_po' => (int) $d->open_po,
            'open_wo' => (int) $d->open_wo,
            'net_req' => (int) $d->net_req,
            'net_req_kg' => $d->net_req_kg !== null ? (float) $d->net_req_kg : null,
            'suggestion' => $d->suggestion,
        ]);

        return ApiResponse::item(['id' => $main->id, 'run_date' => $main->run_date, 'status' => $main->status, 'detail' => $detail->values()]);
    }

    public function run(Request $request)
    {
        $data = $request->validate([
            'periods' => ['required', 'array', 'min:1'],
            'periods.*' => ['string', 'regex:/^\d{6}$/'],
        ]);
        $periods = collect($data['periods'])->unique()->sort()->values()->all();

        $rows = $this->explode($periods);

        $main = DB::transaction(function () use ($rows, $request) {
            $main = prd_mrp_main::create([
                'run_date' => now(),
                'user_id' => $request->user()->id,
                'status' => 'DONE',
                'created_at' => now(),
            ]);
            foreach ($rows as $r) {
                prd_mrp_detail::create(['main_id' => $main->id] + $r);
            }
            AuditLogger::record($request, 'Run MRP ' . implode(',', array_unique(array_column($rows, 'period'))) . " ({$main->id})");

            return $main;
        });

        return $this->show($main->id);
    }

    public function destroy(Request $request, int $id)
    {
        $main = prd_mrp_main::findOrFail($id);
        DB::transaction(function () use ($main, $request) {
            prd_mrp_detail::where('main_id', $main->id)->delete();
            $main->delete();
            AuditLogger::record($request, "Delete MRP run {$main->id}");
        });

        return ApiResponse::item(['message' => 'MRP run dihapus.']);
    }

    /**
     * Time-phased explosion across the sorted periods. Running balances carry
     * supply (stock + open orders) forward so a period only shows the shortfall
     * that earlier supply did not already cover.
     *
     * @param  array<int, string>  $periods
     * @return array<int, array<string, mixed>>
     */
    private function explode(array $periods): array
    {
        $rmOnhand = [];       // rm item → running bars available
        $rmMeta = [];         // rm item → [bar_length, weight]
        $fgOnhand = [];       // fg item → running pcs available
        $out = [];

        foreach ($periods as $period) {
            $mpp = DB::table('prd_mpp')->where('period', $period)->where('status', 'APPROVED')
                ->pluck('plan_qty', 'item_id');

            $rmGross = [];    // rm item → bars needed this period

            foreach ($mpp as $fgId => $planQty) {
                $planQty = (int) $planQty;
                if (! isset($fgOnhand[$fgId])) {
                    $fgOnhand[$fgId] = $this->fg->stock((int) $fgId) + $this->openWo((int) $fgId);
                }
                $avail = $fgOnhand[$fgId];
                $net = max(0, $planQty - $avail);
                $fgOnhand[$fgId] = max(0, $avail - $planQty);

                $out[] = [
                    'item_id' => (int) $fgId, 'period' => $period,
                    'gross_req' => $planQty, 'onhand' => $this->fg->stock((int) $fgId),
                    'open_po' => 0, 'open_wo' => $this->openWo((int) $fgId),
                    'net_req' => $net, 'net_req_kg' => null,
                    'suggestion' => $net > 0 ? 'WO' : null,
                ];

                // explode the shortfall through the FG's BOM into RM bars
                if ($net > 0) {
                    foreach ($this->bomRm((int) $fgId) as $rm) {
                        $bar = $rmMeta[$rm['mat_id']]['bar'] ??= max(1.0, (float) $rm['bar_length']);
                        $bars = (int) ceil($net * $rm['length_use'] / $bar);
                        $rmGross[$rm['mat_id']] = ($rmGross[$rm['mat_id']] ?? 0) + $bars;
                        $rmMeta[$rm['mat_id']]['weight'] ??= (float) $rm['weight'];
                    }
                }
            }

            foreach ($rmGross as $rmId => $gross) {
                if (! isset($rmOnhand[$rmId])) {
                    $rmOnhand[$rmId] = $this->rmStock((int) $rmId) + $this->openPo((int) $rmId);
                }
                $avail = $rmOnhand[$rmId];
                $net = max(0, $gross - $avail);
                $rmOnhand[$rmId] = max(0, $avail - $gross);

                $out[] = [
                    'item_id' => (int) $rmId, 'period' => $period,
                    'gross_req' => $gross, 'onhand' => $this->rmStock((int) $rmId),
                    'open_po' => $this->openPo((int) $rmId), 'open_wo' => 0,
                    'net_req' => $net,
                    'net_req_kg' => round($net * ($rmMeta[$rmId]['weight'] ?? 0), 2),
                    'suggestion' => $net > 0 ? 'PR' : null,
                ];
            }
        }

        return $out;
    }

    /** BOM raw-material lines of an FG: mat_id, length used per pc, bar length, weight. */
    private function bomRm(int $fgId): array
    {
        return DB::table('m_bom as b')
            ->join('m_bom_det_rm as d', 'd.id_prim', '=', 'b.id')
            ->join('m_item as i', 'i.id', '=', 'd.mat_id')
            ->where('b.item_id', $fgId)
            ->get(['d.mat_id', 'd.length_use', 'i.length as bar_length', 'i.weight'])
            ->map(fn ($r) => [
                'mat_id' => (int) $r->mat_id,
                'length_use' => (float) $r->length_use,
                'bar_length' => (float) $r->bar_length,
                'weight' => (float) $r->weight,
            ])->all();
    }

    /** FG pieces on open (DRAFT/RELEASED) work orders. */
    private function openWo(int $fgId): int
    {
        return (int) DB::table('prd_wo_main')->where('fg_id', $fgId)->whereIn('status', [1, 2])->sum('qty');
    }

    /** RM bars physically on hand (received, not yet issued). */
    private function rmStock(int $rmId): int
    {
        return (int) DB::table('wh_inc_detail')->where('item_id', $rmId)
            ->whereNotIn('serial_id', DB::table('wh_out_detail')->select('serial_id'))
            ->sum('qty');
    }

    /** RM bars on open purchase orders (ordered minus received). */
    private function openPo(int $rmId): int
    {
        return (int) DB::table('prc_po_detail as d')
            ->join('prc_po_main as m', 'm.id', '=', 'd.main_id')
            ->where('d.item_id', $rmId)->whereIn('m.status', [1, 2])
            ->sum(DB::raw('GREATEST(d.qty - d.qty_received, 0)'));
    }
}
