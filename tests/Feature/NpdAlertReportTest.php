<?php

use App\Support\AlertService;
use App\Support\NpdService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * NPD Tahap 7 — peringatan dan laporan.
 *
 * Proyek pengembangan berjalan berbulan-bulan dan tidak ada yang membuka
 * daftarnya setiap hari. Yang diuji di sini adalah bahwa tiga keadaan yang
 * membuat proyek diam — task lewat tanggal, gate menggantung, target SOP
 * terlampaui — muncul sendiri, dan berhenti muncul begitu keadaannya berubah.
 */
function alertProject(array $over = []): object
{
    $rfq = rfqFor();
    feasFor($rfq);
    $project = app(NpdService::class)->createFromRfq($rfq, ['name' => 'Proyek alert'], admin()->id);

    if ($over) {
        $project->update($over);
    }

    return $project->fresh();
}

/** Peringatan terbuka satu jenis untuk satu proyek. */
function npdAlert(string $type, int $projectId): ?object
{
    return DB::table('sys_alert')
        ->where('type', $type)->where('ref_type', 'npd_project')->where('ref_id', $projectId)
        ->whereNull('resolved_at')
        ->first();
}

it('memperingatkan task yang lewat tanggal, satu baris per proyek', function () {
    $project = alertProject();
    $phase = $project->phases()->where('phase_no', 1)->first();

    // Tiga task telat; PM butuh satu baris yang menyebut tiga, bukan tiga baris.
    foreach (range(1, 3) as $i) {
        $phase->tasks()->create([
            'name' => "Task telat {$i}",
            'planned_end' => now()->subDays(5 + $i)->toDateString(),
            'status' => 'RUNNING',
        ]);
    }

    app(AlertService::class)->checkNpd();

    $alert = npdAlert(AlertService::NPD_TASK, $project->id);

    expect($alert)->not->toBeNull()
        ->and($alert->title)->toContain('3 task')
        ->and((float) $alert->value)->toBe(3.0);
});

it('menutup peringatan task begitu semuanya selesai', function () {
    $project = alertProject();
    $phase = $project->phases()->where('phase_no', 1)->first();
    $phase->tasks()->create(['name' => 'Telat', 'planned_end' => now()->subWeek()->toDateString(), 'status' => 'RUNNING']);

    app(AlertService::class)->checkNpd();
    expect(npdAlert(AlertService::NPD_TASK, $project->id))->not->toBeNull();

    $phase->tasks()->update(['status' => 'DONE']);
    app(AlertService::class)->checkNpd();

    // Keadaan yang sudah beres menutup dirinya sendiri.
    expect(npdAlert(AlertService::NPD_TASK, $project->id))->toBeNull();
});

it('memperingatkan gate yang menggantung, tetapi tidak yang baru diajukan', function () {
    Sanctum::actingAs(admin());
    $project = alertProject();
    $phase = $project->phases()->where('phase_no', 1)->first();
    $phase->deliverables()->update(['status' => 'DONE']);
    app(NpdService::class)->submitGate($phase->fresh());

    // Baru diajukan hari ini — belum pantas diganggu.
    app(AlertService::class)->checkNpd();
    expect(npdAlert(AlertService::NPD_GATE, $project->id))->toBeNull();

    // Mundurkan pengajuannya melewati ambang.
    DB::table('approvals')->where('doc_type', 'npd_project_phase')->where('doc_id', $phase->id)
        ->update(['created_at' => now()->subDays(AlertService::GATE_STALE_DAYS + 2)]);

    app(AlertService::class)->checkNpd();
    $alert = npdAlert(AlertService::NPD_GATE, $project->id);

    expect($alert)->not->toBeNull()
        ->and($alert->title)->toContain('gate fase 1');
});

it('memperingatkan target SOP yang sudah terlewat', function () {
    $project = alertProject(['target_sop' => now()->subDays(10)->toDateString()]);

    app(AlertService::class)->checkNpd();
    $alert = npdAlert(AlertService::NPD_SOP, $project->id);

    // Pelanggan biasanya sudah menjadwalkan pesanannya — ini selalu kritis.
    expect($alert)->not->toBeNull()
        ->and($alert->severity)->toBe('CRITICAL');
});

it('tidak mengganggu proyek yang target SOP-nya masih di depan', function () {
    $project = alertProject(['target_sop' => now()->addMonths(2)->toDateString()]);

    app(AlertService::class)->checkNpd();

    expect(npdAlert(AlertService::NPD_SOP, $project->id))->toBeNull();
});

it('menutup seluruh peringatan proyek yang sudah tidak berjalan', function () {
    $project = alertProject(['target_sop' => now()->subDays(5)->toDateString()]);
    app(AlertService::class)->checkNpd();
    expect(npdAlert(AlertService::NPD_SOP, $project->id))->not->toBeNull();

    // Proyek dibatalkan; peringatannya tidak boleh menggantung selamanya.
    $project->update(['status' => 'CANCELLED']);
    app(AlertService::class)->checkNpd();

    expect(npdAlert(AlertService::NPD_SOP, $project->id))->toBeNull();
});

it('menaikkan satu peringatan per kondisi, berapa kali pun diperiksa', function () {
    $project = alertProject(['target_sop' => now()->subDays(3)->toDateString()]);

    app(AlertService::class)->checkNpd();
    app(AlertService::class)->checkNpd();
    app(AlertService::class)->checkNpd();

    $count = DB::table('sys_alert')
        ->where('type', AlertService::NPD_SOP)->where('ref_id', $project->id)->count();

    expect($count)->toBe(1);
});

it('menghitung lead time rata-rata hanya dari proyek yang sudah selesai', function () {
    // Proyek yang baru dimulai kemarin akan menurunkan rata-rata secara palsu
    // kalau ikut dihitung.
    alertProject();

    $report = app(NpdService::class)->report();

    expect($report['lead_time']['ongoing'])->toBeGreaterThan(0)
        ->and($report['lead_time']['avg_days'])->toBeNull();
});

it('menyajikan KPI lengkap lewat HTTP', function () {
    Sanctum::actingAs(admin());

    $this->getJson('/api/v1/npd/report')->assertOk()
        ->assertJsonStructure(['data' => [
            'as_of',
            'lead_time' => ['avg_days', 'completed', 'ongoing'],
            'on_time' => ['with_target', 'on_time', 'pct'],
            'gate_rejects' => ['total', 'recent'],
            'ppap' => ['total', 'approved'],
            'adoption' => ['handed_over', 'still_trial'],
            'projects',
        ]]);
});

it('ikut memeriksa proyek NPD lewat tombol cek peringatan', function () {
    Sanctum::actingAs(admin());

    $this->postJson('/api/v1/alerts/refresh')->assertOk()
        ->assertJsonStructure(['data' => ['quota', 'min_stock', 'npd', 'message']]);
});
