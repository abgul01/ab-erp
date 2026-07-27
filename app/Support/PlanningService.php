<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Shared production-planning maths used by MPP (net requirement) and MPS
 * (capacity-based scheduling). Kept in one place so both screens agree.
 *
 * WO status ints (see WoController): 1 DRAFT · 2 RELEASED · 3 CLOSED · 9 CANCELLED.
 */
class PlanningService
{
    /** Productive seconds per machine per working day: 8h × 2 shifts = 16h. */
    public const WORK_SECONDS_PER_DAY = 57600;

    /** Process id of the terminal "FG" marker step (cached), or null if unseeded. */
    public static function fgProcId(): ?int
    {
        static $id = false;
        if ($id === false) {
            $id = DB::table('m_process')->where('code', 'FG')->value('id');
            $id = $id ? (int) $id : null;
        }

        return $id;
    }

    /**
     * "On process" = pieces of an item already cut (the first routing step) on
     * WOs that are not yet finished (status DRAFT/RELEASED, not CLOSED or
     * CANCELLED). Reduces MPP demand. Read straight from the cutting
     * transactions, so it is real reported output from the floor.
     */
    public function onProcess(int $itemId): int
    {
        return (int) DB::table('tr_cut_serial as cs')
            ->join('tr_cut_detail as cd', 'cd.id', '=', 'cs.detail_id')
            ->join('tr_cut_main as cm', 'cm.id', '=', 'cd.main_id')
            ->join('prd_wip as w', 'w.id', '=', 'cm.wip_id')
            ->join('prd_wo_main as wm', 'wm.id', '=', 'w.wo_id')
            ->where('wm.fg_id', $itemId)
            ->whereIn('wm.status', [1, 2])
            ->sum('cs.qty');
    }

    /**
     * Bottleneck of an item's routing: for each process take its most-preferred
     * (lowest priority number) active cycle time, then the slowest of those
     * processes sets the throughput. Returns cycle_sec (per piece) + the machine
     * to run that bottleneck on. cycle_sec = 0 when no cycle time is defined.
     *
     * @return array{cycle_sec: float, machine_id: int|null}
     */
    public function bottleneck(int $itemId): array
    {
        $rows = DB::table('m_route_time')
            ->where('item_id', $itemId)->where('active', 1)
            ->orderBy('proc_id')->orderBy('priority')
            ->get(['proc_id', 'machine_id', 'cycle_sec']);

        $topPerProc = [];
        foreach ($rows as $r) {
            // rows are ordered by priority asc, so first seen per proc_id is the top choice
            if (! array_key_exists($r->proc_id, $topPerProc)) {
                $topPerProc[$r->proc_id] = $r;
            }
        }

        $bottleneck = null;
        foreach ($topPerProc as $r) {
            if ($bottleneck === null || (float) $r->cycle_sec > (float) $bottleneck->cycle_sec) {
                $bottleneck = $r;
            }
        }

        return [
            'cycle_sec' => $bottleneck ? (float) $bottleneck->cycle_sec : 0.0,
            'machine_id' => $bottleneck ? ($bottleneck->machine_id ? (int) $bottleneck->machine_id : null) : null,
        ];
    }

    /**
     * How many pieces of an item can be produced per working day given its
     * bottleneck cycle time. 0 cycle time → unlimited (caller falls back).
     */
    public function dailyCapacity(int $itemId): int
    {
        $cycle = $this->bottleneck($itemId)['cycle_sec'];

        return $cycle > 0 ? (int) floor(self::WORK_SECONDS_PER_DAY / $cycle) : 0;
    }

    /**
     * Candidate machines for an item's bottleneck process, ordered by priority
     * (1 = preferred). The MPS loader fills the preferred machine first and
     * overflows to the alternates. Each entry: machine_id + cycle_sec (per pc).
     *
     * @return array<int, array{machine_id: int|null, cycle_sec: float}>
     */
    public function bottleneckMachines(int $itemId): array
    {
        $rows = DB::table('m_route_time')
            ->where('item_id', $itemId)->where('active', 1)
            ->orderBy('proc_id')->orderBy('priority')
            ->get(['proc_id', 'machine_id', 'cycle_sec', 'priority']);

        if ($rows->isEmpty()) {
            return [];
        }

        // top (lowest-priority number) cycle per process → bottleneck = slowest
        $topPerProc = [];
        foreach ($rows as $r) {
            if (! array_key_exists($r->proc_id, $topPerProc)) {
                $topPerProc[$r->proc_id] = (float) $r->cycle_sec;
            }
        }
        $bottleneckProc = array_keys($topPerProc, max($topPerProc))[0];

        return $rows->where('proc_id', $bottleneckProc)->sortBy('priority')
            ->map(fn ($r) => [
                'machine_id' => $r->machine_id ? (int) $r->machine_id : null,
                'cycle_sec' => (float) $r->cycle_sec,
            ])->values()->all();
    }

    /**
     * Full routing of an item as an ordered list of operations. Order follows
     * the routing steps (m_bom_pro_det.sequence); each operation carries the
     * machines that can run it (m_route_time, priority order) with cycle time.
     * Processes without a cycle time still appear (machines empty) so the plan
     * knows the step exists.
     *
     * @return array<int, array{proc_id:int, sequence:int, machines: array<int, array{machine_id:int|null, cycle_sec:float}>}>
     */
    public function routing(int $itemId, ?int $mainId = null): array
    {
        // An item may have several routing templates ranked by priority; a WO
        // passes the one it chose, otherwise planning assumes the top-priority
        // routing (m_bom_pro ordered by priority).
        if ($mainId === null) {
            $mainId = DB::table('m_bom_pro')->where('item_id', $itemId)
                ->orderBy('priority')->value('process_main_id');
        }

        $fgProc = self::fgProcId();
        $steps = $mainId
            ? DB::table('m_process_main_det')->where('main_id', $mainId)
                ->when($fgProc, fn ($q) => $q->where('proc_id', '<>', $fgProc))   // FG is a marker, not a MES step
                ->orderBy('sequence')->get(['proc_id', 'sequence'])
            : collect();

        // fallback: no routing defined → use whatever processes have a cycle time
        if ($steps->isEmpty()) {
            $steps = DB::table('m_route_time')->where('item_id', $itemId)->where('active', 1)
                ->distinct()->orderBy('proc_id')->get(['proc_id'])
                ->map(fn ($r, $i) => (object) ['proc_id' => $r->proc_id, 'sequence' => $i + 1]);
        }

        $ops = [];
        foreach ($steps as $s) {
            $machines = DB::table('m_route_time')
                ->where('item_id', $itemId)->where('proc_id', $s->proc_id)->where('active', 1)
                ->orderBy('priority')
                ->get(['machine_id', 'cycle_sec'])
                ->map(fn ($r) => [
                    'machine_id' => $r->machine_id ? (int) $r->machine_id : null,
                    'cycle_sec' => (float) $r->cycle_sec,
                ])->values()->all();

            $ops[] = [
                'proc_id' => (int) $s->proc_id,
                'sequence' => (int) $s->sequence,
                'machines' => $machines,
            ];
        }

        return $ops;
    }

    /** Cycle time (sec/pc) of an item's operation on a specific machine. */
    public function cycleFor(int $itemId, int $procId, ?int $machineId): float
    {
        $q = DB::table('m_route_time')->where('item_id', $itemId)->where('proc_id', $procId)->where('active', 1);
        if ($machineId) {
            $q->where('machine_id', $machineId);
        }
        $row = $q->orderBy('priority')->first();

        return $row ? max(0.01, (float) $row->cycle_sec) : 0.0;
    }
}
