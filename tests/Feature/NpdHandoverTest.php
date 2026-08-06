<?php

use App\Exceptions\BizException;
use App\Models\npd_bom_main;
use App\Models\npd_cp_main;
use App\Models\npd_project;
use App\Support\ItemLifecycle;
use App\Support\NpdCostingService;
use App\Support\NpdHandoverService;
use App\Support\NpdPpapService;
use App\Support\NpdQualityService;
use App\Support\NpdService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * NPD Tahap 6 — serah terima ke produksi.
 *
 * Inilah alasan seluruh modul ini ada: keluaran proyek berhenti menjadi lampiran
 * dan menjadi master yang benar-benar dipakai pabrik. Yang diuji di sini adalah
 * bahwa penyalinannya utuh, syaratnya tidak bisa dilewati, dan seluruhnya
 * terjadi bersama — tidak pernah ada keadaan "BOM sudah jadi tapi routing belum".
 */

/** Proyek lengkap: part terdaftar, BOM, control plan final, PPAP disetujui, 5 gate lolos. */
function handoverReadyProject(array $opts = []): npd_project
{
    $rfq = rfqFor();
    feasFor($rfq);
    $project = app(NpdService::class)->createFromRfq($rfq, ['name' => 'Proyek serah terima'], admin()->id);

    app(NpdCostingService::class)->registerPart($project, [
        'code' => 'FG-HO-'.substr(uniqid(), -6),
        'category_id' => DB::table('m_i_category')->value('id'),
    ], admin()->id);
    $project = $project->fresh();

    // Preliminary BOM: satu material yang sudah ada di master.
    $f = mrpFixture(['plan' => 10]);
    $bom = npd_bom_main::create([
        'main_id' => $project->id, 'version' => 'v1', 'status' => 'APPROVED',
        'user_id' => admin()->id,
    ]);
    $bom->detail()->create([
        'item_id' => ($opts['unresolved'] ?? false) ? null : $f['rm'],
        'new_item_name' => ($opts['unresolved'] ?? false) ? 'Bracket belum terdaftar' : null,
        'role' => 'RM', 'qty' => 2, 'length_use' => 600, 'unit_cost' => 0, 'cost_source' => 'MANUAL',
    ]);

    // Control plan final: sumber urutan proses dan parameter QC.
    if (! ($opts['no_cp'] ?? false)) {
        $cp = npd_cp_main::create([
            'main_id' => $project->id, 'code' => 'CP-'.substr(uniqid(), -8),
            'cp_type' => 'PRODUCTION', 'date' => now()->toDateString(),
            'status' => 'DRAFT', 'user_id' => admin()->id,
        ]);
        $procs = DB::table('m_process')->orderBy('id')->limit(2)->pluck('id');
        $param = DB::table('m_inspection_param')->value('id');

        $cp->detail()->create([
            'seq' => 1, 'proc_id' => $procs[0], 'param_id' => $param,
            'nominal' => 10, 'min_value' => 9.8, 'max_value' => 10.2,
            'sample_size' => 5, 'frequency' => 'tiap 50 pcs', 'to_item_inspection' => true,
        ]);
        $cp->detail()->create([
            'seq' => 2, 'proc_id' => $procs[1] ?? $procs[0],
            'sample_size' => 1, 'method' => 'Cek tekanan hidrolik', 'to_item_inspection' => false,
        ]);

        app(NpdQualityService::class)->finalizeCp($cp->fresh());
    }

    // Lima gate lolos.
    foreach (range(1, 5) as $no) {
        $phase = $project->phases()->where('phase_no', $no)->first();
        $phase->deliverables()->update(['status' => 'DONE']);
        $phase->update(['status' => 'APPROVED']);
        if ($no < 5) {
            $project->phases()->where('phase_no', $no + 1)->update(['status' => 'RUNNING']);
        }
    }
    $project->update(['status' => 'HANDOVER', 'current_phase_no' => 5]);

    // PPAP disetujui pelanggan.
    if (! ($opts['no_ppap'] ?? false)) {
        $ppap = app(NpdPpapService::class)->create($project->fresh(), ['ppap_level' => 1], admin()->id);
        $psw = $ppap->detail->first(fn ($d) => $d->std->element_no === 18);
        app(NpdPpapService::class)->updateElement($ppap, $psw->id, ['status' => 'DONE']);
        $sent = app(NpdPpapService::class)->submit($ppap->fresh(), ['psw_no' => 'PSW-HO']);
        app(NpdPpapService::class)->recordDecision($sent, ['status' => 'APPROVED']);
    }

    return $project->fresh();
}

it('membuat BOM, routing, parameter QC, dan menaikkan part dalam satu tindakan', function () {
    $project = handoverReadyProject();
    $procs = DB::table('m_process')->orderBy('id')->limit(2)->pluck('id');

    $result = app(NpdHandoverService::class)->execute($project, [
        'cycle_times' => [
            ['proc_id' => $procs[0], 'cycle_sec' => 42.5, 'setup_min' => 15],
            ['proc_id' => $procs[1] ?? $procs[0], 'cycle_sec' => 30],
        ],
    ], admin()->id);

    expect($result['bom_id'])->not->toBeNull()
        ->and($result['rm_lines'])->toBe(1)
        ->and($result['process_main_id'])->not->toBeNull()
        ->and($result['routing_steps'])->toBeGreaterThan(0)
        ->and($result['inspection_params'])->toBe(1)   // hanya baris bertanda "→ QC"
        ->and($result['route_times'])->toBeGreaterThan(0);

    $item = DB::table('m_item')->where('id', $project->item_id)->first();

    // Part diterima produksi, dan proyeknya ditutup.
    expect($item->lifecycle)->toBe(ItemLifecycle::MASSPRO)
        ->and((int) $item->active)->toBe(1)
        ->and($project->fresh()->status)->toBe('CLOSED')
        ->and($project->fresh()->handover_date)->not->toBeNull();

    // Master yang lahir tetap DRAFT: approval engineering tidak ditembus.
    expect(DB::table('m_bom')->where('id', $result['bom_id'])->value('status'))->toBe('DRAFT')
        ->and(DB::table('m_process_main')->where('id', $result['process_main_id'])->value('status'))->toBe('DRAFT');

    // Routing terdaftar sebagai pilihan pertama part ini.
    expect(DB::table('m_bom_pro')->where('item_id', $project->item_id)
        ->where('process_main_id', $result['process_main_id'])->value('priority'))->toBe(1);
});

it('menyalin hanya baris control plan yang ditandai untuk QC', function () {
    $project = handoverReadyProject();

    app(NpdHandoverService::class)->execute($project, [], admin()->id);

    // Baris kedua control plan mengendalikan setelan mesin, bukan ciri barang —
    // QC tidak mengukur setelan.
    expect(DB::table('m_item_inspection')->where('item_id', $project->item_id)->count())->toBe(1);

    $spec = DB::table('m_item_inspection')->where('item_id', $project->item_id)->first();
    expect((float) $spec->min_value)->toBe(9.8)
        ->and((float) $spec->max_value)->toBe(10.2);
});

it('menahan serah terima selama BOM masih memuat part yang belum terdaftar', function () {
    $project = handoverReadyProject(['unresolved' => true]);

    $blockers = app(NpdHandoverService::class)->blockers($project);

    expect(collect($blockers)->contains(fn ($b) => str_contains($b, 'belum terdaftar')))->toBeTrue();
    expect(fn () => app(NpdHandoverService::class)->execute($project, [], admin()->id))
        ->toThrow(BizException::class);
});

it('menahan serah terima tanpa control plan final', function () {
    $project = handoverReadyProject(['no_cp' => true]);

    expect(collect(app(NpdHandoverService::class)->blockers($project))
        ->contains(fn ($b) => str_contains($b, 'control plan')))->toBeTrue();
});

it('menahan serah terima tanpa PPAP yang disetujui', function () {
    $project = handoverReadyProject(['no_ppap' => true]);

    expect(collect(app(NpdHandoverService::class)->blockers($project))
        ->contains(fn ($b) => str_contains($b, 'PPAP')))->toBeTrue();
});

it('menyebut seluruh penghalang sekaligus, bukan satu per satu', function () {
    $rfq = rfqFor();
    feasFor($rfq);
    $project = app(NpdService::class)->createFromRfq($rfq, [], admin()->id);

    $blockers = app(NpdHandoverService::class)->blockers($project);

    // SPV yang harus memperbaiki empat hal lebih baik mengetahui keempatnya
    // sekarang daripada menemukannya berurutan dalam empat percobaan.
    expect(count($blockers))->toBeGreaterThanOrEqual(4);
});

it('tidak mengulang pembuatan BOM bila sudah pernah dibuat', function () {
    $project = handoverReadyProject();
    $existing = DB::table('m_bom')->insertGetId([
        'item_id' => $project->item_id, 'active' => 1, 'status' => 'DRAFT', 'rev' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $project->update(['bom_id' => $existing]);

    $result = app(NpdHandoverService::class)->execute($project->fresh(), [], admin()->id);

    expect($result['bom_id'])->toBe($existing)
        ->and($result['rm_lines'])->toBe(0);
});

it('melewatkan cycle time yang dikosongkan', function () {
    $project = handoverReadyProject();
    $proc = DB::table('m_process')->value('id');

    $result = app(NpdHandoverService::class)->execute($project, [
        'cycle_times' => [['proc_id' => $proc, 'cycle_sec' => 0]],
    ], admin()->id);

    expect($result['route_times'])->toBe(0);
});

it('menyajikan pratinjau berisi apa yang akan dibuat dan apa yang menahan', function () {
    Sanctum::actingAs(admin());
    $project = handoverReadyProject();

    $p = $this->getJson("/api/v1/npd-projects/{$project->id}/handover-preview")->assertOk()->json('data');

    expect($p['can_handover'])->toBeTrue()
        ->and($p['blockers'])->toBe([])
        ->and($p['will_create']['bom']['rm_lines'])->toBe(1)
        ->and($p['will_create']['routing']['steps'])->not->toBeEmpty()
        ->and($p['will_create']['inspection_params'])->toHaveCount(1);
});

it('menjalankan serah terima lewat HTTP dan menolak pengulangan', function () {
    Sanctum::actingAs(admin());
    $project = handoverReadyProject();

    $this->postJson("/api/v1/npd-projects/{$project->id}/handover")->assertOk();

    // Sekali saja: part-nya sudah bukan uji coba lagi.
    $this->postJson("/api/v1/npd-projects/{$project->id}/handover")->assertStatus(422);
});
