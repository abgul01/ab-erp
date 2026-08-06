<?php

use App\Exceptions\BizException;
use App\Models\npd_project;
use App\Models\npd_trial_main;
use App\Support\FgStockService;
use App\Support\MrpService;
use App\Support\NpdCostingService;
use App\Support\NpdService;
use App\Support\NpdTrialService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * NPD Tahap 3 — trial produksi dan hasil ukurnya.
 *
 * Dua hal yang membuat trial berguna, dan keduanya diuji di sini: ia berjalan
 * lewat Work Order sungguhan (supaya material dan jam mesinnya tercatat) tetapi
 * tidak dianggap pasokan, dan putusan OK/NG-nya dihitung dari spesifikasi,
 * bukan dari pendapat orang yang memasukkan angkanya.
 */
function npdTrialProject(): npd_project
{
    $rfq = rfqFor();
    feasFor($rfq);
    $project = app(NpdService::class)->createFromRfq($rfq, ['name' => 'Proyek trial'], admin()->id);

    app(NpdCostingService::class)->registerPart($project, [
        'code' => 'FG-TRL-'.substr(uniqid(), -6),
        'category_id' => DB::table('m_i_category')->value('id'),
    ], admin()->id);

    return $project->fresh();
}

function npdTrial(npd_project $project, array $attrs = []): npd_trial_main
{
    return app(NpdTrialService::class)->create($project, array_merge([
        'date' => now()->toDateString(),
        'trial_type' => 'PROTOTYPE',
        'planned_qty' => 10,
    ], $attrs), admin()->id);
}

it('membuat Work Order uji coba bersama trialnya', function () {
    $project = npdTrialProject();
    $trial = npdTrial($project, ['planned_qty' => 25]);

    $wo = DB::table('prd_wo_main')->where('id', $trial->wo_id)->first();

    expect($wo)->not->toBeNull()
        ->and($wo->wo_kind)->toBe('NPD_TRIAL')
        ->and((int) $wo->npd_project_id)->toBe($project->id)
        ->and((int) $wo->fg_id)->toBe((int) $project->item_id)
        ->and((int) $wo->qty)->toBe(25);
});

it('menolak trial sebelum part-nya terdaftar di master item', function () {
    $rfq = rfqFor();
    feasFor($rfq);
    $project = app(NpdService::class)->createFromRfq($rfq, [], admin()->id);

    // Work Order menunjuk fg_id; tidak ada cara membuatnya untuk part tanpa nomor.
    expect(fn () => npdTrial($project))->toThrow(BizException::class);
});

it('menghitung putusan OK/NG dari batas spesifikasi, bukan dari ketikan', function () {
    $project = npdTrialProject();
    $trial = npdTrial($project);
    $param = DB::table('m_inspection_param')->value('id');

    $saved = app(NpdTrialService::class)->saveResults($trial, [
        ['param_id' => $param, 'sample_no' => 1, 'nominal' => 10, 'min_value' => 9.8, 'max_value' => 10.2, 'measured' => 10.05],
        ['param_id' => $param, 'sample_no' => 2, 'nominal' => 10, 'min_value' => 9.8, 'max_value' => 10.2, 'measured' => 10.4],
        ['param_id' => $param, 'sample_no' => 3, 'nominal' => 10, 'min_value' => 9.8, 'max_value' => 10.2, 'measured' => 9.7],
    ], admin()->id);

    expect($saved->detail->pluck('judgement')->all())->toBe(['OK', 'NG', 'NG'])
        ->and($saved->passRate())->toBe(33.3);
});

it('memakai batas dari master item dan mengabaikan batas yang diketik lebih longgar', function () {
    $project = npdTrialProject();
    $param = DB::table('m_inspection_param')->value('id');

    DB::table('m_item_inspection')->insert([
        'item_id' => $project->item_id, 'param_id' => $param,
        'nominal' => 10, 'min_value' => 9.9, 'max_value' => 10.1, 'mandatory' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $trial = npdTrial($project);

    // Penguji mengetik batas yang jauh lebih longgar; master harus menang.
    $saved = app(NpdTrialService::class)->saveResults($trial, [
        ['param_id' => $param, 'measured' => 10.3, 'min_value' => 5, 'max_value' => 15],
    ], admin()->id);

    $row = $saved->detail->first();

    expect((float) $row->max_value)->toBe(10.1)
        ->and($row->judgement)->toBe('NG');
});

it('menganggap parameter tanpa batas sebagai keterangan, bukan kegagalan', function () {
    $project = npdTrialProject();
    $trial = npdTrial($project);
    $param = DB::table('m_inspection_param')->value('id');

    // Nomor cetakan atau warna tidak punya batas; menandainya NG akan membuat
    // angka kelulusan salah.
    $saved = app(NpdTrialService::class)->saveResults($trial, [
        ['param_id' => $param, 'measured' => 7],
    ], admin()->id);

    expect($saved->detail->first()->judgement)->toBe('OK');
});

it('menolak menutup trial yang belum punya satu pun hasil ukur', function () {
    $project = npdTrialProject();
    $trial = npdTrial($project);

    expect(fn () => app(NpdTrialService::class)->finish($trial, [
        'produced_qty' => 10, 'ok_qty' => 10, 'ng_qty' => 0,
    ], admin()->id))->toThrow(BizException::class);
});

it('menolak jumlah OK + NG yang melebihi yang diproduksi', function () {
    $project = npdTrialProject();
    $trial = npdTrial($project);
    $param = DB::table('m_inspection_param')->value('id');
    app(NpdTrialService::class)->saveResults($trial, [['param_id' => $param, 'measured' => 1]], admin()->id);

    expect(fn () => app(NpdTrialService::class)->finish($trial->fresh(), [
        'produced_qty' => 10, 'ok_qty' => 8, 'ng_qty' => 5,
    ], admin()->id))->toThrow(BizException::class);
});

it('menutup trial beserta Work Order uji cobanya', function () {
    $project = npdTrialProject();
    $trial = npdTrial($project);
    $param = DB::table('m_inspection_param')->value('id');
    app(NpdTrialService::class)->saveResults($trial, [['param_id' => $param, 'measured' => 1]], admin()->id);

    $done = app(NpdTrialService::class)->finish($trial->fresh(), [
        'produced_qty' => 10, 'ok_qty' => 9, 'ng_qty' => 1, 'conclusion' => 'Dimensi masuk spek.',
    ], admin()->id);

    expect($done->status)->toBe('DONE')
        // WO trial yang menggantung akan terus terlihat sebagai pekerjaan
        // belum selesai di lantai produksi.
        ->and((int) DB::table('prd_wo_main')->where('id', $trial->wo_id)->value('status'))->toBe(3);

    // Dan tidak bisa diubah lagi.
    expect(fn () => app(NpdTrialService::class)->saveResults($done, [
        ['param_id' => $param, 'measured' => 2],
    ], admin()->id))->toThrow(BizException::class);
});

it('tidak menghitung trial sebagai pasokan MRP walau Work Order-nya dirilis', function () {
    $project = npdTrialProject();
    $trial = npdTrial($project, ['planned_qty' => 100]);

    // Rilis WO trialnya, lalu minta rencana atas part yang sama.
    DB::table('prd_wo_main')->where('id', $trial->wo_id)->update(['status' => 2]);
    DB::table('prd_mpp')->updateOrInsert(
        ['period' => now()->format('Ym'), 'item_id' => $project->item_id],
        ['plan_qty' => 100, 'status' => 'APPROVED', 'created_at' => now(), 'updated_at' => now()]
    );

    $rows = (new MrpService(app(FgStockService::class)))->explode([now()->format('Ym')]);
    $fg = collect($rows)->firstWhere('item_id', (int) $project->item_id);

    expect($fg['open_wo'])->toBe(0)
        ->and($fg['net_req'])->toBe(100);
});

it('menghapus trial berikut Work Order uji cobanya', function () {
    Sanctum::actingAs(admin());
    $project = npdTrialProject();
    $trial = npdTrial($project);
    $woId = $trial->wo_id;

    $this->deleteJson("/api/v1/npd-trials/{$trial->id}")->assertOk();

    expect(DB::table('prd_wo_main')->where('id', $woId)->exists())->toBeFalse()
        ->and(npd_trial_main::find($trial->id))->toBeNull();
});

it('menyajikan trial dan parameter berspesifikasi lewat HTTP', function () {
    Sanctum::actingAs(admin());
    $project = npdTrialProject();
    npdTrial($project);

    $res = $this->getJson("/api/v1/npd-trials/project/{$project->id}")->assertOk()
        ->assertJsonStructure(['data' => ['project', 'trials', 'params']]);

    // Daftar parameter membawa spesifikasi yang berlaku, supaya penguji tidak
    // mengetik ulang toleransi yang sudah ada di master.
    expect($res->json('data.params.0'))->toHaveKeys(['id', 'code', 'nominal', 'min_value', 'max_value', 'source']);
});
