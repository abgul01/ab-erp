<?php

use App\Exceptions\BizException;
use App\Models\npd_cp_main;
use App\Models\npd_ppap_main;
use App\Models\npd_project;
use App\Support\NpdCostingService;
use App\Support\NpdHandoverService;
use App\Support\NpdPpapService;
use App\Support\NpdQualityService;
use App\Support\NpdService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * NPD Tahap 5 — PPAP.
 *
 * Sasaran PRD §2 menyebut "kelengkapan dokumen PPAP tercek otomatis sebelum
 * submission". Itulah yang diuji di sini: enam elemen dijawab sistem dari data
 * yang sudah ada, dan submission ditolak selama elemen wajib untuk levelnya
 * masih kosong.
 */
function ppapProject(): npd_project
{
    $rfq = rfqFor();
    feasFor($rfq);

    return app(NpdService::class)->createFromRfq($rfq, ['name' => 'Proyek PPAP'], admin()->id);
}

function ppapFor(npd_project $project, int $level = 3): npd_ppap_main
{
    return app(NpdPpapService::class)->create($project, ['ppap_level' => $level], admin()->id);
}

it('membuat seluruh 18 elemen saat submission dibuat', function () {
    $ppap = ppapFor(ppapProject());

    expect($ppap->detail)->toHaveCount(18)
        ->and($ppap->detail->pluck('std.element_no')->sort()->values()->all())->toBe(range(1, 18));
});

it('mewajibkan hanya PSW pada level 1, dan paket lengkap pada level 3', function () {
    $project = ppapProject();

    $level1 = ppapFor($project, 1);
    $level3 = ppapFor($project, 3);

    // Level 1: PSW saja yang menahan.
    expect($level1->outstanding()->pluck('std.element_no')->all())->toBe([18]);

    // Level 3: banyak elemen wajib, dan sebagiannya sudah dijawab sistem.
    expect($level3->outstanding()->count())->toBeGreaterThan(5);
});

it('menandai elemen yang buktinya sudah ada di dalam sistem', function () {
    $project = ppapProject();

    // Bangun buktinya: PFMEA final, control plan final, trial selesai.
    $fmea = fmeaFor($project, 'PROCESS');
    app(NpdQualityService::class)->saveFmeaLines($fmea, [risk()]);
    app(NpdQualityService::class)->finalizeFmea($fmea->fresh());

    $cp = npd_cp_main::create([
        'main_id' => $project->id, 'code' => 'CP-'.substr(uniqid(), -8),
        'cp_type' => 'PRODUCTION', 'date' => now()->toDateString(), 'status' => 'DRAFT', 'user_id' => admin()->id,
    ]);
    $cp->detail()->create(['seq' => 1, 'to_item_inspection' => false, 'sample_size' => 1]);
    app(NpdQualityService::class)->finalizeCp($cp->fresh());

    $ppap = ppapFor($project);
    $byNo = $ppap->detail->keyBy(fn ($d) => $d->std->element_no);

    // #6 PFMEA dan #7 control plan terjawab sendiri, lengkap dengan asalnya.
    expect($byNo[6]->status)->toBe('DONE')
        ->and($byNo[6]->auto_source)->toBe('PFMEA final')
        ->and($byNo[7]->status)->toBe('DONE')
        // #4 DFMEA belum ada, jadi tetap terbuka.
        ->and($byNo[4]->status)->toBe('OPEN');
});

it('tidak membatalkan elemen yang sudah ditandai manusia', function () {
    $project = ppapProject();
    $ppap = ppapFor($project);

    $line = $ppap->detail->first(fn ($d) => $d->std->element_no === 8);   // MSA, tanpa bukti otomatis
    app(NpdPpapService::class)->updateElement($ppap, $line->id, [
        'status' => 'DONE', 'note' => 'Laporan MSA dilampirkan manual',
    ]);

    // Pemeriksaan otomatis hanya menaikkan status, tidak pernah menurunkan.
    $synced = app(NpdPpapService::class)->syncFromProject($ppap->fresh());
    $after = $synced->detail->first(fn ($d) => $d->std->element_no === 8);

    expect($after->status)->toBe('DONE');
});

it('mewajibkan alasan saat elemen ditandai tidak berlaku', function () {
    $ppap = ppapFor(ppapProject());
    $line = $ppap->detail->first(fn ($d) => $d->std->element_no === 13);   // AAR

    expect(fn () => app(NpdPpapService::class)->updateElement($ppap, $line->id, ['status' => 'NA']))
        ->toThrow(BizException::class);

    // Dengan alasan: sah — AAR memang tidak berlaku untuk part tanpa
    // persyaratan penampilan.
    $ok = app(NpdPpapService::class)->updateElement($ppap, $line->id, [
        'status' => 'NA', 'note' => 'Part tidak punya persyaratan penampilan',
    ]);

    expect($ok->detail->first(fn ($d) => $d->std->element_no === 13)->status)->toBe('NA');
});

it('menolak pengiriman selama elemen wajib belum lengkap, dan menyebut yang mana', function () {
    $ppap = ppapFor(ppapProject(), 3);

    try {
        app(NpdPpapService::class)->submit($ppap, ['psw_no' => 'PSW-001']);
        $this->fail('Seharusnya ditolak.');
    } catch (BizException $e) {
        // Bahasa yang dipakai pelanggan saat menolak adalah nomor elemennya.
        expect($e->getMessage())->toContain('#18')
            ->and($e->getMessage())->toContain('level 3');
    }
});

it('menuntut nomor PSW sebelum dikirim', function () {
    $project = ppapProject();
    $ppap = ppapFor($project, 1);

    // Level 1 hanya menuntut PSW sebagai elemen; tandai ada, tapi nomornya kosong.
    $psw = $ppap->detail->first(fn ($d) => $d->std->element_no === 18);
    app(NpdPpapService::class)->updateElement($ppap, $psw->id, ['status' => 'DONE']);

    expect(fn () => app(NpdPpapService::class)->submit($ppap->fresh(), []))
        ->toThrow(BizException::class);
});

it('mengirim submission yang elemen wajibnya lengkap, lalu menguncinya', function () {
    $project = ppapProject();
    $ppap = ppapFor($project, 1);

    $psw = $ppap->detail->first(fn ($d) => $d->std->element_no === 18);
    app(NpdPpapService::class)->updateElement($ppap, $psw->id, ['status' => 'DONE']);

    $sent = app(NpdPpapService::class)->submit($ppap->fresh(), ['psw_no' => 'PSW-2026-001']);

    expect($sent->status)->toBe('SUBMITTED')
        ->and($sent->psw_no)->toBe('PSW-2026-001');

    // Setelah dikirim, isinya tidak boleh berubah di tangan pengirimnya.
    expect(fn () => app(NpdPpapService::class)->updateElement($sent, $psw->id, ['status' => 'OPEN']))
        ->toThrow(BizException::class);
});

it('mencatat jawaban pelanggan dan hanya APPROVED yang membuka serah terima', function () {
    $project = ppapProject();
    $ppap = ppapFor($project, 1);
    $psw = $ppap->detail->first(fn ($d) => $d->std->element_no === 18);
    app(NpdPpapService::class)->updateElement($ppap, $psw->id, ['status' => 'DONE']);
    $sent = app(NpdPpapService::class)->submit($ppap->fresh(), ['psw_no' => 'PSW-X']);

    // INTERIM adalah izin sementara dengan syarat — belum membuka apa pun.
    $interim = app(NpdPpapService::class)->recordDecision($sent, ['status' => 'INTERIM']);
    expect(app(NpdPpapService::class)->hasApproved($project->id))->toBeFalse();

    app(NpdPpapService::class)->recordDecision($interim, ['status' => 'APPROVED']);
    expect(app(NpdPpapService::class)->hasApproved($project->id))->toBeTrue();
});

it('menahan kenaikan part ke produksi massal sampai PPAP disetujui', function () {
    Sanctum::actingAs(admin());
    $project = ppapProject();

    app(NpdCostingService::class)->registerPart($project, [
        'code' => 'FG-PPAP-'.substr(uniqid(), -6),
        'category_id' => DB::table('m_i_category')->value('id'),
    ], admin()->id);

    for ($no = 1; $no <= 5; $no++) {
        $phase = $project->phases()->where('phase_no', $no)->first();
        $phase->deliverables()->update(['status' => 'DONE']);
        $this->postJson("/api/v1/npd-projects/{$project->id}/gate/{$no}/submit")->assertOk();
        $this->postJson("/api/v1/npd-projects/{$project->id}/gate/{$no}/approve")->assertOk();
        $this->postJson("/api/v1/npd-projects/{$project->id}/gate/{$no}/approve")->assertOk();
    }

    // Lima gate lolos, tetapi pelanggan belum mengizinkan produksi massal —
    // dan itu muncul sebagai salah satu penghalang serah terima.
    $blockers = app(NpdHandoverService::class)->blockers($project->fresh());
    expect(collect($blockers)->contains(fn ($b) => str_contains($b, 'PPAP')))->toBeTrue();

    $ppap = ppapFor($project->fresh(), 1);
    $psw = $ppap->detail->first(fn ($d) => $d->std->element_no === 18);
    app(NpdPpapService::class)->updateElement($ppap, $psw->id, ['status' => 'DONE']);
    $sent = app(NpdPpapService::class)->submit($ppap->fresh(), ['psw_no' => 'PSW-Y']);
    app(NpdPpapService::class)->recordDecision($sent, ['status' => 'APPROVED']);

    // Setelah disetujui, PPAP tidak lagi menahan (syarat lain diuji terpisah).
    $after = app(NpdHandoverService::class)->blockers($project->fresh());
    expect(collect($after)->contains(fn ($b) => str_contains($b, 'PPAP')))->toBeFalse();
});

it('menyajikan submission, elemen, dan dokumen lewat HTTP', function () {
    Sanctum::actingAs(admin());
    $project = ppapProject();

    $this->postJson("/api/v1/npd-ppap/project/{$project->id}", ['ppap_level' => 3])->assertCreated();

    $this->getJson("/api/v1/npd-ppap/project/{$project->id}")->assertOk()
        ->assertJsonStructure(['data' => ['project', 'submissions', 'elements', 'docs']]);
});
