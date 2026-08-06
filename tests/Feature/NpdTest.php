<?php

use App\Exceptions\BizException;
use App\Models\npd_rfq;
use App\Support\NpdService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * NPD — Tahap 1: dari RFQ menjadi proyek, lalu melewati gate.
 *
 * Dua hal yang paling menentukan apakah modul ini berguna: proyek hanya lahir
 * dari kelayakan yang benar-benar dinilai, dan gate tidak bisa dilewati selama
 * deliverable wajibnya belum ada. Tanpa keduanya, ini cuma daftar proyek.
 */
function rfqFor(array $attrs = []): npd_rfq
{
    $cus = DB::table('m_contacts')->where('category_id', 3)->value('id')
        ?: DB::table('m_contacts')->value('id');

    return npd_rfq::create(array_merge([
        'cus_id' => $cus,
        'code' => 'RFQ-UJI-'.substr(uniqid(), -6),
        'date' => now()->toDateString(),
        'part_name' => 'Part uji NPD',
        'drawing_ref' => 'DWG-UJI-01',
        'qty' => 5000,
        'target_price' => 45000,
        'status' => 'OPEN',
        'user_id' => admin()->id,
    ], $attrs));
}

/** Studi kelayakan dengan kesimpulan tertentu. */
function feasFor(npd_rfq $rfq, string $conclusion = 'GO'): void
{
    DB::table('npd_feasibility')->insert([
        'rfq_id' => $rfq->id,
        'tech_ok' => 1, 'capacity_ok' => 1, 'cost_ok' => 1,
        'conclusion' => $conclusion,
        'user_id' => admin()->id,
        'evaluated_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('membuat lima fase beserta deliverable wajibnya saat proyek lahir', function () {
    $rfq = rfqFor();
    feasFor($rfq);

    $project = app(NpdService::class)->createFromRfq($rfq, ['name' => 'Proyek uji'], admin()->id);

    expect($project->phases()->count())->toBe(5)
        // Fase pertama langsung berjalan; sisanya menunggu gilirannya.
        ->and($project->phases()->where('phase_no', 1)->value('status'))->toBe('RUNNING')
        ->and($project->phases()->where('phase_no', 2)->value('status'))->toBe('PLANNED')
        ->and($project->current_phase_no)->toBe(1);

    $deliverables = DB::table('npd_deliverable as d')
        ->join('npd_project_phase as p', 'p.id', '=', 'd.main_id')
        ->where('p.main_id', $project->id)->count();

    expect($deliverables)->toBeGreaterThan(0);

    // RFQ-nya ikut tertaut, jadi satu RFQ tidak bisa jadi dua proyek.
    expect($rfq->fresh()->main_id)->toBe($project->id);
});

it('menolak membuat proyek dari RFQ yang belum dinyatakan layak', function () {
    $rfq = rfqFor();

    expect(fn () => app(NpdService::class)->createFromRfq($rfq, [], admin()->id))
        ->toThrow(BizException::class);

    feasFor($rfq, 'CONDITIONAL');

    // "Layak dengan syarat" berarti syaratnya belum dipenuhi.
    expect(fn () => app(NpdService::class)->createFromRfq($rfq->fresh(), [], admin()->id))
        ->toThrow(BizException::class);
});

it('menolak RFQ yang sama dijadikan proyek dua kali', function () {
    $rfq = rfqFor();
    feasFor($rfq);
    app(NpdService::class)->createFromRfq($rfq, [], admin()->id);

    expect(fn () => app(NpdService::class)->createFromRfq($rfq->fresh(), [], admin()->id))
        ->toThrow(BizException::class);
});

it('menahan gate selama deliverable wajib belum beres', function () {
    $rfq = rfqFor();
    feasFor($rfq);
    $project = app(NpdService::class)->createFromRfq($rfq, [], admin()->id);
    $phase = $project->phases()->where('phase_no', 1)->first();

    // Ini inti modulnya: "sudah lewat gate" tidak boleh berarti apa-apa kalau
    // PFMEA-nya ternyata belum ada.
    expect(fn () => app(NpdService::class)->submitGate($phase))->toThrow(BizException::class);

    $phase->deliverables()->update(['status' => 'DONE']);
    app(NpdService::class)->submitGate($phase->fresh());

    expect($phase->fresh()->status)->toBe('SUBMITTED');
});

it('menerima deliverable yang dikecualikan sebagai penutup gate', function () {
    $rfq = rfqFor();
    feasFor($rfq);
    $project = app(NpdService::class)->createFromRfq($rfq, [], admin()->id);
    $phase = $project->phases()->where('phase_no', 1)->first();

    // Dikecualikan dengan alasan tetap sah — yang dilarang adalah diabaikan.
    $phase->deliverables()->update(['status' => 'WAIVED', 'note' => 'Tidak berlaku untuk part derivatif']);

    app(NpdService::class)->submitGate($phase->fresh());

    expect($phase->fresh()->status)->toBe('SUBMITTED');
});

it('membuka fase berikutnya hanya setelah dua level menyetujui', function () {
    Sanctum::actingAs(admin());
    $rfq = rfqFor();
    feasFor($rfq);
    $project = app(NpdService::class)->createFromRfq($rfq, [], admin()->id);
    $phase = $project->phases()->where('phase_no', 1)->first();
    $phase->deliverables()->update(['status' => 'DONE']);

    $this->postJson("/api/v1/npd-projects/{$project->id}/gate/1/submit")->assertOk();

    // Level pertama saja belum cukup: proyek masih di fase 1.
    $this->postJson("/api/v1/npd-projects/{$project->id}/gate/1/approve")->assertOk();
    expect($project->fresh()->current_phase_no)->toBe(1);

    $this->postJson("/api/v1/npd-projects/{$project->id}/gate/1/approve")->assertOk();

    expect($project->fresh()->current_phase_no)->toBe(2)
        ->and($project->phases()->where('phase_no', 1)->value('status'))->toBe('APPROVED')
        ->and($project->phases()->where('phase_no', 2)->value('status'))->toBe('RUNNING');
});

it('menandai proyek siap serah terima setelah gate terakhir', function () {
    Sanctum::actingAs(admin());
    $rfq = rfqFor();
    feasFor($rfq);
    $project = app(NpdService::class)->createFromRfq($rfq, [], admin()->id);

    for ($no = 1; $no <= 5; $no++) {
        $phase = $project->phases()->where('phase_no', $no)->first();
        $phase->deliverables()->update(['status' => 'DONE']);
        $this->postJson("/api/v1/npd-projects/{$project->id}/gate/{$no}/submit")->assertOk();
        $this->postJson("/api/v1/npd-projects/{$project->id}/gate/{$no}/approve")->assertOk();
        $this->postJson("/api/v1/npd-projects/{$project->id}/gate/{$no}/approve")->assertOk();
    }

    // Bukan CLOSED: serah terimanya sendiri belum terjadi.
    expect($project->fresh()->status)->toBe('HANDOVER');
});

it('menolak kesimpulan GO selama masih ada aspek yang belum sanggup', function () {
    Sanctum::actingAs(admin());
    $rfq = rfqFor();

    $this->postJson("/api/v1/npd-rfq/{$rfq->id}/feasibility", [
        'tech_ok' => true, 'capacity_ok' => false, 'cost_ok' => true,
        'conclusion' => 'GO',
    ])->assertStatus(422);

    // CONDITIONAL boleh — itu memang gunanya.
    $this->postJson("/api/v1/npd-rfq/{$rfq->id}/feasibility", [
        'tech_ok' => true, 'capacity_ok' => false, 'cost_ok' => true,
        'conclusion' => 'CONDITIONAL', 'note' => 'Menunggu mesin bending baru',
    ])->assertOk();
});

it('mengunci isi fase yang gate-nya sudah diajukan', function () {
    Sanctum::actingAs(admin());
    $rfq = rfqFor();
    feasFor($rfq);
    $project = app(NpdService::class)->createFromRfq($rfq, [], admin()->id);
    $phase = $project->phases()->where('phase_no', 1)->first();
    $phase->deliverables()->update(['status' => 'DONE']);

    $del = $phase->deliverables()->first();
    $this->postJson("/api/v1/npd-projects/{$project->id}/gate/1/submit")->assertOk();

    // 423 Locked: mengubah isi fase setelah diajukan berarti approver menilai
    // sesuatu yang sudah berbeda dari yang dilihatnya.
    $this->putJson("/api/v1/npd-deliverables/{$del->id}", ['status' => 'OPEN'])->assertStatus(423);
});

it('mewajibkan alasan saat deliverable dikecualikan', function () {
    Sanctum::actingAs(admin());
    $rfq = rfqFor();
    feasFor($rfq);
    $project = app(NpdService::class)->createFromRfq($rfq, [], admin()->id);
    $del = $project->phases()->where('phase_no', 1)->first()->deliverables()->first();

    $this->putJson("/api/v1/npd-deliverables/{$del->id}", ['status' => 'WAIVED'])->assertStatus(422);

    $this->putJson("/api/v1/npd-deliverables/{$del->id}", [
        'status' => 'WAIVED', 'note' => 'Part derivatif, DFMEA induk masih berlaku',
    ])->assertOk();
});

it('menolak task ditandai selesai sebelum pendahulunya selesai', function () {
    Sanctum::actingAs(admin());
    $rfq = rfqFor();
    feasFor($rfq);
    $project = app(NpdService::class)->createFromRfq($rfq, [], admin()->id);
    $phase = $project->phases()->where('phase_no', 1)->first();

    $first = $phase->tasks()->create(['name' => 'Kumpulkan drawing', 'status' => 'OPEN']);
    $second = $phase->tasks()->create(['name' => 'Review drawing', 'status' => 'OPEN', 'predecessor_id' => $first->id]);

    $this->putJson("/api/v1/npd-tasks/{$second->id}", ['name' => $second->name, 'status' => 'DONE'])
        ->assertStatus(422);

    $this->putJson("/api/v1/npd-tasks/{$first->id}", ['name' => $first->name, 'status' => 'DONE'])->assertOk();
    $this->putJson("/api/v1/npd-tasks/{$second->id}", ['name' => $second->name, 'status' => 'DONE'])->assertOk();

    // Yang selesai itu seratus persen — status dan progres dijaga sejalan.
    expect((int) $second->fresh()->progress_pct)->toBe(100);
});

it('menyajikan daftar, detail, dashboard, dan task saya lewat HTTP', function () {
    Sanctum::actingAs(admin());

    $this->getJson('/api/v1/npd-projects')->assertOk();
    $this->getJson('/api/v1/npd-rfq')->assertOk();
    $this->getJson('/api/v1/npd/my-tasks')->assertOk();
    $this->getJson('/api/v1/npd/dashboard')->assertOk()
        ->assertJsonStructure(['data' => ['summary' => ['funnel', 'pending_gates', 'late_tasks'], 'lead_times']]);

    $id = DB::table('npd_project')->value('id');
    if ($id) {
        $this->getJson("/api/v1/npd-projects/{$id}")->assertOk()
            ->assertJsonStructure(['data' => ['code', 'phases', 'milestones', 'members']]);
    }
});
