<?php

namespace App\Http\Controllers\Api\Production;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\prd_mpp;
use App\Models\prd_mps;
use App\Models\prd_mps_resched;
use App\Models\prd_wo_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\PlanningService;
use App\Support\WorkCalendarService;
use Illuminate\Http\Request;

/**
 * Master Production Schedule (Fase 4 APS). Dated schedule entries per FG, capped
 * by the approved MPP for that item+period. Lifecycle: DRAFT → APPROVED (only
 * approved MPS can be turned into a Work Order).
 */
class MpsController extends Controller
{
    public function index(Request $request)
    {
        $query = prd_mps::with(['item', 'machine', 'process']);
        if ($q = trim((string) $request->query('q', ''))) {
            $query->whereHas('item', fn ($s) => $s->where('code', 'like', "%{$q}%")->orWhere('part_name', 'like', "%{$q}%"));
        }
        if ($st = $request->query('status')) {
            $query->where('status', $st);
        }
        // Filter to a single month so the (large) operation-level board isn't
        // truncated by paging: `month`=YYYY-MM or `period`=YYYYMM.
        if ($m = $request->query('month')) {
            $query->where('plan_date', 'like', $m.'-%');
        } elseif ($p = $request->query('period')) {
            $query->where('plan_date', 'like', substr($p, 0, 4).'-'.substr($p, 4, 2).'-%');
        }
        $query->orderBy('plan_date')->orderBy('machine_id');
        $page = $query->paginate(min(max((int) $request->query('per_page', 20), 1), 2000));

        // lots with a pending reschedule request → the board blinks them yellow
        $ids = $page->getCollection()->pluck('id')->all();
        $pending = $ids
            ? prd_mps_resched::whereIn('mps_id', $ids)->where('status', 'PENDING')->pluck('mps_id')->flip()
            : collect();

        // each lot's OP number = 1-based position of its process in the item's
        // routing, so the board can colour cells by operation step.
        $planner = new PlanningService;
        $seqByItem = [];
        foreach ($page->getCollection()->pluck('item_id')->unique()->filter() as $itemId) {
            $map = [];
            foreach ($planner->routing((int) $itemId) as $i => $op) {
                $map[(int) $op['proc_id']] = $i + 1;
            }
            $seqByItem[$itemId] = $map;
        }

        // annotate remaining WO capacity so the WO screen can pick open MPS
        $page->getCollection()->transform(function ($m) use ($pending, $seqByItem) {
            $m->wo_qty = (int) prd_wo_main::where('mps_id', $m->id)->where('status', '<>', 9)->sum('qty');
            $m->wo_remaining = max(0, (int) $m->qty - $m->wo_qty);
            $m->pending = $pending->has($m->id);
            $m->op_seq = $m->proc_id ? ($seqByItem[$m->item_id][$m->proc_id] ?? null) : null;

            return $m;
        });

        return ApiResponse::paginated($page);
    }

    public function show(int $id)
    {
        $mps = prd_mps::with(['item', 'machine', 'process'])->findOrFail($id);
        $period = $this->periodOf($mps->plan_date);
        $data = $mps->toArray();
        $data['period'] = $period;
        $data['mpp_qty'] = (int) prd_mpp::where('item_id', $mps->item_id)->where('period', $period)->where('status', 'APPROVED')->value('plan_qty');
        $data['wo_qty'] = (int) prd_wo_main::where('mps_id', $mps->id)->where('status', '<>', 9)->sum('qty');
        $data['wo_remaining'] = max(0, (int) $mps->qty - $data['wo_qty']);

        return ApiResponse::item($data);
    }

    public function store(Request $request)
    {
        $data = $this->validateMps($request);
        $this->assertWithinMpp($data['item_id'], $data['plan_date'], $data['qty'], null, $data['proc_id']);

        $mps = prd_mps::create([...$data, 'status' => 'DRAFT']);
        AuditLogger::record($request, "Create MPS {$mps->plan_date} item#{$mps->item_id}");

        return ApiResponse::item($mps->load(['item', 'machine', 'process']), 201);
    }

    public function update(Request $request, int $id)
    {
        $mps = prd_mps::findOrFail($id);
        $this->assertDraft($mps);
        $data = $this->validateMps($request);
        $this->assertWithinMpp($data['item_id'], $data['plan_date'], $data['qty'], $id, $data['proc_id']);

        $mps->update($data);
        AuditLogger::record($request, "Update MPS #{$id}");

        return ApiResponse::item($mps->load(['item', 'machine', 'process']));
    }

    public function destroy(Request $request, int $id)
    {
        $mps = prd_mps::findOrFail($id);
        $this->assertDraft($mps);
        if (prd_wo_main::where('mps_id', $id)->exists()) {
            throw BizException::make('MPS_HAS_WO', 'MPS ini sudah punya Work Order, tidak dapat dihapus.');
        }
        $mps->delete();
        AuditLogger::record($request, "Delete MPS #{$id}");

        return ApiResponse::item(['message' => 'MPS berhasil dihapus.']);
    }

    public function approve(Request $request, int $id)
    {
        $mps = prd_mps::findOrFail($id);
        if ($mps->status !== 'DRAFT') {
            throw BizException::make('MPS_STATE', 'Hanya MPS DRAFT yang dapat di-approve.');
        }
        $mps->update(['status' => 'APPROVED']);
        AuditLogger::record($request, "Approve MPS #{$id}");

        return ApiResponse::item($mps->load(['item', 'machine']));
    }

    /**
     * Auto-schedule MPS as a ROUTING-BASED, finite-capacity machine load.
     * For every approved-MPP item, the monthly quantity flows through the item's
     * routing operations in sequence (Cut → Machining → Chamfer …). Each
     * operation is loaded onto its process machines (priority order, overflow to
     * alternates) and split across working days by cycle time until the machine-
     * day capacity (8h × 2 shifts = 16h) is full. A later operation starts only
     * after the previous one has begun (flow), so you can trace a quantity from
     * one machine/day to the next. Each MPS row = one operation lot
     * {date, item, process, machine, qty}. APPROVED lots are kept and
     * pre-consume capacity; only DRAFT lots are regenerated.
     */
    public function generate(Request $request)
    {
        $data = $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{6}$/'],
            'item_id' => ['nullable', 'integer', 'exists:m_item,id'],
        ]);
        $period = $data['period'];
        $planner = new PlanningService;
        $like = substr($period, 0, 4).'-'.substr($period, 4, 2).'-%';
        $days = $this->workingDays($period);
        $nDays = count($days);
        $cap = PlanningService::WORK_SECONDS_PER_DAY;

        $mppQuery = prd_mpp::where('period', $period)->where('status', 'APPROVED');
        if (! empty($data['item_id'])) {
            $mppQuery->where('item_id', $data['item_id']);
        }
        $mpps = $mppQuery->get();

        // machine-day used-seconds ledger: $load[machineId][date]
        $load = [];

        // pre-consume capacity already taken by APPROVED lots (kept as-is)
        $approvedLots = prd_mps::where('status', 'APPROVED')->where('plan_date', 'like', $like)
            ->whereNotNull('machine_id')->get(['machine_id', 'item_id', 'proc_id', 'qty', 'plan_date']);
        foreach ($approvedLots as $lot) {
            $cyc = $planner->cycleFor((int) $lot->item_id, (int) $lot->proc_id, (int) $lot->machine_id) ?: 1;
            $d = substr($lot->plan_date, 0, 10);
            $load[$lot->machine_id][$d] = ($load[$lot->machine_id][$d] ?? 0) + (int) $lot->qty * $cyc;
        }

        // wipe our own DRAFT lots for the period (optionally one item)
        $del = prd_mps::where('status', 'DRAFT')->where('plan_date', 'like', $like);
        if (! empty($data['item_id'])) {
            $del->where('item_id', $data['item_id']);
        }
        $del->delete();

        $created = 0;
        $items = 0;
        $shortCapacity = 0;

        foreach ($mpps as $mpp) {
            $itemId = (int) $mpp->item_id;
            $plan = (int) $mpp->plan_qty;
            $ops = $planner->routing($itemId);
            if (empty($ops)) {
                // no routing/cycle time → single unscheduled lot on day 1
                prd_mps::create(['plan_date' => $days[0] ?? ($period.'01'), 'item_id' => $itemId, 'proc_id' => null, 'qty' => $plan, 'machine_id' => null, 'status' => 'DRAFT']);
                $created++;
                $items++;

                continue;
            }
            $items++;
            $itemShort = false;
            $startIdx = 0; // earliest working-day index this operation may start on (flow)

            foreach ($ops as $op) {
                $procId = $op['proc_id'];
                // per-process remaining after any already-approved lots
                $approvedProc = (int) prd_mps::where('item_id', $itemId)->where('proc_id', $procId)
                    ->where('status', 'APPROVED')->where('plan_date', 'like', $like)->sum('qty');
                $remaining = max(0, $plan - $approvedProc);

                $machines = $op['machines'];
                if (empty($machines)) {
                    // process has no machine/cycle → park the lot unscheduled, keep the flow moving
                    if ($remaining > 0) {
                        prd_mps::create(['plan_date' => $days[$startIdx] ?? $days[0], 'item_id' => $itemId, 'proc_id' => $procId, 'qty' => $remaining, 'machine_id' => null, 'status' => 'DRAFT']);
                        $created++;
                    }
                    $startIdx = min($nDays - 1, $startIdx + 1);

                    continue;
                }

                $firstDay = null;
                foreach ($machines as $m) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $cyc = max(0.01, $m['cycle_sec']);
                    $mid = $m['machine_id'];
                    for ($di = $startIdx; $di < $nDays; $di++) {
                        if ($remaining <= 0) {
                            break;
                        }
                        $date = $days[$di];
                        $maxPcs = (int) floor(($cap - ($load[$mid][$date] ?? 0)) / $cyc);
                        if ($maxPcs <= 0) {
                            continue;
                        }
                        $q = min($maxPcs, $remaining);
                        prd_mps::create(['plan_date' => $date, 'item_id' => $itemId, 'proc_id' => $procId, 'qty' => $q, 'machine_id' => $mid, 'status' => 'DRAFT']);
                        $load[$mid][$date] = ($load[$mid][$date] ?? 0) + $q * $cyc;
                        $remaining -= $q;
                        $created++;
                        if ($firstDay === null || $di < $firstDay) {
                            $firstDay = $di;
                        }
                    }
                }
                if ($remaining > 0) {
                    $itemShort = true;
                }
                // the next operation may start the day after this one began
                if ($firstDay !== null) {
                    $startIdx = min($nDays - 1, max($startIdx, $firstDay + 1));
                }
            }
            if ($itemShort) {
                $shortCapacity++;
            }
        }

        AuditLogger::record($request, "Generate MPS {$period}: {$created} lot operasi utk {$items} item");

        return ApiResponse::item([
            'period' => $period, 'items' => $items, 'created' => $created, 'short_capacity' => $shortCapacity,
        ]);
    }

    /** Weekday (Mon–Fri) dates of a YYYYMM period as Y-m-d strings. */
    /**
     * Dates the plant actually runs.
     *
     * This used to be "any day that is not a weekend", which quietly scheduled
     * production onto national holidays and shutdowns. It now asks the working
     * calendar; a month nobody has filled in still falls back to weekdays, so
     * the schedule keeps working while the calendar is being set up.
     */
    private function workingDays(string $period): array
    {
        return app(WorkCalendarService::class)->workingDates($period);
    }

    private function periodOf(string $date): string
    {
        return substr($date, 0, 4).substr($date, 5, 2);
    }

    /**
     * MPS total per (item, period, process) must not exceed the approved MPP
     * plan. Because MPS is now operation-level, the cap is applied per process
     * (each routing step of the month produces up to the MPP quantity).
     */
    private function assertWithinMpp(int $itemId, string $date, int $qty, ?int $excludeId, ?int $procId): void
    {
        $period = $this->periodOf($date);
        $mpp = prd_mpp::where('item_id', $itemId)->where('period', $period)->where('status', 'APPROVED')->first();
        if (! $mpp) {
            throw BizException::make('MPS_NO_MPP', "Belum ada MPP approved untuk item ini di periode {$period}. Buat & approve MPP dulu.");
        }
        $existing = (int) prd_mps::where('item_id', $itemId)
            ->where('plan_date', 'like', substr($period, 0, 4).'-'.substr($period, 4, 2).'-%')
            ->when($procId !== null, fn ($q) => $q->where('proc_id', $procId), fn ($q) => $q->whereNull('proc_id'))
            ->when($excludeId, fn ($q) => $q->where('id', '<>', $excludeId))
            ->sum('qty');
        if ($existing + $qty > (int) $mpp->plan_qty) {
            throw BizException::make('MPS_OVER', "Total MPS proses ini ({$existing}+{$qty}) melebihi rencana MPP ({$mpp->plan_qty}) periode {$period}.");
        }
    }

    private function assertDraft(prd_mps $mps): void
    {
        if ($mps->status !== 'DRAFT') {
            throw BizException::make('MPS_LOCKED', 'MPS yang sudah approved tidak dapat diubah.');
        }
    }

    private function validateMps(Request $request): array
    {
        $data = $request->validate([
            'plan_date' => ['required', 'date'],
            'item_id' => ['required', 'integer', 'exists:m_item,id'],
            'proc_id' => ['nullable', 'integer', 'exists:m_process,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'machine_id' => ['nullable', 'integer'],
        ]);
        $data['proc_id'] = $data['proc_id'] ?? null;

        return $data;
    }
}
