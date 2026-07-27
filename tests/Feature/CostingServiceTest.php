<?php

use App\Support\CostingService;
use Illuminate\Support\Facades\DB;

/**
 * Standard COGM per WO:
 *   material = qty × Σ(length_use × weight/mm × RM cost/kg)
 *   labor/foh = Σ ops (cycle_sec × qty / 3600) × rate
 * Fixtures are built fresh so the numbers are exact and deterministic.
 */
function makeItem(string $code, array $extra = []): int
{
    $cat = DB::table('m_i_category')->value('id');

    return DB::table('m_item')->insertGetId(array_merge([
        'code' => $code.'-'.uniqid(), 'part_name' => $code, 'type' => 'Pipe',
        'category_id' => $cat, 'min_stock' => 0, 'max_stock' => 0, 'active' => 1,
    ], $extra));
}

it('computes material + labor + FOH into a total and unit cost', function () {
    $period = '209906';
    $cut = (int) DB::table('m_process')->where('code', 'CUT')->value('id');

    // RM: 18 kg per 6000 mm bar → 0.003 kg/mm
    $rm = makeItem('RM-COGM', ['weight' => 18, 'length' => 6000]);
    $fg = makeItem('FG-COGM', ['length' => 100]);

    // BOM: one RM line using 100 mm per FG piece → 0.3 kg → ×15000 default = Rp4.500/pc
    $bom = DB::table('m_bom')->insertGetId(['item_id' => $fg, 'active' => 1]);
    DB::table('m_bom_det_rm')->insert(['id_prim' => $bom, 'mat_id' => $rm, 'length_cut' => 102, 'length_use' => 100, 'priority' => 1]);

    // routing: one CUT step at 120 sec/pc
    $main = DB::table('m_process_main')->insertGetId(['code' => 'RT-COGM-'.uniqid(), 'name' => 'Cut', 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('m_process_main_det')->insert(['main_id' => $main, 'proc_id' => $cut, 'sequence' => 1, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('m_bom_pro')->insert(['item_id' => $fg, 'process_main_id' => $main, 'priority' => 1, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('m_route_time')->insert(['item_id' => $fg, 'proc_id' => $cut, 'machine_id' => null, 'cycle_sec' => 120, 'setup_min' => 0, 'priority' => 1, 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);

    // rates for the period
    DB::table('cst_rate')->insert([
        ['period' => $period, 'rate_type' => 'LABOR', 'process_id' => null, 'rate_per_hour' => 30000],
        ['period' => $period, 'rate_type' => 'FOH', 'process_id' => null, 'rate_per_hour' => 50000],
    ]);

    $wo = (object) ['id' => 0, 'fg_id' => $fg, 'process_main_id' => $main, 'qty' => 10];
    $c = (new CostingService)->cogmForWo($wo, $period);

    // material 10 × 4500 = 45000; hours = 120×10/3600 = 0.3333 → labor 10000, foh 16666.67
    expect($c['material_cost'])->toBeMoney(45000.0)
        ->and($c['labor_cost'])->toBeMoney(10000.0)
        ->and($c['foh_cost'])->toBeMoney(16666.67)
        ->and($c['subcont_cost'])->toBeMoney(0.0)
        ->and($c['scrap_recovery'])->toBeMoney(0.0)
        ->and($c['total'])->toBeMoney(71666.67)
        ->and(round($c['unit_cost'], 2))->toBeMoney(7166.67);
});
