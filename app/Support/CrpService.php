<?php

namespace App\Support;

use App\Models\m_machine;
use App\Models\prd_crp;
use Illuminate\Support\Facades\DB;

/**
 * Capacity Requirements Planning (CRP).
 * Loads MPS planned qty × routing cycle times → hours per process/machine,
 * then compares against available machine capacity.
 *
 * LLD §5.9
 */
class CrpService
{
    public function __construct(
        private PlanningService $planner,
        private WorkCalendarService $calendar,
    ) {}

    /**
     * Run CRP for a period.
     *
     * @return array{run_id: int, loads: array}
     */
    public function run(string $period): array
    {
        // MPS is scheduled by date, not stamped with a period, so the month is
        // derived from plan_date. The routing comes from the Work Order the line
        // will become — MPS itself does not pin one.
        $mpsItems = DB::table('prd_mps')
            ->whereRaw("DATE_FORMAT(plan_date, '%Y%m') = ?", [$period])
            ->where('status', 'APPROVED')
            ->get(['id', 'item_id', 'qty', 'proc_id', 'machine_id']);

        if ($mpsItems->isEmpty()) {
            return ['run_id' => null, 'loads' => []];
        }

        // Compute load per process/machine
        $loads = []; // process_id → machine_id → {load_hours, item_details}

        foreach ($mpsItems as $mps) {
            $qty = (int) $mps->qty;
            if ($qty <= 0) {
                continue;
            }

            // The item's default (top-priority) routing: MPS schedules an item,
            // the choice of an alternative routing is made on the Work Order.
            $routing = $this->planner->routing((int) $mps->item_id);

            foreach ($routing as $op) {
                $procId = $op['proc_id'];
                foreach ($op['machines'] as $m) {
                    $machineId = $m['machine_id'] ?? 0;
                    $cycleSec = max(0.01, (float) $m['cycle_sec']);
                    $hours = round($qty * $cycleSec / 3600, 2);

                    $key = "{$procId}_{$machineId}";
                    if (! isset($loads[$key])) {
                        $loads[$key] = [
                            'process_id' => $procId,
                            'machine_id' => $machineId,
                            'load_hours' => 0,
                            'items' => [],
                        ];
                    }
                    $loads[$key]['load_hours'] += $hours;
                    $loads[$key]['items'][] = [
                        'item_id' => (int) $mps->item_id,
                        'plan_qty' => $qty,
                        'cycle_sec' => $cycleSec,
                        'hours' => $hours,
                    ];
                }
            }
        }

        // Get machine available hours
        $machines = m_machine::where('active', 1)->get()->keyBy('id');

        $results = [];
        foreach ($loads as $l) {
            $machineId = $l['machine_id'];
            $machine = $machineId ? ($machines[$machineId] ?? null) : null;

            /*
             * Capacity from the working calendar.
             *
             * This used to read `$machine->available_hours`, a column that does
             * not exist — so it always fell through to 24 × 24 = 576 hours a
             * month, and every machine looked comfortably under-loaded. Real
             * capacity is the machine's daily hours across the days the plant
             * actually runs, holidays excluded.
             */
            $availableHours = $machineId
                ? $this->calendar->machineHours((int) $machineId, $period)
                : $this->calendar->plantHours($period);

            $loadPct = $availableHours > 0
                ? round($l['load_hours'] / $availableHours * 100, 1)
                : 0;

            $results[] = [
                'process_id' => $l['process_id'],
                'process_code' => DB::table('m_process')->where('id', $l['process_id'])->value('code'),
                'machine_id' => $machineId ?: null,
                'machine_code' => $machine?->code,
                'line_id' => $machine?->line_id,
                'load_hours' => round($l['load_hours'], 2),
                'available_hours' => round($availableHours, 1),
                'load_pct' => $loadPct,
                'status' => $loadPct > 100 ? 'OVERLOAD' : ($loadPct > 80 ? 'WARNING' : 'OK'),
                'items' => $l['items'],
            ];
        }

        // Persist one row per process/machine
        $crpIds = DB::transaction(function () use ($period, $results) {
            $ids = [];
            foreach ($results as $r) {
                $availableHours = $r['available_hours'];
                $crp = prd_crp::create([
                    'period' => $period,
                    'basis' => 'MPS',
                    'process_id' => $r['process_id'],
                    'machine_id' => $r['machine_id'] ?? 0,
                    'load_hours' => $r['load_hours'],
                    'capacity_hours' => $availableHours,
                ]);
                $ids[] = $crp->id;
            }

            return $ids;
        });

        return [
            'run_id' => $crpIds[0] ?? null,
            'loads' => $results,
            'lines' => $this->groupByLine($results, $period),
        ];
    }

    /**
     * Beban per lintasan produksi.
     *
     * Kapasitas sesungguhnya dinilai per lintasan, bukan per mesin: satu mesin
     * menganggur di lintasan yang penuh tidak menolong apa-apa kalau ia tidak
     * bisa mengambil alih pekerjaan mesin sebelahnya. Mesin yang belum
     * dimasukkan ke lintasan mana pun dikumpulkan apa adanya — menyembunyikannya
     * akan membuat jumlah jamnya tidak cocok dengan daftar di atasnya.
     *
     * @param  array<int, array<string, mixed>>  $results
     * @return array<int, array<string, mixed>>
     */
    private function groupByLine(array $results, string $period): array
    {
        $lines = DB::table('m_production_line')->where('active', 1)->get()->keyBy('id');

        $grouped = [];

        foreach ($results as $r) {
            $key = $r['line_id'] ?: 0;

            if (! isset($grouped[$key])) {
                $line = $key ? ($lines[$key] ?? null) : null;

                $grouped[$key] = [
                    'line_id' => $key ?: null,
                    'line' => $line ? "{$line->code} — {$line->name}" : '(belum masuk lintasan)',
                    'machines' => 0,
                    'load_hours' => 0.0,
                    'available_hours' => 0.0,
                ];
            }

            $grouped[$key]['machines']++;
            $grouped[$key]['load_hours'] += $r['load_hours'];
            $grouped[$key]['available_hours'] += $r['available_hours'];
        }

        return collect($grouped)->map(function ($g) {
            $pct = $g['available_hours'] > 0
                ? round($g['load_hours'] / $g['available_hours'] * 100, 1)
                : 0;

            return $g + [
                'load_hours' => round($g['load_hours'], 2),
                'available_hours' => round($g['available_hours'], 1),
                'load_pct' => $pct,
                'status' => $pct > 100 ? 'OVERLOAD' : ($pct > 80 ? 'WARNING' : 'OK'),
            ];
        })->sortByDesc('load_pct')->values()->all();
    }
}
