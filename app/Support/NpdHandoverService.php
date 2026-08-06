<?php

namespace App\Support;

use App\Exceptions\BizException;
use App\Models\npd_bom_main;
use App\Models\npd_cp_main;
use App\Models\npd_project;
use Illuminate\Support\Facades\DB;

/**
 * Serah terima proyek NPD ke produksi.
 *
 * Inilah satu-satunya alasan seluruh modul ini ada. Sebelum ini, keluaran
 * proyek pengembangan berhenti sebagai lampiran: BOM di Excel, urutan proses di
 * papan tulis, toleransi di kepala process engineer. Yang dikerjakan di sini
 * adalah memindahkan semuanya menjadi master yang benar-benar dipakai pabrik —
 * dalam satu transaksi, sehingga tidak pernah ada keadaan "BOM sudah jadi tapi
 * routing belum".
 *
 * Empat hal yang dibuat:
 *
 *   m_bom + detailnya          dari preliminary BOM proyek
 *   m_process_main + detailnya dari urutan proses pada control plan
 *   m_route_time               dari cycle time yang diukur saat trial
 *   m_item_inspection          dari baris control plan yang ditandai "→ QC"
 *
 * Lalu part-nya dinaikkan dari TRIAL ke MASSPRO — dan sejak itulah ia diterima
 * rencana bulanan, forecast, sales order, dan Work Order produksi.
 *
 * Semua master lahir berstatus DRAFT dan tetap melewati approval engineering
 * yang sudah berlaku. NPD tidak boleh menembus kontrol yang sudah ada.
 *
 * PRD_Modul_NPD_FTPI.md §7.8
 */
class NpdHandoverService
{
    public function __construct(private NpdPpapService $ppap) {}

    /**
     * Apa yang akan terjadi bila serah terima dijalankan, dan apa yang menahannya.
     *
     * Dipakai layar SPV supaya keputusannya diambil sambil melihat isinya, bukan
     * setelah menekan tombol.
     *
     * @return array<string, mixed>
     */
    public function preview(npd_project $project): array
    {
        $blockers = $this->blockers($project);
        $bom = $this->latestBom($project);
        $cp = $this->finalControlPlan($project);

        $routing = $cp ? $this->routingSteps($cp) : collect();

        return [
            'project' => $project->only(['id', 'code', 'name', 'part_name', 'status', 'item_id', 'bom_id', 'process_main_id']),
            'blockers' => $blockers,
            'can_handover' => empty($blockers),
            'will_create' => [
                'bom' => $bom ? [
                    'version' => $bom->version,
                    'rm_lines' => $bom->detail->where('role', 'RM')->count(),
                    'pm_lines' => $bom->detail->where('role', 'PM')->count(),
                    'exists_already' => (bool) $project->bom_id,
                ] : null,
                'routing' => $cp ? [
                    'source' => "Control plan {$cp->code}",
                    'steps' => $routing->values(),
                    'exists_already' => (bool) $project->process_main_id,
                ] : null,
                'inspection_params' => $cp
                    ? $cp->detail->where('to_item_inspection', true)->whereNotNull('param_id')
                        ->map(fn ($d) => [
                            'param_id' => $d->param_id,
                            'param' => $d->param?->code.' — '.$d->param?->name,
                            'nominal' => $d->nominal,
                            'min_value' => $d->min_value,
                            'max_value' => $d->max_value,
                        ])->values()
                    : collect(),
            ],
        ];
    }

    /**
     * Jalankan serah terima.
     *
     * @param  array{cycle_times?: array<int, array{proc_id:int, cycle_sec:float, machine_id?:int, setup_min?:float}>}  $data
     */
    public function execute(npd_project $project, array $data, int $userId): array
    {
        $blockers = $this->blockers($project);

        if ($blockers) {
            throw BizException::make(
                'NPD_HANDOVER_BLOCKED',
                'Serah terima ditolak: '.implode('; ', $blockers).'.'
            );
        }

        $bom = $this->latestBom($project);
        $cp = $this->finalControlPlan($project);

        return DB::transaction(function () use ($project, $bom, $cp, $data) {
            $created = [
                'bom_id' => $project->bom_id,
                'process_main_id' => $project->process_main_id,
                'rm_lines' => 0, 'pm_lines' => 0,
                'routing_steps' => 0, 'route_times' => 0, 'inspection_params' => 0,
            ];

            if (! $project->bom_id && $bom) {
                [$created['bom_id'], $created['rm_lines'], $created['pm_lines']] = $this->copyBom($project, $bom);
            }

            if (! $project->process_main_id && $cp) {
                [$created['process_main_id'], $created['routing_steps']] = $this->copyRouting($project, $cp);
            }

            $created['route_times'] = $this->copyCycleTimes($project, $data['cycle_times'] ?? []);
            $created['inspection_params'] = $cp ? $this->copyInspectionParams($project, $cp) : 0;

            // Terakhir, dan hanya kalau semua di atas berhasil: part diterima produksi.
            ItemLifecycle::graduate((int) $project->item_id);

            $project->update([
                'bom_id' => $created['bom_id'],
                'process_main_id' => $created['process_main_id'],
                'handover_date' => now()->toDateString(),
                'status' => 'CLOSED',
            ]);

            AuditLogger::record(
                request(),
                "Serah terima NPD {$project->code}: BOM #{$created['bom_id']}, routing #{$created['process_main_id']}, "
                ."{$created['inspection_params']} parameter QC; part naik ke produksi massal",
                $project->code
            );

            return $created + ['project' => $project->fresh()->load('item')];
        });
    }

    /**
     * Alasan-alasan yang menahan serah terima, seluruhnya sekaligus.
     *
     * Disajikan sebagai daftar, bukan satu per satu: SPV yang harus memperbaiki
     * empat hal lebih baik mengetahui keempatnya sekarang daripada menemukannya
     * berurutan dalam empat percobaan.
     *
     * @return array<int, string>
     */
    public function blockers(npd_project $project): array
    {
        $out = [];

        if ($project->status !== 'HANDOVER') {
            $out[] = 'proyek belum lolos gate terakhir';
        }

        if (! $project->item_id) {
            $out[] = 'part belum didaftarkan ke master item';
        } elseif (! ItemLifecycle::isTrial((int) $project->item_id)) {
            $out[] = 'part sudah berstatus produksi massal';
        }

        if (! $this->ppap->hasApproved($project->id)) {
            $out[] = 'belum ada PPAP yang disetujui pelanggan';
        }

        $bom = $this->latestBom($project);

        if (! $bom) {
            $out[] = 'proyek belum punya preliminary BOM';
        } else {
            /*
             * Baris BOM yang masih menunjuk "part baru" tidak bisa disalin ke
             * BOM produksi: materialnya belum ada nomornya, jadi tidak bisa
             * dibeli, tidak bisa dijadwalkan, dan tidak bisa dikeluarkan gudang.
             */
            $unresolved = $bom->detail->whereNull('item_id');

            if ($unresolved->isNotEmpty()) {
                $out[] = 'BOM masih memuat part yang belum terdaftar di master item ('
                    .$unresolved->map(fn ($d) => $d->new_item_name ?: 'tanpa nama')->implode(', ').')';
            }
        }

        if (! $this->finalControlPlan($project)) {
            $out[] = 'belum ada control plan berstatus final';
        }

        return $out;
    }

    /* ---------------- penyalinan ---------------- */

    /** @return array{0:int, 1:int, 2:int} [bom_id, jumlah RM, jumlah PM] */
    private function copyBom(npd_project $project, npd_bom_main $bom): array
    {
        // Item bisa saja sudah punya BOM (proyek modifikasi); yang ada dipakai
        // ulang agar tidak lahir dua BOM aktif untuk satu part.
        $bomId = DB::table('m_bom')->where('item_id', $project->item_id)->value('id');

        if (! $bomId) {
            $bomId = DB::table('m_bom')->insertGetId([
                'item_id' => $project->item_id,
                'active' => 1,
                'status' => 'DRAFT',
                'rev' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rm = 0;
        $pm = 0;

        foreach ($bom->detail as $line) {
            if ($line->role === 'PM') {
                DB::table('m_bom_det_pm')->insert([
                    'id_prim' => $bomId,
                    'pm_id' => $line->item_id,
                    'qty' => (int) max(1, $line->qty),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $pm++;

                continue;
            }

            DB::table('m_bom_det_rm')->insert([
                'id_prim' => $bomId,
                'mat_id' => $line->item_id,
                'length_cut' => $line->length_use ?? 0,
                'length_use' => $line->length_use ?? 0,
                'priority' => ++$rm,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return [$bomId, $rm, $pm];
    }

    /**
     * Routing dari urutan proses pada control plan.
     *
     * Control plan APQP memang disusun per langkah proses, jadi urutannya adalah
     * process flow yang sudah disepakati — tidak perlu diketik ulang di tempat
     * lain hanya untuk dipindahkan ke sini.
     *
     * @return array{0:int, 1:int} [process_main_id, jumlah langkah]
     */
    private function copyRouting(npd_project $project, npd_cp_main $cp): array
    {
        $steps = $this->routingSteps($cp);

        if ($steps->isEmpty()) {
            return [null, 0];
        }

        $routingId = DB::table('m_process_main')->insertGetId([
            'code' => app(NumberingService::class)->next('ROUTING', 'WOS'),
            'name' => 'Routing '.$project->part_name,
            'active' => 1,
            'status' => 'DRAFT',
            'rev' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($steps as $i => $step) {
            DB::table('m_process_main_det')->insert([
                'main_id' => $routingId,
                'proc_id' => $step['proc_id'],
                'sequence' => $i + 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Item boleh punya beberapa routing berprioritas; yang baru jadi pilihan
        // pertama karena inilah yang divalidasi trial.
        DB::table('m_bom_pro')->insert([
            'item_id' => $project->item_id,
            'process_main_id' => $routingId,
            'priority' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$routingId, $steps->count()];
    }

    /**
     * Cycle time hasil pengukuran saat trial.
     *
     * Diisi process engineer pada layar serah terima, bukan dihitung sistem:
     * MES mencatat kapan pallet mulai dan selesai, tetapi angka yang dipakai
     * perencanaan adalah cycle time yang sudah dinilai wajar oleh manusia —
     * bukan rata-rata mentah yang termasuk gangguan dan penyetelan.
     */
    private function copyCycleTimes(npd_project $project, array $cycleTimes): int
    {
        $n = 0;

        foreach ($cycleTimes as $ct) {
            if (empty($ct['proc_id']) || ! ($ct['cycle_sec'] ?? 0) > 0) {
                continue;
            }

            DB::table('m_route_time')->updateOrInsert(
                [
                    'item_id' => $project->item_id,
                    'proc_id' => $ct['proc_id'],
                    'machine_id' => $ct['machine_id'] ?? null,
                ],
                [
                    'cycle_sec' => $ct['cycle_sec'],
                    'setup_min' => $ct['setup_min'] ?? 0,
                    'priority' => 1,
                    'active' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]
            );
            $n++;
        }

        return $n;
    }

    /**
     * Parameter inspeksi produksi dari control plan.
     *
     * Hanya baris yang ditandai "→ QC" dan punya batas ukur — itulah yang sudah
     * dijamin lengkap saat control plan difinalkan. Baris yang mengendalikan
     * setelan mesin tidak ikut, karena QC tidak mengukur setelan.
     */
    private function copyInspectionParams(npd_project $project, npd_cp_main $cp): int
    {
        $n = 0;

        foreach ($cp->detail->where('to_item_inspection', true) as $line) {
            if (! $line->param_id) {
                continue;
            }

            DB::table('m_item_inspection')->updateOrInsert(
                ['item_id' => $project->item_id, 'param_id' => $line->param_id],
                [
                    'nominal' => $line->nominal ?? 0,
                    'min_value' => $line->min_value ?? 0,
                    'max_value' => $line->max_value ?? 0,
                    'mandatory' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]
            );
            $n++;
        }

        return $n;
    }

    /* ---------------- pembantu ---------------- */

    private function latestBom(npd_project $project): ?npd_bom_main
    {
        return npd_bom_main::with('detail')
            ->where('main_id', $project->id)
            ->orderByRaw("FIELD(status, 'APPROVED', 'DRAFT', 'SUPERSEDED')")
            ->orderByDesc('id')
            ->first();
    }

    private function finalControlPlan(npd_project $project): ?npd_cp_main
    {
        return npd_cp_main::with('detail.param', 'detail.proc')
            ->where('main_id', $project->id)
            ->where('status', 'FINAL')
            ->orderByDesc('id')
            ->first();
    }

    /** Urutan proses unik pada control plan, sesuai nomor barisnya. */
    private function routingSteps(npd_cp_main $cp)
    {
        return $cp->detail
            ->filter(fn ($d) => $d->proc_id)
            ->sortBy('seq')
            ->unique('proc_id')
            ->map(fn ($d) => ['proc_id' => (int) $d->proc_id, 'name' => $d->proc?->name_p])
            ->values();
    }
}
