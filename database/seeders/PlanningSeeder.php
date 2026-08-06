<?php

namespace Database\Seeders;

use App\Support\CrpService;
use App\Support\MrpService;
use App\Support\WorkCalendarService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanningSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Menyemai Planning & Work Orders (100+ Work Orders)…');

        $this->seedMpp();
        $this->seedMps();
        $this->seedMrp();
        $this->seedCrp();
        $this->seedWorkOrders();

        $this->command?->info('  Planning & Work Orders (100+ WO) disemai.');
    }

    private function seedMpp(): void
    {
        /*
         * One planned quantity per item per month — the table enforces that
         * with a unique key, so each finished good appears once per period.
         *
         * Three months are planned because the MPP screen is a rolling
         * three-month matrix: seeding only the current month would leave two
         * thirds of it blank. The current month is approved and already in
         * production; the two ahead are still drafts, which is what the approve
         * action is there to act on.
         */
        /*
         * This month and next are approved — MRP only explodes approved plans,
         * and forward demand is what gives the mid-month re-run something to
         * buy for. The third month stays draft so the approve action has work.
         */
        $periods = [
            DemoCalendar::period() => 'APPROVED',
            DemoCalendar::nextPeriod(1) => 'APPROVED',
            DemoCalendar::nextPeriod(2) => 'DRAFT',
        ];

        $offset = 0;
        foreach ($periods as $period => $status) {
            for ($i = 1; $i <= 40; $i++) {
                $fgId = 70 + $i;

                DB::table('prd_mpp')->updateOrInsert(
                    ['period' => $period, 'item_id' => $fgId],
                    [
                        // Demand is planned to grow gently month on month.
                        'plan_qty' => 500 + ($i * 25) + ($offset * 60),
                        'status' => $status,
                        'created_at' => DemoCalendar::date(2),
                        'updated_at' => DemoCalendar::date(2),
                    ]
                );
            }
            $offset++;
        }
    }

    private function seedMps(): void
    {
        /*
         * MPS breaks the monthly plan into dated lots on a specific machine.
         * Cutting is the first operation for pipe, so that is what the schedule
         * is built around; 100 lots spread across the month's working days.
         */
        // Only real working days — scheduling a lot onto a national holiday is
        // exactly what the working calendar exists to prevent.
        $workingDays = app(WorkCalendarService::class)->workingDates(DemoCalendar::period());

        for ($i = 1; $i <= 100; $i++) {
            $fgId = 70 + (($i % 40) + 1);

            SeedWriter::put('prd_mps', ['id' => $i,
                'item_id' => $fgId,
                'proc_id' => 1,                              // Cutting
                'machine_id' => (($i % 3) + 1),              // the three saws
                'plan_date' => $workingDays[$i % count($workingDays)],
                'qty' => 200 + ($i * 5),
                'status' => 'APPROVED',
                'created_at' => DemoCalendar::date(3),
                'updated_at' => DemoCalendar::date(3),
            ]);
        }
    }

    /**
     * The first MRP of the month, run for real.
     *
     * Earlier versions of this seeder invented the numbers, which meant the
     * demo showed requirements that no bill of material actually produced. It
     * now calls the same engine the button calls: the approved MPP is exploded
     * through each product's BOM, converted to whole bars, and netted against
     * stock and open orders.
     *
     * At this point in the seed nothing has been bought yet, so this run shows
     * the full requirement — which is exactly what a plan looks like on the
     * first working day. A second run after purchasing exists is seeded at the
     * end of the chain to show the netting doing its job.
     */
    private function seedMrp(): void
    {
        $periods = [
            DemoCalendar::period(),
            DemoCalendar::nextPeriod(1),
            DemoCalendar::nextPeriod(2),
        ];

        $main = app(MrpService::class)->run($periods);

        DB::table('prd_mrp_main')->where('id', $main->id)->update([
            'run_date' => DemoCalendar::date(3),
            'user_id' => 1,
            'created_at' => DemoCalendar::date(3),
        ]);

        $rows = DB::table('prd_mrp_detail')->where('main_id', $main->id)->count();
        $this->command?->info("  MRP run #{$main->id}: {$rows} baris kebutuhan dari MPP disetujui.");
    }

    /**
     * Capacity planning, run for real.
     *
     * The numbers used to be invented, which meant the demo showed a capacity
     * of 160 hours for every machine regardless of the calendar. It now calls
     * the same engine the button calls, so the load comes from the approved
     * MPS and the capacity from the working calendar — including the two
     * machines that only run one shift.
     */
    private function seedCrp(): void
    {
        $result = app(CrpService::class)->run(DemoCalendar::period());
        $rows = count($result['loads'] ?? []);

        $this->command?->info("  CRP: {$rows} baris beban proses/mesin dari MPS disetujui.");
    }

    private function seedWorkOrders(): void
    {
        $wos = [];
        $woRms = [];
        $woPms = [];

        for ($i = 1; $i <= 100; $i++) {
            $day = (($i - 1) % 10) + 4; // Days 4..13
            $cusId = 60 + (($i % 50) + 1);
            $soId = sprintf('SO-2026-%03d', $i);
            $fgIdx = (($i % 40) + 1);
            $fgId = 70 + $fgIdx; // FG Item 71..110
            $rmId = $fgIdx;      // RM Item 1..40
            $pmId = 40 + (($fgIdx % 30) + 1); // PM Item 41..70

            $wos[] = [
                'id' => $i,
                'code' => sprintf('WO-2026-%03d', $i),
                'date' => DemoCalendar::date($day)->toDateString(),
                'customer_id' => $cusId,
                'so_id' => $soId,
                'fg_id' => $fgId,
                'user_id' => 1,
                'qty' => 500 + ($i * 10),
                'mps_id' => $i,                  // the schedule lot it came from
                'process_main_id' => ($i % 3) + 1,  // routing chosen for this run
                // Work Order status is numeric: 1 Draft, 2 Released, 3 Closed.
                // The last ten stay in draft so the release action has something
                // to act on in the demo.
                'status' => $i > 90 ? 1 : 2,
                'no_cut' => 0,
                'for_pm' => 0,
                'created_at' => DemoCalendar::date($day),
                'updated_at' => DemoCalendar::date($day),
            ];

            $woRms[] = [
                'id' => $i,
                'main_id' => $i,
                'rm_id' => $rmId,
                'note' => sprintf('Pipa Raw Material #%d (Alokasi WO-%03d)', $rmId, $i),
                'created_at' => DemoCalendar::date($day),
            ];

            $woPms[] = [
                'id' => $i,
                'main_id' => $i,
                'code_tr' => sprintf('PM-WO-%03d', $i),
                'pm_id' => $pmId,
                'note' => sprintf('Part Component #%d (Alokasi WO-%03d)', $pmId, $i),
                'created_at' => DemoCalendar::date($day),
            ];
        }

        foreach (array_chunk($wos, 50) as $chunk) {
            foreach ($chunk as $wo) {
                SeedWriter::put('prd_wo_main', $wo);
            }
        }
        foreach (array_chunk($woRms, 50) as $chunk) {
            foreach ($chunk as $wrm) {
                SeedWriter::put('prd_wo_detail_rm', $wrm);
            }
        }
        foreach (array_chunk($woPms, 50) as $chunk) {
            foreach ($chunk as $wpm) {
                SeedWriter::put('prd_wo_detail_pm', $wpm);
            }
        }
    }
}
