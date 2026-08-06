<?php

namespace App\Support;

use App\Exceptions\BizException;
use App\Models\npd_project;
use App\Models\npd_trial_main;
use App\Models\prd_wo_main;
use Illuminate\Support\Facades\DB;

/**
 * Trial produksi: Work Order-nya, dan hasil ukur yang keluar darinya.
 *
 * Dua hal yang dijaga di sini.
 *
 * Setiap trial punya Work Order sendiri, dibuat dari sini dan ditandai
 * `NPD_TRIAL`. Lewat WO itulah pemakaian material dan jam mesin tercatat di
 * jalur yang sama dengan produksi biasa — tetapi keluarannya tidak dihitung MRP
 * sebagai pasokan, karena barangnya dibuat untuk diukur dan dibongkar, bukan
 * untuk memenuhi pesanan.
 *
 * Judgement OK/NG dihitung dari batas spesifikasi, tidak pernah diketik. Hasil
 * ukur yang lulus-tidaknya ditentukan oleh orang yang memasukkan angkanya bukan
 * hasil pengukuran, melainkan pendapat.
 *
 * PRD_Modul_NPD_FTPI.md §7.5
 */
class NpdTrialService
{
    /**
     * Buat trial beserta Work Order-nya dalam satu transaksi.
     *
     * Part harus sudah terdaftar di master item: Work Order menunjuk `fg_id`,
     * dan tidak ada cara membuat WO untuk sesuatu yang belum ada nomornya.
     */
    public function create(npd_project $project, array $data, int $userId): npd_trial_main
    {
        if (! $project->item_id) {
            throw BizException::make(
                'NPD_TRIAL_NO_PART',
                'Part proyek ini belum terdaftar di master item — daftarkan dulu di layar BOM & Costing sebelum membuat trial.'
            );
        }

        return DB::transaction(function () use ($project, $data, $userId) {
            $wo = prd_wo_main::create([
                'code' => app(NumberingService::class)->next('WOTRIAL', 'WOT'),
                'wo_kind' => 'NPD_TRIAL',
                'npd_project_id' => $project->id,
                'date' => $data['date'],
                'customer_id' => $project->cus_id,
                'so_id' => '-',
                'fg_id' => $project->item_id,
                'process_main_id' => $data['process_main_id'] ?? null,
                // Trial tidak berasal dari jadwal produksi; ia justru yang
                // menentukan apakah jadwal itu kelak bisa dibuat.
                'mps_id' => 0,
                'user_id' => $userId,
                'qty' => $data['planned_qty'],
                'status' => 1,          // DRAFT
                'no_cut' => 0,
                'for_pm' => 0,
            ]);

            $trial = npd_trial_main::create([
                'main_id' => $project->id,
                'phase_id' => $data['phase_id'] ?? $project->phases()->where('phase_no', 4)->value('id'),
                'wo_id' => $wo->id,
                'code' => app(NumberingService::class)->next('TRIAL', 'TRL'),
                'trial_type' => $data['trial_type'] ?? 'PROTOTYPE',
                'date' => $data['date'],
                'machine_id' => $data['machine_id'] ?? null,
                'planned_qty' => $data['planned_qty'],
                'status' => 'DRAFT',
                'user_id' => $userId,
            ]);

            AuditLogger::record(
                request(),
                "Buat trial {$trial->code} proyek {$project->code} (WO {$wo->code})",
                $trial->code
            );

            return $trial;
        });
    }

    /**
     * Spesifikasi yang berlaku untuk sebuah parameter pada part proyek ini.
     *
     * Diambil dari `m_item_inspection` bila part-nya sudah punya — itulah angka
     * yang nanti dipakai QC produksi, jadi trial harus diukur terhadap angka
     * yang sama. Kalau belum ada, penguji mengisinya sendiri, dan angka itu
     * dibekukan di baris hasil.
     *
     * @return array{nominal: ?float, min_value: ?float, max_value: ?float, source: string}
     */
    public function specFor(int $itemId, int $paramId): array
    {
        $spec = DB::table('m_item_inspection')
            ->where('item_id', $itemId)
            ->where('param_id', $paramId)
            ->first(['nominal', 'min_value', 'max_value']);

        if (! $spec) {
            return ['nominal' => null, 'min_value' => null, 'max_value' => null, 'source' => 'MANUAL'];
        }

        return [
            'nominal' => (float) $spec->nominal,
            'min_value' => (float) $spec->min_value,
            'max_value' => (float) $spec->max_value,
            'source' => 'MASTER',
        ];
    }

    /**
     * Simpan hasil ukur.
     *
     * Baris lama dibuang lalu ditulis ulang: satu trial dicatat sekali duduk
     * setelah pengukuran selesai, dan penggabungan sebagian hanya akan
     * menyisakan baris yatim dari percobaan sebelumnya.
     */
    public function saveResults(npd_trial_main $trial, array $rows, int $userId): npd_trial_main
    {
        $this->assertDraft($trial);

        $project = npd_project::findOrFail($trial->main_id);

        return DB::transaction(function () use ($trial, $rows, $project, $userId) {
            $trial->detail()->delete();

            foreach ($rows as $r) {
                $spec = $this->specFor((int) $project->item_id, (int) $r['param_id']);

                // Spesifikasi master menang atas yang diketik: kalau part-nya
                // sudah punya batas resmi, trial tidak boleh diukur terhadap
                // batas lain yang lebih longgar.
                $nominal = $spec['source'] === 'MASTER' ? $spec['nominal'] : ($r['nominal'] ?? null);
                $min = $spec['source'] === 'MASTER' ? $spec['min_value'] : ($r['min_value'] ?? null);
                $max = $spec['source'] === 'MASTER' ? $spec['max_value'] : ($r['max_value'] ?? null);

                $trial->detail()->create([
                    'param_id' => $r['param_id'],
                    'sample_no' => $r['sample_no'] ?? 1,
                    'nominal' => $nominal,
                    'min_value' => $min,
                    'max_value' => $max,
                    'measured' => $r['measured'],
                    'judgement' => $this->judge((float) $r['measured'], $min, $max),
                    'instrument' => $r['instrument'] ?? null,
                    'inspector_id' => $r['inspector_id'] ?? $userId,
                    'note' => $r['note'] ?? null,
                ]);
            }

            return $trial->fresh()->load('detail.param');
        });
    }

    /**
     * Lulus atau tidak, dari batas spesifikasinya.
     *
     * Tanpa batas atas maupun bawah, hasilnya dianggap OK — parameter yang
     * dicatat sebagai keterangan (misalnya warna atau nomor cetakan) memang
     * tidak punya batas, dan menandainya NG hanya akan membuat angka kelulusan
     * salah.
     */
    private function judge(float $measured, ?float $min, ?float $max): string
    {
        if ($min !== null && $measured < $min) {
            return 'NG';
        }

        if ($max !== null && $measured > $max) {
            return 'NG';
        }

        return 'OK';
    }

    /**
     * Tutup trial: hasilnya dikunci dan Work Order-nya ikut ditutup.
     *
     * Jumlah OK dan NG harus masuk akal terhadap yang benar-benar diproduksi;
     * trial yang melaporkan lebih banyak barang jadi daripada yang dibuat adalah
     * laporan yang tidak bisa dipakai membuat keputusan gate.
     */
    public function finish(npd_trial_main $trial, array $data, int $userId): npd_trial_main
    {
        $this->assertDraft($trial);

        $produced = (int) $data['produced_qty'];
        $ok = (int) $data['ok_qty'];
        $ng = (int) $data['ng_qty'];

        if ($ok + $ng > $produced) {
            throw BizException::make(
                'NPD_TRIAL_QTY',
                "Jumlah OK ({$ok}) + NG ({$ng}) melebihi yang diproduksi ({$produced})."
            );
        }

        if ($trial->detail()->count() === 0) {
            throw BizException::make(
                'NPD_TRIAL_NO_RESULT',
                'Trial tanpa satu pun hasil ukur tidak dapat ditutup — itu yang menjadi bukti deliverable fase validasi.'
            );
        }

        return DB::transaction(function () use ($trial, $data, $produced, $ok, $ng) {
            $trial->update([
                'produced_qty' => $produced,
                'ok_qty' => $ok,
                'ng_qty' => $ng,
                'conclusion' => $data['conclusion'] ?? null,
                'status' => 'DONE',
            ]);

            // Work Order trial ikut ditutup: ia dibuat untuk trial ini saja, dan
            // WO trial yang menggantung akan terus terlihat sebagai pekerjaan
            // yang belum selesai di lantai produksi.
            prd_wo_main::where('id', $trial->wo_id)->update(['status' => 3]);

            AuditLogger::record(request(), "Tutup trial {$trial->code}: OK {$ok} / NG {$ng}", $trial->code);

            return $trial->fresh()->load('detail.param');
        });
    }

    public function assertDraft(npd_trial_main $trial): void
    {
        if ($trial->status !== 'DRAFT') {
            throw BizException::make('NPD_TRIAL_LOCKED', 'Trial yang sudah ditutup tidak dapat diubah.');
        }
    }
}
