<?php

namespace Database\Seeders;

use App\Models\m_bom;
use App\Models\m_contacts;
use App\Models\m_item;
use App\Models\m_item_customer;
use App\Models\prd_mpp;
use App\Models\prd_mps;
use App\Models\prd_wo_main;
use App\Models\sls_forecast;
use App\Models\sls_so_main;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Big picture" demo seed — a lot of interconnected rows so the whole
 * planning chain is visible at a glance:
 *
 *   Customers → Items (RM / PM / FG + BOM) → Forecast → Sales Order
 *   → MPP (3 bulan) → MPS (jadwal harian utk Gantt) → Work Order.
 *
 * Everything it creates is tagged with a *-DEMO- code prefix (items) or a
 * "CUSD" customer u_code, so the seeder is fully idempotent: it wipes its own
 * previous rows first, then rebuilds. Run standalone with:
 *   php artisan db:seed --class=Database\\Seeders\\DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    /** Months covered by the plan: current + next two (YYYYMM + first day). */
    private array $periods = [];

    public function run(): void
    {
        $base = Carbon::now()->startOfMonth();
        $this->periods = [
            $base->copy(),
            $base->copy()->addMonth(),
            $base->copy()->addMonths(2),
        ];

        $this->cleanup();

        $admin = User::where('username', 'admin')->firstOrFail();
        $catCustomer = $this->customerCategoryId();
        $matGroup = $this->groupId('Material');
        $fgGroup = $this->groupId('FG');

        $customers = $this->seedCustomers($catCustomer);
        $rm = $this->seedRawMaterials($matGroup);
        $pm = $this->seedAssemblyParts($matGroup);
        $fgs = $this->seedFinishedGoods($fgGroup, $rm, $pm, $customers);

        $this->seedForecast($fgs, $customers);
        $this->seedSalesOrders($fgs, $customers, $admin);
        $this->seedPlanningChain($fgs, $customers, $admin);

        $this->command?->info(sprintf(
            'Demo: %d customer, %d RM, %d PM, %d FG, plus Forecast/SO/MPP/MPS/WO utk 3 bulan (%s).',
            count($customers), count($rm), count($pm), count($fgs),
            collect($this->periods)->map(fn ($p) => $p->format('Y-m'))->implode(', ')
        ));
    }

    /* ---------------- master lookups ---------------- */

    private function customerCategoryId(): int
    {
        return (int) (DB::table('m_cont_categ')->where('name', 'Customer')->value('id')
            ?: DB::table('m_cont_categ')->insertGetId(['name' => 'Customer']));
    }

    private function groupId(string $name): int
    {
        $id = DB::table('m_i_category')->where('name_c', $name)->value('id');
        if (! $id) {
            $id = DB::table('m_i_category')->insertGetId(['name_c' => $name, 'updated_at' => now(), 'created_at' => now()]);
        }

        return (int) $id;
    }

    /* ---------------- cleanup (idempotency) ---------------- */

    private function cleanup(): void
    {
        $fgIds = m_item::where('code', 'like', 'FG-DEMO-%')->pluck('id')->all();
        $itemIds = m_item::where('code', 'like', '%-DEMO-%')->pluck('id')->all();
        $bomIds = m_bom::whereIn('item_id', $fgIds ?: [0])->pluck('id')->all();
        $woIds = prd_wo_main::where('code', 'like', 'WO-DEMO-%')->pluck('id')->all();
        $soIds = sls_so_main::where('code', 'like', 'SO-DEMO-%')->pluck('id')->all();

        DB::table('prd_wo_detail_rm')->whereIn('main_id', $woIds ?: [0])->delete();
        DB::table('prd_wo_detail_pm')->whereIn('main_id', $woIds ?: [0])->delete();
        prd_wo_main::whereIn('id', $woIds ?: [0])->delete();

        prd_mps::whereIn('item_id', $fgIds ?: [0])->delete();
        prd_mpp::whereIn('item_id', $fgIds ?: [0])->delete();

        DB::table('sls_so_detail')->whereIn('main_id', $soIds ?: [0])->delete();
        sls_so_main::whereIn('id', $soIds ?: [0])->delete();

        sls_forecast::whereIn('item_id', $fgIds ?: [0])->delete();
        m_item_customer::whereIn('item_id', $fgIds ?: [0])->delete();

        DB::table('m_bom_det_rm')->whereIn('id_prim', $bomIds ?: [0])->delete();
        DB::table('m_bom_det_pm')->whereIn('id_prim', $bomIds ?: [0])->delete();
        m_bom::whereIn('id', $bomIds ?: [0])->delete();

        m_item::whereIn('id', $itemIds ?: [0])->delete();
        m_contacts::where('u_code', 'like', 'CUSD%')->delete();
    }

    /* ---------------- customers ---------------- */

    /** @return array<int, int> index → m_contacts id */
    private function seedCustomers(int $catId): array
    {
        $rows = [
            ['CUSD01', 'PT Astra Daihatsu Motor', 'ADM'],
            ['CUSD02', 'PT Toyota Motor Manufacturing Indonesia', 'TMMIN'],
            ['CUSD03', 'PT Mitsubishi Krama Yudha', 'MKM'],
            ['CUSD04', 'PT Hino Motors Manufacturing Indonesia', 'HMMI'],
            ['CUSD05', 'PT Isuzu Astra Motor Indonesia', 'IAMI'],
        ];
        $ids = [];
        foreach ($rows as [$code, $name, $nick]) {
            $ids[] = m_contacts::create([
                'u_code' => $code, 'company_n' => $name, 'nick_n' => $nick,
                'category_id' => $catId, 'active' => 1,
            ])->id;
        }

        return $ids;
    }

    /* ---------------- raw materials ---------------- */

    /** @return array<string, int> rm code → item id */
    private function seedRawMaterials(int $matGroup): array
    {
        // [code, name, type, o_d, thick, length, weight]
        $rows = [
            ['RM-DEMO-STK-38x6000', 'Steel Tube OD38 t3.2', 'Pipe', 38.0, 3.2, 6000, 16.20],
            ['RM-DEMO-STK-42x6000', 'Steel Tube OD42 t3.0', 'Pipe', 42.0, 3.0, 6000, 17.30],
            ['RM-DEMO-STK-50x6000', 'Steel Tube OD50 t4.0', 'Pipe', 50.0, 4.0, 6000, 27.20],
            ['RM-DEMO-STK-60x6000', 'Steel Tube OD60 t4.5', 'Pipe', 60.0, 4.5, 6000, 36.90],
            ['RM-DEMO-BAR-25x4000', 'Round Bar OD25', 'Roundbar', 25.0, 0, 4000, 15.40],
            ['RM-DEMO-BAR-32x4000', 'Round Bar OD32', 'Roundbar', 32.0, 0, 4000, 25.20],
        ];
        $ids = [];
        foreach ($rows as [$code, $name, $type, $od, $thick, $len, $wt]) {
            $ids[$code] = m_item::create([
                'code' => $code, 'part_name' => $name, 'type' => $type,
                'descrip' => 'Raw material ' . $name, 'category_id' => $matGroup,
                'o_d' => $od, 'thick' => $thick, 'length' => $len, 'weight' => $wt,
                'min_stock' => 20, 'max_stock' => 500, 'pm' => 0, 'active' => 1,
            ])->id;
        }

        return $ids;
    }

    /* ---------------- assembly / purchased parts (PM) ---------------- */

    /** @return array<string, int> pm code → item id */
    private function seedAssemblyParts(int $matGroup): array
    {
        $rows = [
            ['PM-DEMO-BOLT-M8', 'Bolt M8 x 20', 'Other'],
            ['PM-DEMO-NUT-M8', 'Nut M8', 'Other'],
            ['PM-DEMO-WASHER-8', 'Washer 8mm', 'Other'],
            ['PM-DEMO-CAP-42', 'End Cap OD42', 'Other'],
            ['PM-DEMO-BRKT-L01', 'Bracket L-Type', 'Plat Bar'],
        ];
        $ids = [];
        foreach ($rows as [$code, $name, $type]) {
            $ids[$code] = m_item::create([
                'code' => $code, 'part_name' => $name, 'type' => $type,
                'descrip' => 'Assembly part ' . $name, 'category_id' => $matGroup,
                'min_stock' => 100, 'max_stock' => 5000, 'pm' => 1, 'active' => 1,
            ])->id;
        }

        return $ids;
    }

    /* ---------------- finished goods + BOM + customer links ---------------- */

    /**
     * @param  array<string, int>  $rm
     * @param  array<string, int>  $pm
     * @param  array<int, int>  $customers
     * @return array<int, array{id:int, code:string, month_qty:int, cus:array<int,int>}>
     */
    private function seedFinishedGoods(int $fgGroup, array $rm, array $pm, array $customers): array
    {
        // [code, name, type, rm_code, len_use, pm=[[code,qty]], cus_idx=[...], month_qty]
        $defs = [
            ['FG-DEMO-BOSS-001', 'Boss Steering Axle S', 'Pipe', 'RM-DEMO-STK-42x6000', 120, [], [0, 1], 900],
            ['FG-DEMO-BOSS-002', 'Boss Steering Axle L', 'Pipe', 'RM-DEMO-STK-50x6000', 150, [], [0], 700],
            ['FG-DEMO-COLLAR-01', 'Collar Axle 38', 'Pipe', 'RM-DEMO-STK-38x6000', 80, [], [1, 2], 1200],
            ['FG-DEMO-COLLAR-02', 'Collar Axle 42', 'Pipe', 'RM-DEMO-STK-42x6000', 95, [], [2], 1000],
            ['FG-DEMO-SLEEVE-01', 'Sleeve Suspension S', 'Pipe', 'RM-DEMO-STK-60x6000', 200, [['PM-DEMO-CAP-42', 2]], [3], 600],
            ['FG-DEMO-SLEEVE-02', 'Sleeve Suspension L', 'Pipe', 'RM-DEMO-STK-60x6000', 240, [['PM-DEMO-CAP-42', 2]], [3, 4], 550],
            ['FG-DEMO-PIN-01', 'Pin Joint 25', 'Roundbar', 'RM-DEMO-BAR-25x4000', 60, [], [4], 1500],
            ['FG-DEMO-PIN-02', 'Pin Joint 32', 'Roundbar', 'RM-DEMO-BAR-32x4000', 75, [], [0, 4], 1300],
            ['FG-DEMO-BRACKET-A', 'Bracket Assy A', 'Pipe', 'RM-DEMO-STK-38x6000', 100, [['PM-DEMO-BRKT-L01', 1], ['PM-DEMO-BOLT-M8', 4], ['PM-DEMO-NUT-M8', 4]], [1], 800],
            ['FG-DEMO-FLANGE-01', 'Flange Ring 60', 'Pipe', 'RM-DEMO-STK-60x6000', 45, [['PM-DEMO-BOLT-M8', 6], ['PM-DEMO-WASHER-8', 6]], [2, 3], 750],
        ];

        $out = [];
        foreach ($defs as [$code, $name, $type, $rmCode, $lenUse, $pmList, $cusIdx, $monthQty]) {
            $rmItem = m_item::find($rm[$rmCode]);
            $fg = m_item::create([
                'code' => $code, 'part_name' => $name, 'type' => $type,
                'descrip' => 'Finished good ' . $name, 'category_id' => $fgGroup,
                'o_d' => $rmItem->o_d, 'thick' => $rmItem->thick,
                'length' => $lenUse, 'length_cut' => $lenUse + 2,
                'min_stock' => 0, 'max_stock' => 0, 'pm' => 0, 'active' => 1,
            ]);

            // BOM: one RM line + optional PM lines
            $bom = m_bom::create(['item_id' => $fg->id, 'active' => 1]);
            DB::table('m_bom_det_rm')->insert([
                'id_prim' => $bom->id, 'mat_id' => $rm[$rmCode],
                'length_cut' => $lenUse + 2, 'length_use' => $lenUse, 'priority' => 1,
            ]);
            foreach ($pmList as [$pmCode, $qty]) {
                DB::table('m_bom_det_pm')->insert([
                    'id_prim' => $bom->id, 'pm_id' => $pm[$pmCode], 'qty' => $qty,
                ]);
            }

            // Customer links (needed before SO/forecast)
            foreach ($cusIdx as $prio => $ci) {
                m_item_customer::create([
                    'item_id' => $fg->id, 'cus_id' => $customers[$ci],
                    'priority' => $prio + 1, 'active' => 1,
                ]);
            }

            $out[] = [
                'id' => $fg->id, 'code' => $code, 'month_qty' => $monthQty,
                'cus' => array_map(fn ($ci) => $customers[$ci], $cusIdx),
                'rm_id' => $rm[$rmCode], 'pm_ids' => array_map(fn ($p) => $pm[$p[0]], $pmList),
            ];
        }

        return $out;
    }

    /* ---------------- forecast ---------------- */

    /** @param array<int, array> $fgs */
    private function seedForecast(array $fgs, array $customers): void
    {
        foreach ($fgs as $fg) {
            foreach ($fg['cus'] as $ci => $cusId) {
                // demand shared across this FG's customers
                $share = intdiv($fg['month_qty'], max(1, count($fg['cus'])));
                foreach ($this->periods as $pi => $p) {
                    $period = $p->format('Ym');
                    // a slight month-over-month growth so numbers vary
                    $qty = (int) round($share * (1 + 0.05 * $pi));
                    sls_forecast::create([
                        'cus_id' => $cusId, 'item_id' => $fg['id'],
                        'period' => $period, 'version' => 'FINAL', 'qty' => $qty,
                    ]);
                    // an earlier revision for the first month, to show versioning
                    if ($pi === 0) {
                        sls_forecast::create([
                            'cus_id' => $cusId, 'item_id' => $fg['id'],
                            'period' => $period, 'version' => 'N-1', 'qty' => (int) round($qty * 0.9),
                        ]);
                    }
                }
            }
        }
    }

    /* ---------------- sales orders ---------------- */

    /** @param array<int, array> $fgs */
    private function seedSalesOrders(array $fgs, array $customers, User $admin): void
    {
        // group FGs by customer so each SO only carries that customer's items
        $byCustomer = [];
        foreach ($fgs as $fg) {
            foreach ($fg['cus'] as $cusId) {
                $byCustomer[$cusId][] = $fg;
            }
        }

        $seq = 0;
        foreach ($byCustomer as $cusId => $items) {
            // two SOs per customer: one in month 1, one in month 2
            foreach ([0, 1] as $pi) {
                $seq++;
                $date = $this->periods[$pi]->copy()->addDays(3 + $seq);
                $so = sls_so_main::create([
                    'code' => sprintf('SO-DEMO-%s-%04d', $this->periods[$pi]->format('Ym'), $seq),
                    'date' => $date->toDateString(),
                    'cus_id' => $cusId,
                    'cus_po_no' => sprintf('PO-%s-%03d', $this->periods[$pi]->format('ym'), $seq),
                    'currency_id' => null,
                    'user_id' => $admin->id,
                    'status' => 'APPROVED',
                ]);
                foreach (array_slice($items, 0, 3) as $li => $fg) {
                    $so->detail()->create([
                        'item_id' => $fg['id'],
                        'qty' => (int) round($fg['month_qty'] / 3),
                        'price' => 15000 + $li * 2500,
                        'due_date' => $date->copy()->addDays(20)->toDateString(),
                        'qty_delivered' => 0,
                    ]);
                }
            }
        }
    }

    /* ---------------- MPP → MPS → WO ---------------- */

    /** @param array<int, array> $fgs */
    private function seedPlanningChain(array $fgs, array $customers, User $admin): void
    {
        $woSeq = 0;

        foreach ($fgs as $fg) {
            foreach ($this->periods as $pi => $p) {
                $period = $p->format('Ym');
                $monthQty = (int) round($fg['month_qty'] * (1 + 0.05 * $pi));

                // MPP: one approved row per FG per month
                prd_mpp::create([
                    'period' => $period, 'item_id' => $fg['id'],
                    'plan_qty' => $monthQty, 'status' => 'APPROVED',
                ]);

                // MPS: split the month into 4 dated buckets (days 4,11,18,25)
                // so the Gantt shows several bars. Months 1-2 approved (ready
                // for WO); month 3 left DRAFT (draggable/plannable in the UI).
                $status = $pi < 2 ? 'APPROVED' : 'DRAFT';
                $buckets = $this->split($monthQty, 4);
                $days = [4, 11, 18, 25];
                $mpsRows = [];
                foreach ($buckets as $bi => $qty) {
                    if ($qty <= 0) {
                        continue;
                    }
                    $mpsRows[] = prd_mps::create([
                        'plan_date' => $p->copy()->addDays($days[$bi] - 1)->toDateString(),
                        'item_id' => $fg['id'], 'qty' => $qty,
                        'machine_id' => null, 'status' => $status,
                    ]);
                }

                // WO: only from approved MPS, and only for month 1 to keep the
                // WO list readable. One WO per MPS bucket, exploded from the BOM.
                if ($pi === 0) {
                    foreach ($mpsRows as $mps) {
                        $woSeq++;
                        $this->createWo($fg, $mps, $customers, $admin, $period, $woSeq);
                    }
                }
            }
        }
    }

    /**
     * Build a DRAFT Work Order from an approved MPS: header + BOM explosion
     * (detail_rm / detail_pm). No serials — RM stock (WMS) isn't seeded here,
     * so each line simply shows its shortage until booked.
     *
     * @param  array  $fg
     * @param  array<int, int>  $customers
     */
    private function createWo(array $fg, prd_mps $mps, array $customers, User $admin, string $period, int $seq): void
    {
        $customerId = $fg['cus'][0] ?? $customers[0];

        $wo = prd_wo_main::create([
            'code' => sprintf('WO-DEMO-%s-%04d', $period, $seq),
            'date' => $mps->plan_date,
            'customer_id' => $customerId,
            'so_id' => '-',
            'fg_id' => $fg['id'],
            'mps_id' => $mps->id,
            'user_id' => $admin->id,
            'qty' => $mps->qty,
            'status' => 1, // DRAFT
            'no_cut' => 0,
            'for_pm' => 0,
        ]);

        DB::table('prd_wo_detail_rm')->insert([
            'main_id' => $wo->id, 'rm_id' => $fg['rm_id'], 'note' => null,
        ]);
        foreach ($fg['pm_ids'] as $pmId) {
            DB::table('prd_wo_detail_pm')->insert([
                'main_id' => $wo->id, 'pm_id' => $pmId, 'code_tr' => '-', 'note' => null,
            ]);
        }
    }

    /** Split a total into $n near-equal integer buckets (remainder on the last). */
    private function split(int $total, int $n): array
    {
        $base = intdiv($total, $n);
        $out = array_fill(0, $n, $base);
        $out[$n - 1] += $total - $base * $n;

        return $out;
    }
}
