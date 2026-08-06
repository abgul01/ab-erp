<?php

namespace Database\Seeders;

use App\Support\MrpService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A second MRP run, late in the month, and the requisition it produces.
 *
 * This is the run worth looking at: by now there is stock on the racks,
 * purchase orders still in transit, and requisitions already raised, so the
 * engine has something to net against. The difference between this run and the
 * one at the start of the month is the whole point of MRP — it asks for what is
 * genuinely missing, not for the full requirement over again.
 *
 * The requisition is created through the same service the "Buat PR" button
 * calls, so what the demo shows is what the application actually does.
 */
class ReplanSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Menjalankan ulang MRP setelah stok & PO ada, lalu membuat PR dari hasilnya…');

        $periods = [
            DemoCalendar::period(),
            DemoCalendar::nextPeriod(1),
            DemoCalendar::nextPeriod(2),
        ];

        $mrp = app(MrpService::class)->run($periods);

        DB::table('prd_mrp_main')->where('id', $mrp->id)->update([
            'run_date' => DemoCalendar::date(24),
            'user_id' => 1,
            'created_at' => DemoCalendar::date(24),
        ]);

        $shortfalls = DB::table('prd_mrp_detail')
            ->where('main_id', $mrp->id)
            ->where('suggestion', 'PR')
            ->where('net_req', '>', 0);

        $count = (clone $shortfalls)->count();

        if ($count === 0) {
            $this->command?->warn("  MRP run #{$mrp->id}: kebutuhan sudah tertutup stok & PO berjalan — tidak ada PR yang perlu dibuat.");

            return;
        }

        $pr = app(MrpService::class)->generatePr($mrp->id, 1);

        DB::table('prc_pr_main')->where('id', $pr['pr_id'])->update([
            'date' => DemoCalendar::date(24)->toDateString(),
            'created_at' => DemoCalendar::date(24),
            'updated_at' => DemoCalendar::date(24),
        ]);

        $this->command?->info(sprintf(
            '  MRP run #%d: %d material kurang → PR %s (%s pcs / %s kg), status DRAFT.',
            $mrp->id,
            $count,
            $pr['code'],
            number_format($pr['total_qty']),
            number_format($pr['total_kg'], 2)
        ));
    }
}
