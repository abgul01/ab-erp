<?php

namespace App\Support;

use App\Exceptions\BizException;
use App\Models\npd_cp_main;
use App\Models\npd_fmea_main;
use Illuminate\Support\Facades\DB;

/**
 * FMEA dan Control Plan — sepasang, bukan dua daftar terpisah.
 *
 * FMEA menjawab apa yang bisa salah dan seberapa besar akibatnya; control plan
 * menjawab apa yang karena itu diukur di lantai produksi. Control plan yang
 * tidak lahir dari FMEA hanyalah daftar pengukuran yang kebetulan terpikirkan,
 * dan biasanya melewatkan justru kegagalan yang paling mahal.
 *
 * Dua aturan yang ditegakkan di sini:
 *
 *   RPN dihitung, tidak diketik. Angka yang bisa diketik akan diketik lebih
 *   rendah begitu ada yang ingin melewatkan tindakan perbaikan.
 *
 *   Risiko di atas ambang wajib punya tindakan. FMEA yang mencatat RPN 320
 *   tanpa satu pun tindakan bukan analisis risiko — itu daftar hal yang sudah
 *   diketahui akan gagal.
 *
 * PRD_Modul_NPD_FTPI.md §7.5 FR-16, FR-17
 */
class NpdQualityService
{
    /** Severity × Occurrence × Detection. Satu-satunya sumber nilai RPN. */
    public function rpn(int $severity, int $occurrence, int $detection): int
    {
        return $severity * $occurrence * $detection;
    }

    /**
     * Tulis ulang baris FMEA.
     *
     * RPN dihitung dari ketiga angka, lalu baris yang melewati ambang diperiksa:
     * harus ada tindakan yang direkomendasikan. Yang ditolak disebutkan mode
     * kegagalannya, bukan nomor barisnya — orang mengingat "kebocoran pada
     * sambungan las", bukan "baris ke-7".
     */
    public function saveFmeaLines(npd_fmea_main $fmea, array $rows): npd_fmea_main
    {
        $this->assertDraft($fmea->status, 'FMEA');

        $missing = [];

        foreach ($rows as $r) {
            $rpn = $this->rpn((int) $r['severity'], (int) $r['occurrence'], (int) $r['detection']);

            if ($rpn >= $fmea->rpn_threshold && blank($r['recommended_action'] ?? null)) {
                $missing[] = "{$r['failure_mode']} (RPN {$rpn})";
            }
        }

        if ($missing) {
            throw BizException::make(
                'NPD_FMEA_ACTION',
                "Risiko dengan RPN ≥ {$fmea->rpn_threshold} wajib punya tindakan perbaikan: "
                .implode('; ', $missing).'.'
            );
        }

        return DB::transaction(function () use ($fmea, $rows) {
            $fmea->detail()->delete();

            foreach ($rows as $r) {
                $fmea->detail()->create([
                    'proc_id' => $r['proc_id'] ?? null,
                    'item_function' => $r['item_function'],
                    'failure_mode' => $r['failure_mode'],
                    'effect' => $r['effect'],
                    'severity' => $r['severity'],
                    'cause' => $r['cause'],
                    'occurrence' => $r['occurrence'],
                    'current_control' => $r['current_control'] ?? null,
                    'detection' => $r['detection'],
                    'rpn' => $this->rpn((int) $r['severity'], (int) $r['occurrence'], (int) $r['detection']),
                    'recommended_action' => $r['recommended_action'] ?? null,
                    'action_taken' => $r['action_taken'] ?? null,
                    'resp_user_id' => $r['resp_user_id'] ?? null,
                    'due_date' => $r['due_date'] ?? null,
                    'status' => $r['status'] ?? 'OPEN',
                ]);
            }

            return $fmea->fresh()->load('detail.proc', 'detail.responsible');
        });
    }

    /**
     * Kunci FMEA.
     *
     * Ditolak selama masih ada tindakan yang belum dikerjakan pada risiko di
     * atas ambang — FMEA final yang menyisakan tindakan terbuka menjanjikan
     * sesuatu yang belum terjadi kepada pelanggan yang membacanya.
     */
    public function finalizeFmea(npd_fmea_main $fmea): npd_fmea_main
    {
        $this->assertDraft($fmea->status, 'FMEA');
        $fmea->load('detail');

        if ($fmea->detail->isEmpty()) {
            throw BizException::make('NPD_FMEA_EMPTY', 'FMEA tanpa satu pun baris risiko tidak dapat difinalkan.');
        }

        $open = $fmea->aboveThreshold()->where('status', '<>', 'DONE');

        if ($open->isNotEmpty()) {
            throw BizException::make(
                'NPD_FMEA_OPEN',
                'Masih ada tindakan yang belum selesai pada risiko di atas ambang: '
                .$open->pluck('failure_mode')->implode('; ').'.'
            );
        }

        $fmea->update(['status' => 'FINAL']);
        AuditLogger::record(request(), "Finalkan {$fmea->fmea_type} FMEA {$fmea->code}", $fmea->code);

        return $fmea->fresh()->load('detail');
    }

    /**
     * Susun control plan dari PFMEA: satu baris kendali per risiko di atas ambang.
     *
     * Inilah alur APQP yang sesungguhnya, dan alasan kedua dokumen ini ada di
     * satu modul. Baris yang dihasilkan sengaja belum lengkap — frekuensi,
     * ukuran sampel, dan reaction plan tetap keputusan process engineer. Yang
     * dijamin di sini hanyalah bahwa tidak ada risiko besar yang lolos tanpa
     * satu pun pengukuran.
     *
     * Parameter inspeksi dicocokkan dari kendali yang sudah tertulis di FMEA
     * bila kodenya dikenali; kalau tidak, dibiarkan kosong untuk diisi.
     */
    public function generateCpFromFmea(npd_cp_main $cp, npd_fmea_main $fmea, bool $replace = false): npd_cp_main
    {
        $this->assertDraft($cp->status, 'Control plan');

        if ($fmea->fmea_type !== 'PROCESS') {
            throw BizException::make(
                'NPD_CP_SOURCE',
                'Control plan disusun dari PFMEA (risiko proses), bukan dari DFMEA.'
            );
        }

        if ($fmea->main_id !== $cp->main_id) {
            throw BizException::make('NPD_CP_PROJECT', 'FMEA dan control plan ini bukan milik proyek yang sama.');
        }

        $fmea->load('detail');
        $risks = $fmea->aboveThreshold();

        if ($risks->isEmpty()) {
            throw BizException::make(
                'NPD_CP_NO_RISK',
                "Tidak ada risiko dengan RPN ≥ {$fmea->rpn_threshold} pada PFMEA ini."
            );
        }

        return DB::transaction(function () use ($cp, $risks, $replace) {
            if ($replace) {
                $cp->detail()->whereNotNull('fmea_det_id')->delete();
            }

            // Yang sudah ada tidak digandakan: menjalankan ulang penyusunan
            // setelah PFMEA ditambah barisnya harus menambah, bukan menduplikasi.
            $existing = $cp->detail()->pluck('fmea_det_id')->filter()->all();
            $seq = (int) $cp->detail()->max('seq');

            foreach ($risks as $risk) {
                if (in_array($risk->id, $existing, true)) {
                    continue;
                }

                // Reaction plan awal yang aman: hentikan dan karantina. Kalau
                // ada penanggung jawab risikonya, dialah yang dilapori.
                $reaction = 'Hentikan proses dan karantina barang sejak pemeriksaan terakhir.';
                if ($risk->responsible) {
                    $reaction .= " Laporkan ke {$risk->responsible->name}.";
                }

                $cp->detail()->create([
                    'seq' => ++$seq,
                    'proc_id' => $risk->proc_id,
                    'param_id' => null,
                    'method' => $risk->current_control,
                    'sample_size' => 1,
                    'control_method' => $risk->recommended_action,
                    'reaction_plan' => $reaction,
                    'fmea_det_id' => $risk->id,
                    'to_item_inspection' => true,
                ]);
            }

            AuditLogger::record(request(), "Susun control plan {$cp->code} dari PFMEA", $cp->code);

            return $cp->fresh()->load('detail.proc', 'detail.param');
        });
    }

    /**
     * Kunci control plan.
     *
     * Baris yang ditandai akan menjadi parameter inspeksi item harus lengkap
     * spesifikasinya: parameter dan batasnya. Baris tanpa itu tidak bisa
     * dipakai QC produksi untuk memutuskan apa pun.
     */
    public function finalizeCp(npd_cp_main $cp): npd_cp_main
    {
        $this->assertDraft($cp->status, 'Control plan');
        $cp->load('detail.param');

        if ($cp->detail->isEmpty()) {
            throw BizException::make('NPD_CP_EMPTY', 'Control plan tanpa satu pun baris tidak dapat difinalkan.');
        }

        $incomplete = $cp->detail
            ->where('to_item_inspection', true)
            ->filter(fn ($d) => ! $d->param_id || ($d->min_value === null && $d->max_value === null));

        if ($incomplete->isNotEmpty()) {
            throw BizException::make(
                'NPD_CP_INCOMPLETE',
                'Baris yang akan menjadi parameter inspeksi produksi harus punya parameter dan batas ukur. '
                .'Lengkapi baris urutan: '.$incomplete->pluck('seq')->implode(', ')
                .' — atau hapus tandanya bila memang hanya kendali setelan mesin.'
            );
        }

        $cp->update(['status' => 'FINAL']);
        AuditLogger::record(request(), "Finalkan control plan {$cp->code}", $cp->code);

        return $cp->fresh()->load('detail.proc', 'detail.param');
    }

    private function assertDraft(string $status, string $label): void
    {
        if ($status !== 'DRAFT') {
            throw BizException::make(
                'NPD_QUALITY_LOCKED',
                "{$label} yang sudah final tidak dapat diubah. Buat revisi baru."
            );
        }
    }
}
