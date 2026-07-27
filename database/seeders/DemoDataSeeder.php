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
use App\Support\CostingService;
use App\Support\GlPostingService;
use App\Support\JournalEngine;
use App\Support\LineTax;
use App\Support\PricelistService;
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

    /** route signature (e.g. "CUT-MCH-CHM") → m_process_main id, memoized. */
    private array $routeTemplates = [];

    public function run(): void
    {
        $base = Carbon::now()->startOfMonth();
        $this->periods = [
            $base->copy(),
            $base->copy()->addMonth(),
            $base->copy()->addMonths(2),
        ];

        $this->cleanup();
        $this->routeTemplates = [];

        $admin = User::where('username', 'admin')->firstOrFail();
        $catCustomer = $this->customerCategoryId();
        $matGroup = $this->groupId('Material');
        $fgGroup = $this->groupId('FG');

        $machines = $this->seedMachines();
        $procs = DB::table('m_process')->pluck('id', 'code')->all();

        $customers = $this->seedCustomers($catCustomer);
        $rm = $this->seedRawMaterials($matGroup);
        $pm = $this->seedAssemblyParts($matGroup);
        $fgs = $this->seedFinishedGoods($fgGroup, $rm, $pm, $customers, $procs);

        $this->seedCycleTimes($fgs, $machines, $procs);
        $this->seedForecast($fgs, $customers);
        $this->seedPricelists($fgs, $admin);
        $this->seedSalesOrders($fgs, $customers, $admin);
        $this->seedPlanningChain($fgs, $customers, $admin);
        $this->seedFgFulfilment($admin);
        $this->seedCosting($machines, $procs);
        $this->seedAccounting($admin);

        $this->command?->info(sprintf(
            'Demo: %d customer, %d RM, %d PM, %d FG + routing/cycle-time, plus Pricelist/Forecast/SO/MPP/MPS/WO/FG-stock/DO/Invoice/COGM/Aset utk 3 bulan (%s).',
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

        // Shop-floor transactions tied to these WOs' WIPs. Processing hangs off
        // cutting, and downtime/abnormality hang off both, so they go first —
        // otherwise re-seeding leaves orphans pointing at deleted rows.
        $wipIds = DB::table('prd_wip')->whereIn('wo_id', $woIds ?: [0])->pluck('id')->all();

        $proIds = DB::table('tr_pro_main')->whereIn('wip_id', $wipIds ?: [0])->pluck('id')->all();
        $proDetIds = DB::table('tr_pro_detail')->whereIn('main_id', $proIds ?: [0])->pluck('id')->all();
        $abProIds = DB::table('tr_ab_pro')->whereIn('pro_id', $proIds ?: [0])->pluck('id')->all();
        DB::table('tr_ng_pro')->whereIn('main_id', $abProIds ?: [0])->delete();
        DB::table('tr_repair_pro')->whereIn('main_id', $abProIds ?: [0])->delete();
        DB::table('tr_ab_pro')->whereIn('id', $abProIds ?: [0])->delete();
        DB::table('tr_dt_pro_detail')->whereIn('main_id', DB::table('tr_dt_pro_main')->whereIn('pro_id', $proIds ?: [0])->pluck('id') ?: [0])->delete();
        DB::table('tr_dt_pro_main')->whereIn('pro_id', $proIds ?: [0])->delete();
        DB::table('tr_pro_pallet')->whereIn('detail_id', $proDetIds ?: [0])->delete();
        DB::table('tr_pro_pal_pr')->whereIn('pro_id', $proIds ?: [0])->delete();
        DB::table('tr_pro_detail')->whereIn('main_id', $proIds ?: [0])->delete();
        DB::table('tr_pro_main')->whereIn('id', $proIds ?: [0])->delete();

        $cutIds = DB::table('tr_cut_main')->whereIn('wip_id', $wipIds ?: [0])->pluck('id')->all();
        $abCutIds = DB::table('tr_ab_cut_main')->whereIn('cut_id', $cutIds ?: [0])->pluck('id')->all();
        DB::table('tr_ng_cut')->whereIn('main_id', $abCutIds ?: [0])->delete();
        DB::table('tr_repair_cut')->whereIn('main_id', $abCutIds ?: [0])->delete();
        DB::table('tr_ab_cut_det')->whereIn('main_id', $abCutIds ?: [0])->delete();
        DB::table('tr_ab_cut_main')->whereIn('id', $abCutIds ?: [0])->delete();
        DB::table('tr_dt_cut_detail')->whereIn('main_id', DB::table('tr_dt_cut_main')->whereIn('cut_id', $cutIds ?: [0])->pluck('id') ?: [0])->delete();
        DB::table('tr_dt_cut_main')->whereIn('cut_id', $cutIds ?: [0])->delete();
        $cutDetIds = DB::table('tr_cut_detail')->whereIn('main_id', $cutIds ?: [0])->pluck('id')->all();
        DB::table('tr_cut_serial')->whereIn('detail_id', $cutDetIds ?: [0])->delete();
        DB::table('tr_cut_detail')->whereIn('main_id', $cutIds ?: [0])->delete();
        DB::table('tr_cut_pal_pr')->whereIn('cut_id', $cutIds ?: [0])->delete();
        DB::table('tr_cut_main')->whereIn('id', $cutIds ?: [0])->delete();
        DB::table('prd_wip')->whereIn('wo_id', $woIds ?: [0])->delete();
        // drop any WIP left orphaned by earlier runs (its WO no longer exists)
        DB::table('prd_wip')->whereNotIn('wo_id', DB::table('prd_wo_main')->select('id'))->delete();

        // booked RM serials for those WOs
        $rmDetIds = DB::table('prd_wo_detail_rm')->whereIn('main_id', $woIds ?: [0])->pluck('id')->all();
        DB::table('prd_wo_serial_rm')->whereIn('detail_id', $rmDetIds ?: [0])->delete();

        DB::table('prd_wo_detail_rm')->whereIn('main_id', $woIds ?: [0])->delete();
        DB::table('prd_wo_detail_pm')->whereIn('main_id', $woIds ?: [0])->delete();
        prd_wo_main::whereIn('id', $woIds ?: [0])->delete();

        prd_mps::whereIn('item_id', $fgIds ?: [0])->delete();
        prd_mpp::whereIn('item_id', $fgIds ?: [0])->delete();

        // accounting: journals & payments are fully regenerated from documents
        DB::table('acc_journal_det')->whereIn('main_id', DB::table('acc_journal_main')->pluck('id'))->delete();
        DB::table('acc_journal_main')->delete();
        DB::table('acc_ap_pay_det')->delete();
        DB::table('acc_ap_pay_main')->delete();
        DB::table('acc_ar_rec_det')->delete();
        DB::table('acc_ar_rec_main')->delete();
        DB::table('ast_depre')->update(['journal_id' => null]);

        // costing & asset: COGM of demo WOs, rates for the planned periods, demo assets
        DB::table('cst_cogm')->whereIn('wo_id', $woIds ?: [0])->delete();
        DB::table('cst_rate')->whereIn('period', collect($this->periods)->map(fn ($p) => $p->format('Ym'))->all())->delete();
        $astIds = DB::table('ast_main')->where('code', 'like', 'AST-DEMO-%')->pluck('id')->all();
        DB::table('ast_depre')->whereIn('ast_id', $astIds ?: [0])->delete();
        DB::table('ast_main')->whereIn('id', $astIds ?: [0])->delete();

        // fulfilment: sales invoice → DO → FG warehouse moves (all demo-tagged)
        $invIds = DB::table('sls_inv_main')->where('code', 'like', 'SI-DEMO-%')->pluck('id')->all();
        DB::table('sls_inv_detail')->whereIn('main_id', $invIds ?: [0])->delete();
        DB::table('sls_inv_main')->whereIn('id', $invIds ?: [0])->delete();
        $doIds = DB::table('sls_do_main')->where('code', 'like', 'DO-DEMO-%')->pluck('id')->all();
        DB::table('sls_do_detail')->whereIn('main_id', $doIds ?: [0])->delete();
        DB::table('sls_do_main')->whereIn('id', $doIds ?: [0])->delete();
        $fgoIds = DB::table('tr_out_fg_main')->where('code', 'like', 'FGO-DEMO-%')->pluck('id')->all();
        DB::table('tr_out_fg_det')->whereIn('main_id', $fgoIds ?: [0])->delete();
        DB::table('tr_out_fg_main')->whereIn('id', $fgoIds ?: [0])->delete();
        $fgiIds = DB::table('tr_inc_fg_main')->where('code', 'like', 'FGI-DEMO-%')->pluck('id')->all();
        DB::table('tr_inc_fg_det')->whereIn('main_id', $fgiIds ?: [0])->delete();
        DB::table('tr_inc_fg_main')->whereIn('id', $fgiIds ?: [0])->delete();

        DB::table('sls_so_detail')->whereIn('main_id', $soIds ?: [0])->delete();
        sls_so_main::whereIn('id', $soIds ?: [0])->delete();

        // pricelists go after the SOs that reference their lines
        $plIds = DB::table('m_pricelist_main')->where('code', 'like', 'PL-DEMO-%')->pluck('id')->all();
        DB::table('m_pricelist_det')->whereIn('main_id', $plIds ?: [0])->delete();
        DB::table('m_pricelist_main')->whereIn('id', $plIds ?: [0])->delete();

        sls_forecast::whereIn('item_id', $fgIds ?: [0])->delete();
        m_item_customer::whereIn('item_id', $fgIds ?: [0])->delete();

        // routing time + routing steps
        DB::table('m_route_time')->whereIn('item_id', $fgIds ?: [0])->delete();
        $proIds = DB::table('m_bom_pro')->whereIn('item_id', $fgIds ?: [0])->pluck('id')->all();
        DB::table('m_bom_pro_det')->whereIn('id_prim', $proIds ?: [0])->delete();
        DB::table('m_bom_pro')->whereIn('item_id', $fgIds ?: [0])->delete();
        // shared routing templates (rebuilt each run)
        $rtIds = DB::table('m_process_main')->where('code', 'like', 'RT-DEMO-%')->pluck('id')->all();
        DB::table('m_process_main_det')->whereIn('main_id', $rtIds ?: [0])->delete();
        DB::table('m_process_main')->whereIn('id', $rtIds ?: [0])->delete();

        DB::table('m_bom_det_rm')->whereIn('id_prim', $bomIds ?: [0])->delete();
        DB::table('m_bom_det_pm')->whereIn('id_prim', $bomIds ?: [0])->delete();
        m_bom::whereIn('id', $bomIds ?: [0])->delete();

        m_item::whereIn('id', $itemIds ?: [0])->delete();
        m_contacts::where('u_code', 'like', 'CUSD%')->delete();
        // machines are deliberately kept: shop-floor transactions reference them
    }

    /* ---------------- machines ---------------- */

    /** @return array<string, array<int, int>> process role → machine ids */
    private function seedMachines(): array
    {
        // per process role: how many machines + a friendly name
        $fleet = [
            ['CUT', 3, 'Cutting Line'],
            ['MCH', 3, 'CNC Lathe'],
            ['CHM', 2, 'Chamfer Machine'],
            ['DRL', 2, 'Drilling Machine'],
            ['WLD', 2, 'Welding Station'],
        ];
        $defs = [];
        foreach ($fleet as [$role, $n, $label]) {
            for ($k = 1; $k <= $n; $k++) {
                $defs[] = [sprintf('MC-DEMO-%s-%d', $role, $k), "{$label} {$k}", $role];
            }
        }
        $makerId = (int) (DB::table('m_maker_m')->min('id') ?? 0);
        $out = [];
        foreach ($defs as [$code, $name, $role]) {
            // Machines are matched on code and never deleted: their ids are
            // referenced by live transactions (tr_cut_detail, tr_pro_detail),
            // so re-seeding must not renumber them.
            // m_machine has many NOT NULL columns without defaults (and no FKs) —
            // fill them with sane placeholders so the insert succeeds.
            DB::table('m_machine')->updateOrInsert(['code' => $code], [
                'name' => $name, 'model' => 'DEMO-' . $role, 'categ' => $role,
                'maker_id' => $makerId, 'min_d' => 0, 'max_d' => 0, 'func_id' => 0, 'serial' => $code,
                'y_made' => 2020, 'etd' => '2020-01-01', 'pic_jp_id' => 0, 'pic_local_id' => 0,
                'book_y_local' => '2020-01-01', 'deps_m' => 0, 'deps_exp' => '2020-01-01', 'kwh' => 0,
                'active' => 1, 'updated_at' => now(),
            ]);
            $out[$role][] = (int) DB::table('m_machine')->where('code', $code)->value('id');
        }

        return $out;
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
    private function seedFinishedGoods(int $fgGroup, array $rm, array $pm, array $customers, array $procs): array
    {
        // [code, name, type, rm_code, len_use, pm=[[code,qty]], cus_idx=[...], month_qty, route=[proc codes]]
        $defs = [
            ['FG-DEMO-BOSS-001', 'Boss Steering Axle S', 'Pipe', 'RM-DEMO-STK-42x6000', 120, [], [0, 1], 900, ['CUT', 'MCH', 'CHM']],
            ['FG-DEMO-BOSS-002', 'Boss Steering Axle L', 'Pipe', 'RM-DEMO-STK-50x6000', 150, [], [0], 700, ['CUT', 'MCH', 'CHM']],
            ['FG-DEMO-COLLAR-01', 'Collar Axle 38', 'Pipe', 'RM-DEMO-STK-38x6000', 80, [], [1, 2], 1200, ['CUT', 'MCH']],
            ['FG-DEMO-COLLAR-02', 'Collar Axle 42', 'Pipe', 'RM-DEMO-STK-42x6000', 95, [], [2], 1000, ['CUT', 'MCH', 'CHM']],
            ['FG-DEMO-SLEEVE-01', 'Sleeve Suspension S', 'Pipe', 'RM-DEMO-STK-60x6000', 200, [['PM-DEMO-CAP-42', 2]], [3], 600, ['CUT', 'MCH', 'WLD', 'CHM']],
            ['FG-DEMO-SLEEVE-02', 'Sleeve Suspension L', 'Pipe', 'RM-DEMO-STK-60x6000', 240, [['PM-DEMO-CAP-42', 2]], [3, 4], 550, ['CUT', 'MCH', 'WLD', 'CHM']],
            ['FG-DEMO-PIN-01', 'Pin Joint 25', 'Roundbar', 'RM-DEMO-BAR-25x4000', 60, [], [4], 1500, ['CUT', 'MCH']],
            ['FG-DEMO-PIN-02', 'Pin Joint 32', 'Roundbar', 'RM-DEMO-BAR-32x4000', 75, [], [0, 4], 1300, ['CUT', 'MCH', 'CHM']],
            ['FG-DEMO-BRACKET-A', 'Bracket Assy A', 'Pipe', 'RM-DEMO-STK-38x6000', 100, [['PM-DEMO-BRKT-L01', 1], ['PM-DEMO-BOLT-M8', 4], ['PM-DEMO-NUT-M8', 4]], [1], 800, ['CUT', 'MCH', 'DRL', 'WLD']],
            ['FG-DEMO-FLANGE-01', 'Flange Ring 60', 'Pipe', 'RM-DEMO-STK-60x6000', 45, [['PM-DEMO-BOLT-M8', 6], ['PM-DEMO-WASHER-8', 6]], [2, 3], 750, ['CUT', 'MCH', 'DRL']],
            ['FG-DEMO-SHAFT-01', 'Drive Shaft 42', 'Pipe', 'RM-DEMO-STK-42x6000', 300, [], [0], 1400, ['CUT', 'MCH', 'CHM']],
            ['FG-DEMO-SHAFT-02', 'Drive Shaft 50', 'Pipe', 'RM-DEMO-STK-50x6000', 350, [], [1], 1100, ['CUT', 'MCH', 'DRL', 'CHM']],
            ['FG-DEMO-RING-01', 'Spacer Ring 38', 'Pipe', 'RM-DEMO-STK-38x6000', 30, [], [2], 2000, ['CUT', 'MCH']],
            ['FG-DEMO-RING-02', 'Spacer Ring 50', 'Pipe', 'RM-DEMO-STK-50x6000', 35, [], [3], 1800, ['CUT', 'MCH']],
            ['FG-DEMO-JOINT-01', 'Yoke Joint 60', 'Pipe', 'RM-DEMO-STK-60x6000', 130, [['PM-DEMO-BOLT-M8', 4]], [4], 900, ['CUT', 'MCH', 'DRL', 'WLD', 'CHM']],
            ['FG-DEMO-JOINT-02', 'Yoke Joint 42', 'Pipe', 'RM-DEMO-STK-42x6000', 110, [['PM-DEMO-BOLT-M8', 2]], [0], 950, ['CUT', 'MCH', 'DRL', 'CHM']],
            ['FG-DEMO-BUSH-01', 'Bushing 32', 'Roundbar', 'RM-DEMO-BAR-32x4000', 45, [], [1, 2], 1600, ['CUT', 'MCH', 'CHM']],
            ['FG-DEMO-BUSH-02', 'Bushing 25', 'Roundbar', 'RM-DEMO-BAR-25x4000', 40, [], [3, 4], 1700, ['CUT', 'MCH']],
            // material shipped without processing: routing is just cut → FG
            ['FG-DEMO-SPACER-RAW', 'Spacer Potong 38 (tanpa proses)', 'Pipe', 'RM-DEMO-STK-38x6000', 25, [], [0], 500, ['CUT']],
        ];

        $out = [];
        foreach ($defs as [$code, $name, $type, $rmCode, $lenUse, $pmList, $cusIdx, $monthQty, $route]) {
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

            // Routing: link the FG to shared routing templates, ranked by
            // priority. Priority 1 is the primary route; some FGs also get an
            // alternate (an extra step) to show the multi-routing choice at WO.
            $idx = count($out);
            $mainId = $this->processMainForRoute($route, $procs);
            DB::table('m_bom_pro')->insert([
                'item_id' => $fg->id, 'process_main_id' => $mainId, 'priority' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($idx % 3 === 0) {
                $alt = $this->alternateRoute($route);
                if ($alt !== $route) {
                    DB::table('m_bom_pro')->insert([
                        'item_id' => $fg->id, 'process_main_id' => $this->processMainForRoute($alt, $procs),
                        'priority' => 2, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            // Customer links (needed before SO/forecast)
            foreach ($cusIdx as $prio => $ci) {
                m_item_customer::create([
                    'item_id' => $fg->id, 'cus_id' => $customers[$ci],
                    'priority' => $prio + 1, 'active' => 1,
                ]);
            }

            $out[] = [
                'id' => $fg->id, 'code' => $code, 'month_qty' => $monthQty, 'route' => $route,
                'process_main_id' => $mainId,   // priority-1 routing (WO default)
                'cus' => array_map(fn ($ci) => $customers[$ci], $cusIdx),
                'rm_id' => $rm[$rmCode], 'pm_ids' => array_map(fn ($p) => $pm[$p[0]], $pmList),
            ];
        }

        return $out;
    }

    /**
     * A reusable routing template for a process sequence, created once and
     * shared by every FG with the same route. Tagged RT-DEMO-* for cleanup.
     *
     * @param  array<int, string>  $route  ordered process codes
     * @param  array<string, int>  $procs  process code → id
     */
    private function processMainForRoute(array $route, array $procs): int
    {
        $sig = implode('-', $route);
        if (isset($this->routeTemplates[$sig])) {
            return $this->routeTemplates[$sig];
        }

        // every routing ends with the FG marker step, so "all items end at FG"
        $steps = array_merge($route, ['FG']);
        $mainId = DB::table('m_process_main')->insertGetId([
            'code' => substr("RT-DEMO-{$sig}", 0, 50),
            'name' => implode(' → ', $steps),
            'active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($steps as $i => $pc) {
            if (isset($procs[$pc])) {
                DB::table('m_process_main_det')->insert([
                    'main_id' => $mainId, 'proc_id' => $procs[$pc], 'sequence' => $i + 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        return $this->routeTemplates[$sig] = $mainId;
    }

    /**
     * A plausible alternate routing for an item: insert a drilling step before
     * the final process, or (if already present) add a welding step. Returns the
     * original route unchanged when neither applies.
     *
     * @param  array<int, string>  $route
     * @return array<int, string>
     */
    private function alternateRoute(array $route): array
    {
        if (! in_array('DRL', $route, true) && count($route) >= 2) {
            array_splice($route, count($route) - 1, 0, 'DRL');   // …→DRL→last

            return $route;
        }
        if (! in_array('WLD', $route, true) && count($route) >= 2) {
            array_splice($route, count($route) - 1, 0, 'WLD');

            return $route;
        }

        return $route;
    }

    /* ---------------- cycle time (item × process × machine) ---------------- */

    /**
     * Seed routing times. MCH (machining) is the slowest step → bottleneck that
     * drives daily capacity. Each process gets a priority-1 main machine plus a
     * slightly slower priority-2 alternate, to show the priority scale.
     *
     * @param  array<int, array>  $fgs
     * @param  array<string, array<int, int>>  $machines
     * @param  array<string, int>  $procs
     */
    private function seedCycleTimes(array $fgs, array $machines, array $procs): void
    {
        // base cycle sec/pc per process type (MCH is the heavy bottleneck)
        $base = fn (string $pc, int $i) => match ($pc) {
            'CUT' => 30 + ($i % 4) * 6,
            'MCH' => 110 + ($i % 6) * 15,
            'CHM' => 22 + ($i % 3) * 5,
            'DRL' => 45 + ($i % 4) * 8,
            'WLD' => 70 + ($i % 5) * 12,
            default => 40,
        };

        foreach ($fgs as $i => $fg) {
            foreach ($fg['route'] as $pc) {
                if (! isset($procs[$pc]) || empty($machines[$pc])) {
                    continue;
                }
                // rotate which machine is priority-1 per item, so items spread
                // across the available machines instead of all piling on one.
                $mlist = $machines[$pc];
                $n = count($mlist);
                $rot = $i % $n;
                $ordered = array_merge(array_slice($mlist, $rot), array_slice($mlist, 0, $rot));
                $cyc = $base($pc, $i);
                foreach ($ordered as $pr => $mid) {
                    DB::table('m_route_time')->insert([
                        'item_id' => $fg['id'], 'proc_id' => $procs[$pc], 'machine_id' => $mid,
                        'cycle_sec' => $cyc + $pr * 8, // alternate machine a bit slower
                        'setup_min' => 15, 'priority' => $pr + 1, 'active' => 1,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
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

    /* ---------------- pricelist ---------------- */

    /**
     * One ACTIVE pricelist per customer, each item priced over two windows — an
     * expired one (last renegotiation) and the running one — so the validity
     * rule is visible. Every third item also gets a volume tier (min_qty), some
     * items are left unpriced so their SO line falls back to a manual price, and
     * the first customer keeps an INACTIVE list that must never be picked.
     *
     * @param  array<int, array>  $fgs
     */
    private function seedPricelists(array $fgs, User $admin): void
    {
        $currency = (int) (DB::table('m_currency')->where('code', 'IDR')->value('id') ?? 0);

        // deterministic list price per FG, so re-seeding gives the same numbers
        $listPrice = [];
        foreach ($fgs as $i => $fg) {
            $listPrice[$fg['id']] = 12500 + $i * 750;
        }
        $round100 = fn (float $v) => round($v / 100) * 100;

        $prevFrom = $this->periods[0]->copy()->subMonths(6)->startOfMonth();
        $prevTo = $this->periods[0]->copy()->subDay();
        $curFrom = $this->periods[0]->copy();
        $curTo = $this->periods[2]->copy()->endOfMonth();

        $byCustomer = $this->groupByCustomer($fgs);
        $seq = 0;
        foreach ($byCustomer as $cusId => $items) {
            $seq++;
            $mainId = DB::table('m_pricelist_main')->insertGetId([
                'code' => sprintf('PL-DEMO-%04d', $seq),
                'cus_id' => $cusId, 'status' => 'ACTIVE', 'user_id' => $admin->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $rows = [];
            foreach ($items as $li => $fg) {
                // every other customer has its 3rd item unpriced — the SO picks
                // up those first three, so a manual-price line actually shows up
                if ($li === 2 && $seq % 2 === 0) {
                    continue;
                }
                $price = $listPrice[$fg['id']];
                // expired window: the price before the last increase
                $rows[] = ['main_id' => $mainId, 'item_id' => $fg['id'], 'price' => $round100($price * 0.95),
                    'currency_id' => $currency, 'valid_from' => $prevFrom->toDateString(), 'valid_to' => $prevTo->toDateString(), 'min_qty' => 0];
                $rows[] = ['main_id' => $mainId, 'item_id' => $fg['id'], 'price' => $price,
                    'currency_id' => $currency, 'valid_from' => $curFrom->toDateString(), 'valid_to' => $curTo->toDateString(), 'min_qty' => 0];
                if ($li % 3 === 0) {
                    $rows[] = ['main_id' => $mainId, 'item_id' => $fg['id'], 'price' => $round100($price * 0.92),
                        'currency_id' => $currency, 'valid_from' => $curFrom->toDateString(), 'valid_to' => $curTo->toDateString(), 'min_qty' => 250];
                }
            }
            DB::table('m_pricelist_det')->insert($rows);

            // an INACTIVE list with tempting prices — the lookup must skip it
            if ($seq === 1) {
                $draftId = DB::table('m_pricelist_main')->insertGetId([
                    'code' => 'PL-DEMO-INACTIVE', 'cus_id' => $cusId, 'status' => 'INACTIVE', 'user_id' => $admin->id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('m_pricelist_det')->insert([
                    'main_id' => $draftId, 'item_id' => $items[0]['id'], 'price' => $round100($listPrice[$items[0]['id']] * 0.7),
                    'currency_id' => $currency, 'valid_from' => $curFrom->toDateString(), 'valid_to' => $curTo->toDateString(), 'min_qty' => 0,
                ]);
            }
        }
    }

    /* ---------------- sales orders ---------------- */

    /** @param array<int, array> $fgs */
    private function seedSalesOrders(array $fgs, array $customers, User $admin): void
    {
        $currency = DB::table('m_currency')->where('code', 'IDR')->value('id');
        $ppnTax = DB::table('m_tax')->where('code', 'PPN-DN')->first();
        $pphTax = DB::table('m_tax')->where('code', 'PPH-22')->first();

        $seq = 0;
        foreach ($this->groupByCustomer($fgs) as $cusId => $items) {
            // two SOs per customer: one in month 1, one in month 2
            foreach ([0, 1] as $pi) {
                $seq++;
                $date = $this->periods[$pi]->copy()->addDays(3 + $seq);
                $so = sls_so_main::create([
                    'code' => sprintf('SO-DEMO-%s-%04d', $this->periods[$pi]->format('Ym'), $seq),
                    'date' => $date->toDateString(),
                    'cus_id' => $cusId,
                    'cus_po_no' => sprintf('PO-%s-%03d', $this->periods[$pi]->format('ym'), $seq),
                    'currency_id' => $currency,
                    'user_id' => $admin->id,
                    'status' => 'APPROVED',
                ]);
                foreach (array_slice($items, 0, 3) as $li => $fg) {
                    $qty = (int) round($fg['month_qty'] / 3);
                    // take the price the pricelist gives for this date+qty; items
                    // outside any valid window get a hand-typed price instead
                    $pl = PricelistService::find((int) $cusId, (int) $fg['id'], $date->toDateString(), $qty);
                    $price = $pl ? (float) $pl->price : 15000 + $li * 2500;

                    $withPph = $li === 1;   // one line per SO shows PPh withholding
                    $tax = LineTax::compute(round($price * $qty, 2), $ppnTax, $withPph ? $pphTax : null);

                    $so->detail()->create([
                        'item_id' => $fg['id'],
                        'qty' => $qty,
                        'price' => $price,
                        'pricelist_det_id' => $pl?->id,
                        'tax_id' => $ppnTax?->id,
                        'pph_tax_id' => $withPph ? $pphTax?->id : null,
                        'ppn' => $ppnTax ? 1 : 0,
                        'pph' => $withPph && $pphTax ? 1 : 0,
                        'dpp' => $tax['dpp'],
                        'ppn_value' => $tax['ppn_value'],
                        'pph_value' => $tax['pph_value'],
                        'due_date' => $date->copy()->addDays(20)->toDateString(),
                        'qty_delivered' => 0,
                    ]);
                }
            }
        }
    }

    /**
     * FGs grouped by the customer they are registered to, so a pricelist or an
     * SO only ever carries that customer's items.
     *
     * @param  array<int, array>  $fgs
     * @return array<int, array<int, array>>
     */
    private function groupByCustomer(array $fgs): array
    {
        $byCustomer = [];
        foreach ($fgs as $fg) {
            foreach ($fg['cus'] as $cusId) {
                $byCustomer[$cusId][] = $fg;
            }
        }

        return $byCustomer;
    }

    /* ---------------- FG stock → Delivery Order → Sales Invoice ---------------- */

    /**
     * Demo the sales fulfilment loop without disturbing the planning demo:
     * receive FG stock for a couple of approved SOs, ship part of them on a DO
     * (SO stays APPROVED because delivery is partial), and post one Sales
     * Invoice. Everything is DEMO-tagged so cleanup() rebuilds it idempotently.
     */
    private function seedFgFulfilment(User $admin): void
    {
        $ppn = DB::table('m_tax')->where('code', 'PPN-DN')->first();
        $factor = $ppn ? (float) $ppn->dpp_factor : 11 / 12;
        $rate = $ppn ? (float) $ppn->rate_pct : 12.0;
        $uid = sprintf('U%04d', $admin->id);

        // first approved demo SO of the first two customers
        $sos = sls_so_main::with('detail')->where('code', 'like', 'SO-DEMO-%')
            ->where('status', 'APPROVED')->orderBy('id')->get()
            ->unique('cus_id')->take(2)->values();

        $seq = 0;
        foreach ($sos as $so) {
            $seq++;
            $lines = $so->detail;
            if ($lines->isEmpty()) {
                continue;
            }

            // 1) receive full ordered qty of each line into the FG warehouse
            $inc = DB::table('tr_inc_fg_main')->insertGetId([
                'code' => sprintf('FGI-DEMO-%02d', $seq),
                'date' => now()->toDateString(), 'user_id' => $uid,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($lines as $i => $l) {
                DB::table('tr_inc_fg_det')->insert([
                    'code' => sprintf('FGID-%d-%d', $seq, $i + 1),
                    'main_id' => $inc, 'pal_pro_code' => sprintf('PLD-%d-%d', $seq, $i + 1),
                    'item_id' => $l->item_id, 'cut_id' => 0, 'qty' => (int) $l->qty, 'wip_id' => null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            // 2) ship ~40% of each line on a DO (partial → SO stays APPROVED)
            $doCode = sprintf('DO-DEMO-%02d', $seq);
            $do = DB::table('sls_do_main')->insertGetId([
                'code' => $doCode, 'date' => now()->toDateString(), 'so_id' => $so->id,
                'user_id' => $admin->id, 'status' => 'SHIPPED',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $out = DB::table('tr_out_fg_main')->insertGetId([
                'code' => sprintf('FGO-DEMO-%02d', $seq), 'code_do' => $doCode,
                'date' => now()->toDateString(), 'user_id' => $uid,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $doDetailIds = [];
            foreach ($lines as $i => $l) {
                $ship = max(1, (int) floor((int) $l->qty * 0.4));
                $doDetId = DB::table('sls_do_detail')->insertGetId([
                    'main_id' => $do, 'so_detail_id' => $l->id, 'item_id' => $l->item_id,
                    'qty' => $ship, 'fg_code' => $doCode,
                ]);
                $doDetailIds[] = ['id' => $doDetId, 'qty' => $ship, 'item_id' => $l->item_id, 'price' => (float) $l->price];
                DB::table('tr_out_fg_det')->insert([
                    // per-lot FG accounting: reference the lot received above, not the DO code
                    'main_id' => $out, 'item_id' => $l->item_id, 'fg_code' => sprintf('FGID-%d-%d', $seq, $i + 1),
                    'code' => sprintf('DOD-%d-%d', $seq, $i + 1), 'qty' => $ship,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('sls_so_detail')->where('id', $l->id)->increment('qty_delivered', $ship);
            }

            // 3) invoice the first customer's shipped DO, and flag it INVOICED
            if ($seq === 1) {
                $dpp = 0.0;
                foreach ($doDetailIds as $d) {
                    $dpp += $d['qty'] * $d['price'];
                }
                $dpp = round($dpp, 2);
                $nilaiLain = round($dpp * $factor, 2);
                $vat = round($nilaiLain * $rate / 100, 2);
                $inv = DB::table('sls_inv_main')->insertGetId([
                    'code' => sprintf('SI-DEMO-%02d', $seq), 'date' => now()->toDateString(),
                    'cus_id' => $so->cus_id, 'dpp' => $dpp, 'dpp_nilai_lain' => $nilaiLain,
                    'vat' => $vat, 'total' => round($dpp + $vat, 2),
                    'tax_inv_no' => sprintf('010.000-%s.%08d', now()->format('y'), $seq),
                    'due_date' => now()->addDays(30)->toDateString(), 'user_id' => $admin->id, 'status' => 'POSTED',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($doDetailIds as $d) {
                    DB::table('sls_inv_detail')->insert([
                        'main_id' => $inv, 'do_detail_id' => $d['id'], 'item_id' => $d['item_id'],
                        'qty' => $d['qty'], 'price' => $d['price'], 'amount' => round($d['qty'] * $d['price'], 2),
                    ]);
                }
                DB::table('sls_do_main')->where('id', $do)->update(['status' => 'INVOICED']);
            }
        }
    }

    /* ---------------- Accounting (Fase 7) ---------------- */

    /**
     * Standard chart of accounts, open periods, then generate the general-ledger
     * journals from the demo documents (sales/AP invoices + depreciation) and
     * post one partial AR receipt so the ledger and trial balance have content.
     */
    private function seedAccounting(User $admin): void
    {
        // 1) standard COA (idempotent by code)
        $coa = [
            ['1100', 'Kas & Bank', 'ASSET'], ['1200', 'Piutang Usaha (AR)', 'ASSET'],
            ['1210', 'PPN Masukan', 'ASSET'], ['1300', 'Persediaan', 'ASSET'],
            ['1500', 'Aset Tetap', 'ASSET'], ['1590', 'Akumulasi Penyusutan', 'ASSET'],
            ['2100', 'Utang Usaha (AP)', 'LIABILITY'], ['2210', 'PPN Keluaran', 'LIABILITY'],
            ['2220', 'Utang PPh', 'LIABILITY'], ['3100', 'Modal', 'EQUITY'],
            ['4100', 'Penjualan', 'REVENUE'], ['5100', 'Harga Pokok Penjualan', 'COGS'],
            ['6100', 'Beban Penyusutan', 'EXPENSE'], ['6200', 'Beban Operasional', 'EXPENSE'],
        ];
        foreach ($coa as [$code, $name, $group]) {
            DB::table('acc_coa')->updateOrInsert(['code' => $code], ['name' => $name, 'acc_group' => $group, 'postable' => 1]);
        }

        // 2) open the planned periods (+ a couple past months for older docs)
        $periods = collect($this->periods)->map(fn ($p) => $p->format('Ym'))
            ->merge([$this->periods[0]->copy()->subMonth()->format('Ym'), $this->periods[0]->copy()->subMonths(18)->format('Ym')])
            ->unique();
        foreach ($periods as $period) {
            DB::table('acc_period')->updateOrInsert(['period' => $period], ['status' => 'OPEN']);
        }

        // 3) generate journals from posted documents for each period
        $gl = new GlPostingService;
        foreach ($periods as $period) {
            $gl->generateForPeriod($period, $admin->id);
        }

        // 4) one partial AR receipt (50%) against a posted sales invoice
        $inv = DB::table('sls_inv_main')->where('status', 'POSTED')->orderBy('id')->first();
        if ($inv) {
            $amount = round((float) $inv->total * 0.5, 2);
            $rec = DB::table('acc_ar_rec_main')->insertGetId([
                'code' => 'RCV-DEMO-01', 'date' => now()->toDateString(), 'cus_id' => $inv->cus_id,
                'amount' => $amount, 'user_id' => $admin->id, 'status' => 'POSTED',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('acc_ar_rec_det')->insert(['main_id' => $rec, 'inv_id' => $inv->id, 'amount' => $amount]);
            JournalEngine::post('AR_REC', $rec, now()->toDateString(), 'RCV', [
                ['coa' => '1100', 'debit' => $amount, 'memo' => 'Kas/Bank'],
                ['coa' => '1200', 'credit' => $amount, 'memo' => 'Terima AR RCV-DEMO-01'],
            ], 'Penerimaan AR RCV-DEMO-01', $admin->id);
        }
    }

    /* ---------------- Costing & Asset (Fase 6) ---------------- */

    /**
     * Cost rates per period, a small fixed-asset register tied to the demo
     * machines, then one COGM run and one depreciation posting for month 1 so
     * both screens open with real numbers.
     *
     * @param  array<string, array<int, int>>  $machines  process role → machine ids
     * @param  array<string, int>  $procs
     */
    private function seedCosting(array $machines, array $procs): void
    {
        // 1) labor / FOH rates: a general rate per period + a pricier machining rate
        foreach ($this->periods as $p) {
            $period = $p->format('Ym');
            DB::table('cst_rate')->insert([
                ['period' => $period, 'rate_type' => 'LABOR', 'process_id' => null, 'rate_per_hour' => 28000],
                ['period' => $period, 'rate_type' => 'FOH', 'process_id' => null, 'rate_per_hour' => 45000],
            ]);
            if (isset($procs['MCH'])) {
                DB::table('cst_rate')->insert([
                    ['period' => $period, 'rate_type' => 'LABOR', 'process_id' => $procs['MCH'], 'rate_per_hour' => 35000],
                    ['period' => $period, 'rate_type' => 'FOH', 'process_id' => $procs['MCH'], 'rate_per_hour' => 60000],
                ]);
            }
        }

        // 2) asset categories (master, idempotent)
        foreach ([['MSN', 'Mesin Produksi', 96], ['TOOL', 'Tooling & Jig', 36], ['VHC', 'Kendaraan', 60]] as [$code, $name, $life]) {
            DB::table('m_asset_categ')->updateOrInsert(['code' => $code], ['name' => $name, 'useful_life' => $life, 'depr_method' => 'STRAIGHT']);
        }
        $msn = (int) DB::table('m_asset_categ')->where('code', 'MSN')->value('id');
        $vhc = (int) DB::table('m_asset_categ')->where('code', 'VHC')->value('id');

        // 3) register each demo machine as an asset, plus one vehicle
        $acq = $this->periods[0]->copy()->subMonths(18)->toDateString();
        $seq = 0;
        foreach (DB::table('m_machine')->where('code', 'like', 'MC-DEMO-%')->orderBy('id')->get(['id', 'code', 'name']) as $m) {
            $seq++;
            DB::table('ast_main')->insert([
                'code' => sprintf('AST-DEMO-%03d', $seq), 'categ_id' => $msn, 'name' => $m->name,
                'acq_date' => $acq, 'acq_cost' => 250_000_000 + $seq * 15_000_000, 'useful_life' => 96,
                'machine_id' => $m->id, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('ast_main')->insert([
            'code' => 'AST-DEMO-VHC', 'categ_id' => $vhc, 'name' => 'Truk Pengiriman',
            'acq_date' => $acq, 'acq_cost' => 420_000_000, 'useful_life' => 60,
            'machine_id' => null, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // 4) one COGM run + one depreciation posting for month 1
        $period = $this->periods[0]->format('Ym');
        $svc = new CostingService;
        // same scope the "Hitung COGM" button uses: released / closed WOs
        foreach (prd_wo_main::where('code', 'like', 'WO-DEMO-%')->whereIn('status', [2, 3])->get() as $wo) {
            DB::table('cst_cogm')->updateOrInsert(
                ['period' => $period, 'wo_id' => $wo->id],
                $svc->cogmForWo($wo, $period)
            );
        }
        foreach (DB::table('ast_main')->where('code', 'like', 'AST-DEMO-%')->get() as $a) {
            DB::table('ast_depre')->updateOrInsert(
                ['ast_id' => $a->id, 'period' => $period],
                ['amount' => round((float) $a->acq_cost / max(1, (int) $a->useful_life), 2)]
            );
        }
    }

    /* ---------------- MPP → MPS → WO ---------------- */

    /**
     * MPP (approved) → MPS (machine-loaded via MpsController::generate, so the
     * seeded board matches exactly what the "Generate dari MPP" button makes) →
     * WO. Month 1 MPS lots are approved (locked/green) and seed a few WOs;
     * months 2–3 stay DRAFT so the board shows plannable/draggable lots too.
     *
     * @param array<int, array> $fgs
     */
    private function seedPlanningChain(array $fgs, array $customers, User $admin): void
    {
        // 1) MPP approved for every FG × month
        foreach ($fgs as $fg) {
            foreach ($this->periods as $pi => $p) {
                prd_mpp::create([
                    'period' => $p->format('Ym'), 'item_id' => $fg['id'],
                    'plan_qty' => (int) round($fg['month_qty'] * (1 + 0.05 * $pi)),
                    'status' => 'APPROVED',
                ]);
            }
        }

        // 2) MPS: pack each period into daily machine lots using the real generator
        $mps = app(\App\Http\Controllers\Api\Production\MpsController::class);
        foreach ($this->periods as $p) {
            $req = \Illuminate\Http\Request::create('/mps/generate', 'POST', ['period' => $p->format('Ym')]);
            $req->setUserResolver(fn () => $admin);
            $mps->generate($req);
        }

        // 3) Approve all month-1 lots (locked), leave later months DRAFT
        $m1 = $this->periods[0]->format('Y-m') . '-%';
        prd_mps::where('status', 'DRAFT')->where('plan_date', 'like', $m1)->update(['status' => 'APPROVED']);

        // 4) A few WOs from approved month-1 lots (BOM exploded, no serials)
        $fgById = collect($fgs)->keyBy('id');
        $woSeq = 0;
        $approvedLots = prd_mps::where('status', 'APPROVED')->where('plan_date', 'like', $m1)
            ->whereIn('item_id', $fgById->keys())->orderBy('plan_date')->take(20)->get();
        foreach ($approvedLots as $lot) {
            $fg = $fgById->get($lot->item_id);
            if (! $fg) {
                continue;
            }
            $this->createWo($fg, $lot, $customers, $admin, $this->periods[0]->format('Ym'), ++$woSeq);
        }

        // make sure cut-only (no-process) items get a WO too, so their cutting
        // pallet demonstrates the Incoming FG "material without process" path
        foreach ($fgs as $fg) {
            if (count($fg['route']) !== 1 || prd_wo_main::where('fg_id', $fg['id'])->exists()) {
                continue;
            }
            $lot = prd_mps::where('status', 'APPROVED')->where('plan_date', 'like', $m1)
                ->where('item_id', $fg['id'])->orderBy('plan_date')->first();
            if ($lot) {
                $this->createWo($fg, $lot, $customers, $admin, $this->periods[0]->format('Ym'), ++$woSeq);
            }
        }

        $this->seedMesExecution($admin);
    }

    /* ---------------- MES: release WOs and book their RM serials ---------------- */

    /**
     * Give the shop floor something to scan: release a slice of the demo WOs and
     * book RM serials on them, which is what the Cutting screen consumes.
     * Production itself is reported on the MES screens (tr_cut_* / tr_pro_*).
     */
    private function seedMesExecution(User $admin): void
    {
        $fgProc = \App\Support\PlanningService::fgProcId();
        // release the first 10 WOs, plus any cut-only WO (so the no-process material is covered)
        $cutOnlyMainIds = DB::table('m_process_main_det')
            ->when($fgProc, fn ($q) => $q->where('proc_id', '<>', $fgProc))
            ->groupBy('main_id')->havingRaw('COUNT(*) = 1')->pluck('main_id');
        $wos = prd_wo_main::where('code', 'like', 'WO-DEMO-%')->orderBy('id')->take(10)->get()
            ->merge(prd_wo_main::where('code', 'like', 'WO-DEMO-%')->whereIn('process_main_id', $cutOnlyMainIds)->get())
            ->unique('id')->values();
        foreach ($wos as $wo) {
            $wo->update(['status' => 2]); // RELEASED
            $det = DB::table('prd_wo_detail_rm')->where('main_id', $wo->id)->first();
            if (! $det) {
                continue;
            }
            // pieces per bar is what physically fits: bar length ÷ cut length
            $cut = (float) (DB::table('m_bom as b')->join('m_bom_det_rm as d', 'd.id_prim', '=', 'b.id')
                ->where('b.item_id', $wo->fg_id)->orderBy('d.priority')->value('d.length_cut') ?: 0);
            $bar = 6000.0;
            $perBar = $cut > 0 ? (int) floor($bar / $cut) : (int) $wo->qty;
            for ($k = 1; $k <= 2; $k++) {
                DB::table('prd_wo_serial_rm')->insert([
                    'detail_id' => $det->id,
                    'serial_id' => sprintf('SN-DEMO-%05d-%d', $wo->id, $k),
                    'length_asal' => $bar, 'length_book' => $bar, 'length_rem' => $bar,
                    'qty_per_serial' => max(1, $perBar), 'qty_serial' => 1, 'scrap' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // Drive some WOs to a finished pallet at the last real routing step, so
        // the Incoming FG screen has genuine pallets. The FG marker is excluded;
        // a cut-only routing yields a cutting pallet, a routing with processing
        // yields a processing pallet. Cover the first few + every cut-only WO.
        $madeMulti = 0;
        foreach ($wos as $wo) {
            $realSteps = DB::table('m_process_main_det')->where('main_id', $wo->process_main_id)
                ->when($fgProc, fn ($q) => $q->where('proc_id', '<>', $fgProc))
                ->orderBy('sequence')->pluck('proc_id')->all();
            if (! $realSteps) {
                continue;
            }
            $lastProc = (int) end($realSteps);
            $isCutOnly = count($realSteps) === 1;
            if (! $isCutOnly) {
                if ($madeMulti >= 5) {
                    continue;
                }
                $madeMulti++;
            }
            $wip = DB::table('prd_wip')->where('wo_id', $wo->id)->value('id')
                ?? DB::table('prd_wip')->insertGetId([
                    'wo_id' => $wo->id, 'item_id' => $wo->fg_id,
                    'code' => sprintf('WIP-%06d', $wo->id), 'created_at' => now(), 'updated_at' => now(),
                ]);
            $qty = max(1, (int) floor($wo->qty / 3));

            if ($isCutOnly) {
                // cutting is the last real step → the cut pallet is the finished good
                $cut = DB::table('tr_cut_main')->insertGetId([
                    'code' => sprintf('CUT-DEMO-%06d', $wo->id), 'user_id' => sprintf('U%04d', $admin->id),
                    'wip_id' => $wip, 'no_dp' => '-', 'item_id' => $wo->fg_id, 'process_id' => $lastProc,
                    'date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('tr_cut_pal_pr')->insert([
                    'code' => sprintf('PLT-DEMO-%06d-C', $wo->id), 'cut_id' => $cut, 'qty' => $qty,
                    'process_id' => $lastProc, 'created_at' => now(), 'updated_at' => now(),
                ]);
            } else {
                $pro = DB::table('tr_pro_main')->insertGetId([
                    'code' => sprintf('PRO-DEMO-%06d', $wo->id), 'wip_id' => $wip, 'item_id' => $wo->fg_id,
                    'user_id' => sprintf('U%04d', $admin->id), 'process_id' => $lastProc,
                    'pallet_code' => '-', 'no_dp' => '-', 'date' => now()->toDateString(),
                    'start_time' => now()->format('H:i:s'), 'end_time' => now()->format('H:i:s'),
                    'qty_full' => $qty, 'finish' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('tr_pro_pal_pr')->insert([
                    'code' => sprintf('PLT-DEMO-%06d-F', $wo->id), 'pro_id' => $pro, 'cut_id' => 0,
                    'qty' => $qty, 'pal_code_bf' => '-', 'status' => 'FULL', 'process_id' => $lastProc,
                ]);
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
            'process_main_id' => $fg['process_main_id'],   // default = priority-1 routing
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
}
