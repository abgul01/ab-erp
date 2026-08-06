<?php

use App\Exceptions\BizException;
use App\Models\npd_cp_main;
use App\Models\npd_fmea_main;
use App\Models\npd_project;
use App\Support\NpdQualityService;
use App\Support\NpdService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * NPD Tahap 4 — FMEA dan Control Plan.
 *
 * FMEA hanya berguna kalau angkanya tidak bisa dinegosiasikan dan risiko besar
 * tidak bisa dibiarkan tanpa tindakan. Control plan hanya berguna kalau ia
 * lahir dari FMEA dan barisnya cukup lengkap untuk dipakai QC produksi.
 */
function qualityProject(): npd_project
{
    $rfq = rfqFor();
    feasFor($rfq);

    return app(NpdService::class)->createFromRfq($rfq, ['name' => 'Proyek kualitas'], admin()->id);
}

function fmeaFor(npd_project $project, string $type = 'PROCESS', int $threshold = 100): npd_fmea_main
{
    return npd_fmea_main::create([
        'main_id' => $project->id,
        'fmea_type' => $type,
        'code' => 'FMEA-'.substr(uniqid(), -8),
        'revision' => 'rev A',
        'date' => now()->toDateString(),
        'rpn_threshold' => $threshold,
        'status' => 'DRAFT',
        'user_id' => admin()->id,
    ]);
}

function risk(array $attrs = []): array
{
    return array_merge([
        'proc_id' => DB::table('m_process')->value('id'),
        'item_function' => 'Memotong pipa sesuai panjang',
        'failure_mode' => 'Panjang potong di luar toleransi',
        'effect' => 'Part tidak bisa dirakit pelanggan',
        'severity' => 5,
        'cause' => 'Stopper bergeser',
        'occurrence' => 3,
        'current_control' => 'Cek dimensi tiap 50 pcs',
        'detection' => 4,
    ], $attrs);
}

it('menghitung RPN sebagai S × O × D, bukan dari angka yang diketik', function () {
    $fmea = fmeaFor(qualityProject());

    $saved = app(NpdQualityService::class)->saveFmeaLines($fmea, [
        risk(['severity' => 5, 'occurrence' => 3, 'detection' => 4]),   // 60
    ]);

    expect((int) $saved->detail->first()->rpn)->toBe(60);
});

it('menolak risiko di atas ambang yang tidak punya tindakan perbaikan', function () {
    $fmea = fmeaFor(qualityProject(), 'PROCESS', 100);

    // 8 × 5 × 4 = 160, jauh di atas ambang — FMEA yang mencatat itu tanpa
    // tindakan bukan analisis risiko, melainkan daftar hal yang akan gagal.
    expect(fn () => app(NpdQualityService::class)->saveFmeaLines($fmea, [
        risk(['severity' => 8, 'occurrence' => 5, 'detection' => 4]),
    ]))->toThrow(BizException::class);

    // Dengan tindakan: diterima.
    $saved = app(NpdQualityService::class)->saveFmeaLines($fmea, [
        risk(['severity' => 8, 'occurrence' => 5, 'detection' => 4, 'recommended_action' => 'Pasang stopper terkunci baut']),
    ]);

    expect((int) $saved->detail->first()->rpn)->toBe(160);
});

it('menolak finalisasi FMEA yang tindakannya belum selesai', function () {
    $fmea = fmeaFor(qualityProject());
    app(NpdQualityService::class)->saveFmeaLines($fmea, [
        risk(['severity' => 8, 'occurrence' => 5, 'detection' => 4, 'recommended_action' => 'Pasang stopper terkunci', 'status' => 'OPEN']),
    ]);

    expect(fn () => app(NpdQualityService::class)->finalizeFmea($fmea->fresh()))
        ->toThrow(BizException::class);

    $fmea->detail()->update(['status' => 'DONE']);

    expect(app(NpdQualityService::class)->finalizeFmea($fmea->fresh())->status)->toBe('FINAL');
});

it('membiarkan risiko di bawah ambang tetap terbuka saat finalisasi', function () {
    $fmea = fmeaFor(qualityProject());
    app(NpdQualityService::class)->saveFmeaLines($fmea, [
        risk(['severity' => 3, 'occurrence' => 2, 'detection' => 3, 'status' => 'OPEN']),   // 18
    ]);

    // Ambangnya ada justru supaya perhatian tertuju ke yang besar.
    expect(app(NpdQualityService::class)->finalizeFmea($fmea->fresh())->status)->toBe('FINAL');
});

it('mengunci FMEA yang sudah final', function () {
    $fmea = fmeaFor(qualityProject());
    app(NpdQualityService::class)->saveFmeaLines($fmea, [risk()]);
    app(NpdQualityService::class)->finalizeFmea($fmea->fresh());

    expect(fn () => app(NpdQualityService::class)->saveFmeaLines($fmea->fresh(), [risk()]))
        ->toThrow(BizException::class);
});

it('menyusun control plan dari risiko PFMEA di atas ambang saja', function () {
    $project = qualityProject();
    $fmea = fmeaFor($project);

    app(NpdQualityService::class)->saveFmeaLines($fmea, [
        risk(['failure_mode' => 'Panjang di luar toleransi', 'severity' => 8, 'occurrence' => 5, 'detection' => 4, 'recommended_action' => 'Stopper terkunci']),
        risk(['failure_mode' => 'Gores permukaan', 'severity' => 2, 'occurrence' => 2, 'detection' => 2]),   // 8, di bawah ambang
    ]);

    $cp = npd_cp_main::create([
        'main_id' => $project->id, 'code' => 'CP-'.substr(uniqid(), -8),
        'cp_type' => 'PROTOTYPE', 'date' => now()->toDateString(), 'status' => 'DRAFT', 'user_id' => admin()->id,
    ]);

    $result = app(NpdQualityService::class)->generateCpFromFmea($cp, $fmea->fresh());

    expect($result->detail)->toHaveCount(1)
        // Jejak "kenapa ini diukur" ikut tersimpan.
        ->and($result->detail->first()->fmea_det_id)->not->toBeNull();
});

it('tidak menggandakan baris saat penyusunan dijalankan ulang', function () {
    $project = qualityProject();
    $fmea = fmeaFor($project);
    app(NpdQualityService::class)->saveFmeaLines($fmea, [
        risk(['severity' => 8, 'occurrence' => 5, 'detection' => 4, 'recommended_action' => 'Stopper terkunci']),
    ]);

    $cp = npd_cp_main::create([
        'main_id' => $project->id, 'code' => 'CP-'.substr(uniqid(), -8),
        'cp_type' => 'PROTOTYPE', 'date' => now()->toDateString(), 'status' => 'DRAFT', 'user_id' => admin()->id,
    ]);

    app(NpdQualityService::class)->generateCpFromFmea($cp, $fmea->fresh());
    $again = app(NpdQualityService::class)->generateCpFromFmea($cp->fresh(), $fmea->fresh());

    // Menjalankan ulang setelah PFMEA ditambah barisnya harus menambah, bukan menduplikasi.
    expect($again->detail)->toHaveCount(1);
});

it('menolak menyusun control plan dari DFMEA', function () {
    $project = qualityProject();
    $dfmea = fmeaFor($project, 'DESIGN');
    app(NpdQualityService::class)->saveFmeaLines($dfmea, [
        risk(['proc_id' => null, 'severity' => 8, 'occurrence' => 5, 'detection' => 4, 'recommended_action' => 'Ubah radius tekukan']),
    ]);

    $cp = npd_cp_main::create([
        'main_id' => $project->id, 'code' => 'CP-'.substr(uniqid(), -8),
        'cp_type' => 'PROTOTYPE', 'date' => now()->toDateString(), 'status' => 'DRAFT', 'user_id' => admin()->id,
    ]);

    // Control plan mengendalikan proses, jadi sumbernya PFMEA.
    expect(fn () => app(NpdQualityService::class)->generateCpFromFmea($cp, $dfmea->fresh()))
        ->toThrow(BizException::class);
});

it('menolak finalisasi control plan yang baris QC-nya belum lengkap', function () {
    $project = qualityProject();
    $cp = npd_cp_main::create([
        'main_id' => $project->id, 'code' => 'CP-'.substr(uniqid(), -8),
        'cp_type' => 'PRODUCTION', 'date' => now()->toDateString(), 'status' => 'DRAFT', 'user_id' => admin()->id,
    ]);

    // Ditandai akan jadi parameter QC, tetapi tanpa parameter dan batas ukur —
    // QC produksi tidak bisa memutuskan apa pun dengan baris seperti ini.
    $cp->detail()->create(['seq' => 1, 'to_item_inspection' => true, 'sample_size' => 1]);

    expect(fn () => app(NpdQualityService::class)->finalizeCp($cp->fresh()))
        ->toThrow(BizException::class);

    // Melengkapinya membuat baris itu sah.
    $cp->detail()->update([
        'param_id' => DB::table('m_inspection_param')->value('id'),
        'min_value' => 9.8, 'max_value' => 10.2,
    ]);

    expect(app(NpdQualityService::class)->finalizeCp($cp->fresh())->status)->toBe('FINAL');
});

it('membiarkan baris kendali setelan mesin tanpa parameter', function () {
    $project = qualityProject();
    $cp = npd_cp_main::create([
        'main_id' => $project->id, 'code' => 'CP-'.substr(uniqid(), -8),
        'cp_type' => 'PRODUCTION', 'date' => now()->toDateString(), 'status' => 'DRAFT', 'user_id' => admin()->id,
    ]);

    // Tidak semua baris control plan pantas jadi parameter QC produksi.
    $cp->detail()->create(['seq' => 1, 'to_item_inspection' => false, 'sample_size' => 1, 'method' => 'Cek tekanan hidrolik']);

    expect(app(NpdQualityService::class)->finalizeCp($cp->fresh())->status)->toBe('FINAL');
});

it('menolak menghapus FMEA yang jadi dasar control plan', function () {
    Sanctum::actingAs(admin());
    $project = qualityProject();
    $fmea = fmeaFor($project);
    app(NpdQualityService::class)->saveFmeaLines($fmea, [
        risk(['severity' => 8, 'occurrence' => 5, 'detection' => 4, 'recommended_action' => 'Stopper terkunci']),
    ]);

    $cp = npd_cp_main::create([
        'main_id' => $project->id, 'code' => 'CP-'.substr(uniqid(), -8),
        'cp_type' => 'PROTOTYPE', 'date' => now()->toDateString(), 'status' => 'DRAFT', 'user_id' => admin()->id,
    ]);
    app(NpdQualityService::class)->generateCpFromFmea($cp, $fmea->fresh());

    // Membuangnya akan menghapus jejak "kenapa ini diukur".
    $this->deleteJson("/api/v1/npd-quality/fmea/{$fmea->id}")->assertStatus(422);
});

it('menyajikan FMEA, control plan, dan masternya lewat HTTP', function () {
    Sanctum::actingAs(admin());
    $project = qualityProject();

    $res = $this->postJson("/api/v1/npd-quality/project/{$project->id}/fmea", [
        'fmea_type' => 'PROCESS', 'date' => now()->toDateString(), 'rpn_threshold' => 120,
    ])->assertCreated()->json('data');

    expect($res['code'])->toStartWith('PFMEA/');

    $this->getJson("/api/v1/npd-quality/project/{$project->id}")->assertOk()
        ->assertJsonStructure(['data' => ['project', 'fmeas', 'control_plans', 'processes', 'params']]);
});
