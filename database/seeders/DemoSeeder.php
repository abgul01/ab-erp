<?php

namespace Database\Seeders;

use App\Models\m_bom;
use App\Models\m_item;
use App\Models\prd_mpp;
use App\Models\prd_mps;
use App\Models\sls_so_main;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo volume data so the planning/sales screens (MPP matrix, MPS Gantt,
 * Forecast, Sales Order) look populated. Idempotent: wipes its own demo set
 * (codes FG-D*/RM-D*/PM-D*/CUST-D*/SO-D*/WO-D* + the 3 demo periods) first.
 *
 * Run: php artisan db:seed --class="Database\Seeders\DemoSeeder"
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $p = [
            $now->format('Ym'),
            $now->copy()->addMonth()->format('Ym'),
            $now->copy()->addMonths(2)->format('Ym'),
        ];

        $this->wipe($p);

        $fgGroup = DB::table('m_i_category')->where('name_c', 'FG')->value('id');
        $matGroup = DB::table('m_i_category')->where('name_c', 'Material')->value('id');
        $adminId = DB::table('users')->where('username', 'admin')->value('id');
        $custCatId = DB::table('m_cont_categ')->where('name', 'Customer')->value('id')
            ?: DB::table('m_cont_categ')->insertGetId(['name' => 'Customer']);

        // ---- Machines ----
        $machineIds = [];
        foreach (range(1, 6) as $i) {
            $code = 'MC-' . str_pad($i, 2, '0', STR_PAD_LEFT);
            $machineIds[] = DB::table('m_machine')->updateOrInsert(['code' => $code], ['name' => "Machine {$i}", 'active' => 1, 'updated_at' => $now, 'created_at' => $now])
                ? DB::table('m_machine')->where('code', $code)->value('id') : null;
        }
        $machineIds = DB::table('m_machine')->whereIn('code', array_map(fn ($i) => 'MC-' . str_pad($i, 2, '0', STR_PAD_LEFT), range(1, 6)))->pluck('id')->all();

        // ---- RM items ----
        $rmIds = [];
        $ods = [21.7, 27.2, 34.0, 42.7, 48.6, 60.5, 76.3, 89.1, 101.6, 114.3, 139.8, 165.2];
        foreach ($ods as $i => $od) {
            $code = 'RM-D' . str_pad($i + 1, 2, '0', STR_PAD_LEFT);
            $thick = [1.6, 2.0, 2.3, 2.8, 3.2][$i % 5];
            $rmIds[] = m_item::updateOrCreate(['code' => $code], [
                'part_name' => "Steel Tube OD{$od} t{$thick}", 'type' => 'Pipe', 'category_id' => $matGroup,
                'o_d' => $od, 'thick' => $thick, 'length' => 6000, 'weight' => round($od * $thick * 0.02, 2),
                'min_stock' => 0, 'max_stock' => 0, 'active' => 1, 'pm' => 0,
            ])->id;
        }

        // ---- PM items ----
        $pmIds = [];
        foreach (['Carton Box', 'Plastic Wrap', 'Pallet Wood'] as $i => $nm) {
            $code = 'PM-D' . str_pad($i + 1, 2, '0', STR_PAD_LEFT);
            $pmIds[] = m_item::updateOrCreate(['code' => $code], [
                'part_name' => $nm, 'type' => 'Other', 'category_id' => $fgGroup, 'pm' => 1,
                'weight' => 0, 'min_stock' => 0, 'max_stock' => 0, 'active' => 1,
            ])->id;
        }

        // ---- FG items (20) + BOM ----
        $fgTypes = ['Roundbar', 'Pipe', 'Square Bar', 'Plat Bar'];
        $fgIds = [];
        foreach (range(1, 20) as $i) {
            $code = 'FG-D' . str_pad($i, 2, '0', STR_PAD_LEFT);
            $od = $ods[$i % count($ods)];
            $fg = m_item::updateOrCreate(['code' => $code], [
                'part_name' => "Part Assy {$i}", 'type' => $fgTypes[$i % 4], 'category_id' => $fgGroup,
                'o_d' => $od, 'i_d' => round($od - 8, 1), 'thick' => 4, 'width' => 0, 'height' => 0,
                'length' => 150 + ($i % 10) * 20, 'length_cut' => 152 + ($i % 10) * 20,
                'weight' => round($od * 0.5, 2), 'min_stock' => 0, 'max_stock' => 0, 'active' => 1, 'pm' => 0,
            ]);
            $fgIds[$i] = $fg->id;

            $bom = m_bom::firstOrCreate(['item_id' => $fg->id], ['active' => 1]);
            $bom->rmLines()->delete();
            $bom->pmLines()->delete();
            $lengthUse = 250 + ($i % 8) * 15;
            $bom->rmLines()->create(['mat_id' => $rmIds[$i % count($rmIds)], 'length_cut' => $lengthUse - 3, 'length_use' => $lengthUse, 'priority' => 1]);
            $bom->pmLines()->create(['pm_id' => $pmIds[$i % 3], 'qty' => 1]);
        }
        $fgIds = array_values($fgIds);

        // ---- Customers (6) ----
        $names = ['PT Toyota Manufacturing', 'PT Denso Indonesia', 'PT Aisin', 'PT Musashi', 'PT NSK Bearing', 'PT Yamaha Motor'];
        $custIds = [];
        foreach ($names as $i => $nm) {
            $u = 'CUST-D' . str_pad($i + 1, 2, '0', STR_PAD_LEFT);
            DB::table('m_contacts')->updateOrInsert(['u_code' => $u], [
                'company_n' => $nm, 'nick_n' => 'Cust' . ($i + 1), 'category_id' => $custCatId,
                'active' => 1, 'updated_at' => $now, 'created_at' => $now,
            ]);
            $custIds[] = DB::table('m_contacts')->where('u_code', $u)->value('id');
        }

        // ---- item_customer mapping (each FG to 1-2 customers) ----
        foreach ($fgIds as $i => $fgId) {
            $c1 = $custIds[$i % count($custIds)];
            DB::table('m_item_customer')->updateOrInsert(['item_id' => $fgId, 'cus_id' => $c1], ['priority' => 1, 'active' => 1, 'updated_at' => $now, 'created_at' => $now]);
            if ($i % 3 === 0) {
                $c2 = $custIds[($i + 1) % count($custIds)];
                DB::table('m_item_customer')->updateOrInsert(['item_id' => $fgId, 'cus_id' => $c2], ['priority' => 2, 'active' => 1, 'updated_at' => $now, 'created_at' => $now]);
            }
        }

        // ---- Forecast (3 periods x FG x primary customer) ----
        foreach ($fgIds as $i => $fgId) {
            $cus = $custIds[$i % count($custIds)];
            foreach ($p as $k => $period) {
                DB::table('sls_forecast')->insert([
                    'cus_id' => $cus, 'item_id' => $fgId, 'period' => $period, 'version' => 'FINAL',
                    'qty' => 100 + rand(0, 8) * 50 + $k * 20, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // ---- Sales Orders (15) ----
        for ($n = 1; $n <= 15; $n++) {
            $cus = $custIds[$n % count($custIds)];
            $day = str_pad(rand(1, 26), 2, '0', STR_PAD_LEFT);
            $soId = DB::table('sls_so_main')->insertGetId([
                'code' => 'SO-D' . str_pad($n, 4, '0', STR_PAD_LEFT), 'date' => substr($p[0], 0, 4) . '-' . substr($p[0], 4, 2) . "-{$day}",
                'cus_id' => $cus, 'cus_po_no' => 'CPO-' . rand(1000, 9999), 'user_id' => $adminId,
                'status' => $n <= 10 ? 'APPROVED' : 'DRAFT', 'created_at' => $now, 'updated_at' => $now,
            ]);
            // pick 1-3 FGs registered to this customer
            $custItems = DB::table('m_item_customer')->where('cus_id', $cus)->where('active', 1)->pluck('item_id')->all();
            if (! $custItems) {
                continue;
            }
            $pick = array_slice($custItems, 0, rand(1, min(3, count($custItems))));
            foreach ($pick as $itemId) {
                DB::table('sls_so_detail')->insert([
                    'main_id' => $soId, 'item_id' => $itemId, 'qty' => 50 + rand(0, 6) * 25,
                    'price' => rand(150, 900) * 1000, 'qty_delivered' => 0,
                ]);
            }
        }

        // ---- MPP (3 periods x FG) ~ forecast, ~70% approved ----
        foreach ($fgIds as $i => $fgId) {
            $cus = $custIds[$i % count($custIds)];
            foreach ($p as $period) {
                $fc = (int) DB::table('sls_forecast')->where('item_id', $fgId)->where('period', $period)->where('version', 'FINAL')->sum('qty');
                $plan = max(50, $fc);
                prd_mpp::create(['period' => $period, 'item_id' => $fgId, 'plan_qty' => $plan, 'status' => rand(1, 10) <= 7 ? 'APPROVED' : 'DRAFT']);
            }
        }

        // ---- MPS for current month: split each approved MPP into dated entries ----
        $y = substr($p[0], 0, 4); $mo = substr($p[0], 4, 2);
        $approvedMpp = prd_mpp::where('period', $p[0])->where('status', 'APPROVED')->get();
        foreach ($approvedMpp as $mpp) {
            $slices = rand(2, 4);
            $per = intdiv($mpp->plan_qty, $slices);
            for ($s = 0; $s < $slices; $s++) {
                $day = str_pad(min(28, 3 + $s * 7 + rand(0, 3)), 2, '0', STR_PAD_LEFT);
                prd_mps::create([
                    'plan_date' => "{$y}-{$mo}-{$day}", 'item_id' => $mpp->item_id, 'qty' => $per,
                    'machine_id' => $machineIds[array_rand($machineIds)], 'status' => rand(1, 10) <= 6 ? 'APPROVED' : 'DRAFT',
                ]);
            }
        }

        // ---- A few WOs from approved MPS (empty booking) ----
        $approvedMps = prd_mps::where('status', 'APPROVED')->whereIn('item_id', $fgIds)->take(8)->get();
        $woN = 1;
        foreach ($approvedMps as $mps) {
            DB::table('prd_wo_main')->insert([
                'code' => 'WO-D' . str_pad($woN++, 4, '0', STR_PAD_LEFT), 'date' => $mps->plan_date,
                'customer_id' => $custIds[0], 'so_id' => '-', 'fg_id' => $mps->item_id, 'mps_id' => $mps->id,
                'user_id' => $adminId, 'qty' => max(1, intdiv($mps->qty, 2)), 'status' => rand(1, 2),
                'no_cut' => 0, 'for_pm' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $this->command->info('Demo data: 20 FG + 12 RM + 3 PM, 6 customers, forecasts/MPP for 3 months, 15 SO, MPS + WO for ' . $p[0] . '.');
    }

    private function wipe(array $periods): void
    {
        $fgIds = m_item::where('code', 'like', 'FG-D%')->pluck('id');
        DB::table('prd_wo_main')->where('code', 'like', 'WO-D%')->delete();
        DB::table('prd_mps')->whereIn('item_id', $fgIds)->delete();
        DB::table('prd_mpp')->whereIn('period', $periods)->whereIn('item_id', $fgIds)->delete();
        DB::table('sls_forecast')->whereIn('period', $periods)->whereIn('item_id', $fgIds)->delete();
        $soIds = sls_so_main::where('code', 'like', 'SO-D%')->pluck('id');
        DB::table('sls_so_detail')->whereIn('main_id', $soIds)->delete();
        DB::table('sls_so_main')->where('code', 'like', 'SO-D%')->delete();
    }
}
