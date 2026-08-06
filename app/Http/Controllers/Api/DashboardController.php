<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $now = now();
        $today = $now->toDateString();

        // ── 1. Master data counts ──────────────────────────────────────
        $masterCounts = [
            'items' => DB::table('m_item')->count(),
            'rm_items' => DB::table('m_item')->where('type', 'RM')->count(),
            'pm_items' => DB::table('m_item')->where('type', 'PM')->count(),
            'fg_items' => DB::table('m_item')->where('type', 'FG')->count(),
            'contacts' => DB::table('m_contacts')->count(),
            'machines' => DB::table('m_machine')->count(),
            'processes' => DB::table('m_process')->count(),
            'uoms' => DB::table('m_uom')->count(),
        ];

        // ── 2. Procurement KPIs ────────────────────────────────────────
        $procurement = [
            'pr_draft' => $this->countWhere('prc_pr_main', 'status', 'DRAFT'),
            'po_open' => $this->countWhere('prc_po_main', 'status', 'OPEN'),
            'po_partial' => $this->countWhere('prc_po_main', 'status', 'PARTIAL'),
            'gr_pending' => DB::table('prc_gr_main')
                ->whereNotIn('status', ['POSTED', 'CONFIRMED', 'INVOICED'])
                ->count(),
            'ap_unpaid' => DB::table('prc_inv_main')->whereNotIn('status', ['PAID', 'CLOSED'])->count(),
            'po_overdue' => DB::table('prc_po_main')
                ->whereIn('status', ['OPEN', 'PARTIAL'])
                ->whereNotNull('eta')->where('eta', '<', $today)
                ->count(),
        ];

        // ── 3. Production KPIs ─────────────────────────────────────────
        $production = [
            'wo_draft' => $this->countWhere('prd_wo_main', 'status', 'DRAFT'),
            'wo_open' => $this->countWhere('prd_wo_main', 'status', 'OPEN'),
            'wo_inprogress' => $this->countWhere('prd_wo_main', 'status', 'INPROGRESS'),
            'wo_completed' => $this->countWhere('prd_wo_main', 'status', 'COMPLETED'),
            'mps_pending' => $this->countWhere('prd_mps', 'status', 'DRAFT'),
            'mrp_pending' => $this->countWhere('prd_mrp_main', 'status', 'DRAFT'),
        ];

        // ── 4. WMS KPIs ────────────────────────────────────────────────
        $rmStockCount = DB::table('sum_stock_rm')
            ->join('m_item', 'm_item.id', '=', 'sum_stock_rm.item_id')
            ->where('m_item.type', 'RM')->where('sum_stock_rm.qty', '>', 0)
            ->distinct('sum_stock_rm.item_id')->count();

        $fgStockCount = DB::table('tr_inc_fg_main')->count();

        $lowStockCount = DB::table('sum_stock_rm')
            ->join('m_item', 'm_item.id', '=', 'sum_stock_rm.item_id')
            ->where('m_item.type', 'RM')->where('m_item.active', 1)
            ->where('sum_stock_rm.qty', '>', 0)
            ->whereColumn('sum_stock_rm.qty', '<', DB::raw('COALESCE(m_item.min_stock, 0)'))
            ->count();

        $wms = [
            'rm_stock_items' => $rmStockCount,
            'fg_stock_items' => $fgStockCount,
            'rm_total_qty' => (float) DB::table('sum_stock_rm')->sum('qty'),
            'rm_total_weight' => (float) DB::table('sum_stock_rm')->sum('weight_base'),
            'scrap_candidates' => DB::table('tr_ab_cut_main')->count()
                + DB::table('tr_ab_pro')->count(),
            'low_stock_items' => $lowStockCount,
        ];

        // ── 5. Sales KPIs ──────────────────────────────────────────────
        $sales = [
            'so_open' => $this->countWhere('sls_so_main', 'status', 'OPEN'),
            'so_partial' => $this->countWhere('sls_so_main', 'status', 'PARTIAL'),
            'do_pending' => $this->countWhere('sls_do_main', 'status', 'DRAFT'),
            'ar_unpaid' => DB::table('sls_inv_main')->whereNotIn('status', ['PAID', 'CLOSED'])->count(),
            'forecast_current_month' => (float) DB::table('sls_forecast')
                ->where('period', $now->format('Ym'))->sum('qty'),
            'so_overdue' => DB::table('sls_so_main')
                ->whereIn('status', ['OPEN', 'PARTIAL'])
                ->whereNotNull('due_date')->where('due_date', '<', $today)
                ->count(),
            'so_unfulfilled_qty' => (float) DB::table('sls_so_detail')
                ->join('sls_so_main', 'sls_so_main.id', '=', 'sls_so_detail.main_id')
                ->whereIn('sls_so_main.status', ['OPEN', 'PARTIAL'])
                ->whereColumn('sls_so_detail.qty', '>', DB::raw('COALESCE(sls_so_detail.qty_delivered, 0)'))
                ->sum(DB::raw('sls_so_detail.qty - COALESCE(sls_so_detail.qty_delivered, 0)')),
        ];

        // ── 6. MES Today ───────────────────────────────────────────────
        $mes = [
            'cutting_today' => DB::table('tr_cut_main')->whereDate('date', $today)->count(),
            'processing_today' => DB::table('tr_pro_main')->whereDate('date', $today)->count(),
            'downtime_today' => DB::table('tr_dt_cut_main')->whereDate('start_time', $today)->count()
                                + DB::table('tr_dt_pro_main')->whereDate('start_time', $today)->count(),
            'abnormal_today' => DB::table('tr_ab_cut_main')->whereDate('date', $today)->count()
                                + DB::table('tr_ab_pro')->whereDate('date', $today)->count(),
        ];

        // ── 7. Accounting ──────────────────────────────────────────────
        $accounting = [
            'period_open' => DB::table('acc_period')->where('status', 'OPEN')->count() > 0,
            'journal_draft' => $this->countWhere('acc_journal_main', 'status', 'DRAFT'),
        ];

        // ── 8. Subcont KPIs ────────────────────────────────────────────
        $subcont = [
            'po_open' => $this->countWhere('sub_po_main', 'status', 'OPEN'),
            'dn_sent' => DB::table('sub_dn_main')->where('status', 'SENT')->count(),
            'dn_draft' => $this->countWhere('sub_dn_main', 'status', 'DRAFT'),
        ];

        // ── 9. MRP ─────────────────────────────────────────────────────
        $latestMrp = DB::table('prd_mrp_main')->orderByDesc('id')->first();
        $mrp = [
            'draft_count' => $this->countWhere('prd_mrp_main', 'status', 'DRAFT'),
            'latest_id' => $latestMrp?->id,
        ];
        if ($latestMrp) {
            $mrp['unfulfilled_items'] = (int) DB::table('prd_mrp_detail')
                ->where('main_id', $latestMrp->id)->where('net_req', '>', 0)->count();
            $mrp['total_net_req'] = (float) DB::table('prd_mrp_detail')
                ->where('main_id', $latestMrp->id)->sum('net_req');
            $mrp['rm_net_req'] = (float) DB::table('prd_mrp_detail')
                ->where('main_id', $latestMrp->id)
                ->where('suggestion', 'PR')->sum('net_req');
            $mrp['fg_net_req'] = (float) DB::table('prd_mrp_detail')
                ->where('main_id', $latestMrp->id)
                ->where('suggestion', 'WO')->sum('net_req');
        } else {
            $mrp['unfulfilled_items'] = 0;
            $mrp['total_net_req'] = 0;
            $mrp['rm_net_req'] = 0;
            $mrp['fg_net_req'] = 0;
        }

        // ── 10. Charts ─────────────────────────────────────────────────
        $woByStatus = DB::table('prd_wo_main')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')->get()->pluck('total', 'status')->toArray();

        $grnTrend = DB::table('prc_gr_main')
            ->select(DB::raw("DATE_FORMAT(date, '%Y-%m') as month"), DB::raw('COUNT(*) as total'))
            ->whereDate('date', '>=', $now->copy()->subMonths(5)->startOfMonth())
            ->groupBy('month')->orderBy('month')
            ->get()->pluck('total', 'month')->toArray();

        $soTrend = DB::table('sls_so_main')
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"), DB::raw('COUNT(*) as total'))
            ->whereDate('created_at', '>=', $now->copy()->subMonths(5)->startOfMonth())
            ->groupBy('month')->orderBy('month')
            ->get()->pluck('total', 'month')->toArray();

        $topRmStock = DB::table('sum_stock_rm')
            ->join('m_item', 'm_item.id', '=', 'sum_stock_rm.item_id')
            ->where('m_item.type', 'RM')
            ->select('m_item.code as item_code', 'm_item.part_name as item_name', DB::raw('SUM(sum_stock_rm.qty) as value'))
            ->groupBy('m_item.id', 'm_item.code', 'm_item.part_name')
            ->orderByDesc('value')->limit(5)->get();

        // ── 11. SO fulfillment chart ────────────────────────────────────
        $soFulfillment = DB::table('sls_so_detail')
            ->join('sls_so_main', 'sls_so_main.id', '=', 'sls_so_detail.main_id')
            ->whereIn('sls_so_main.status', ['OPEN', 'PARTIAL'])
            ->select(
                DB::raw('SUM(sls_so_detail.qty) as total_ordered'),
                DB::raw('SUM(COALESCE(sls_so_detail.qty_delivered, 0)) as total_delivered')
            )
            ->first();

        // ── 12. Low stock items chart ───────────────────────────────────
        $lowStockChart = DB::table('sum_stock_rm')
            ->join('m_item', 'm_item.id', '=', 'sum_stock_rm.item_id')
            ->where('m_item.type', 'RM')->where('m_item.active', 1)
            ->where('sum_stock_rm.qty', '>', 0)
            ->whereColumn('sum_stock_rm.qty', '<', DB::raw('COALESCE(m_item.min_stock, 0)'))
            ->select('m_item.code as item_code', 'm_item.part_name as item_name',
                DB::raw('SUM(sum_stock_rm.qty) as stock_qty'),
                DB::raw('MAX(m_item.min_stock) as min_stock'))
            ->groupBy('m_item.id', 'm_item.code', 'm_item.part_name')
            ->orderBy(DB::raw('SUM(sum_stock_rm.qty)'))
            ->limit(5)->get();

        // ── 13. Trends ─────────────────────────────────────────────────
        $prevMonth = $now->copy()->subMonth();
        $trends = [
            'so_this_month' => (int) DB::table('sls_so_main')
                ->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count(),
            'so_last_month' => (int) DB::table('sls_so_main')
                ->whereMonth('created_at', $prevMonth->month)->whereYear('created_at', $prevMonth->year)->count(),
            'wo_this_month' => (int) DB::table('prd_wo_main')
                ->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count(),
            'wo_last_month' => (int) DB::table('prd_wo_main')
                ->whereMonth('created_at', $prevMonth->month)->whereYear('created_at', $prevMonth->year)->count(),
            'po_this_month' => (int) DB::table('prc_po_main')
                ->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count(),
            'po_last_month' => (int) DB::table('prc_po_main')
                ->whereMonth('created_at', $prevMonth->month)->whereYear('created_at', $prevMonth->year)->count(),
            'grn_this_month' => (int) DB::table('prc_gr_main')
                ->whereMonth('date', $now->month)->whereYear('date', $now->year)->count(),
            'grn_last_month' => (int) DB::table('prc_gr_main')
                ->whereMonth('date', $prevMonth->month)->whereYear('date', $prevMonth->year)->count(),
        ];

        return ApiResponse::item([
            'master' => $masterCounts,
            'procurement' => $procurement,
            'production' => $production,
            'wms' => $wms,
            'sales' => $sales,
            'mes' => $mes,
            'accounting' => $accounting,
            'subcont' => $subcont,
            'mrp' => $mrp,
            'trends' => $trends,
            'charts' => [
                'wo_by_status' => $woByStatus,
                'grn_trend' => $grnTrend,
                'so_trend' => $soTrend,
                'top_rm_stock' => $topRmStock,
                'so_fulfillment' => $soFulfillment ? [
                    'ordered' => (float) $soFulfillment->total_ordered,
                    'delivered' => (float) $soFulfillment->total_delivered,
                    'pending' => max(0, (float) $soFulfillment->total_ordered - (float) $soFulfillment->total_delivered),
                ] : ['ordered' => 0, 'delivered' => 0, 'pending' => 0],
                'low_stock' => $lowStockChart,
            ],
        ]);
    }

    private function countWhere(string $table, string $column, string $value): int
    {
        return (int) DB::table($table)->where($column, $value)->count();
    }
}
