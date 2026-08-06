<?php

namespace App\Support;

use App\Exceptions\BizException;
use App\Models\npd_ppap_main;
use App\Models\npd_ppap_std;
use App\Models\npd_project;
use Illuminate\Support\Facades\DB;

/**
 * PPAP: paket bukti yang membuat pelanggan mengizinkan produksi massal.
 *
 * Yang membedakan modul ini dari checklist di Excel adalah pemeriksaan
 * otomatisnya. Enam dari delapan belas elemen sudah punya jawabannya di dalam
 * sistem — DFMEA, PFMEA, control plan, hasil ukur trial, sampel produksi, dan
 * catatan perubahan teknik semuanya dibangun di tahap sebelumnya. Menandainya
 * dengan tangan berarti mengizinkan seseorang mencentang "sudah" untuk dokumen
 * yang tidak pernah dibuat, dan itulah yang membuat submission ditolak
 * pelanggan setelah berminggu-minggu menunggu.
 *
 * PRD_Modul_NPD_FTPI.md §7.6
 */
class NpdPpapService
{
    /** Nomor elemen AIAG yang bisa dijawab sistem sendiri. */
    private const AUTO = [
        2 => 'ECN proyek',
        4 => 'DFMEA final',
        6 => 'PFMEA final',
        7 => 'Control plan final',
        9 => 'Hasil ukur trial',
        14 => 'Sampel dari trial',
    ];

    /**
     * Buat submission beserta seluruh 18 elemennya.
     *
     * Semua elemen dibuat, bukan hanya yang wajib untuk level itu: elemen yang
     * tidak wajib tetap boleh dilampirkan, dan pelanggan kadang memintanya
     * belakangan tanpa menaikkan level.
     */
    public function create(npd_project $project, array $data, int $userId): npd_ppap_main
    {
        return DB::transaction(function () use ($project, $data, $userId) {
            $ppap = npd_ppap_main::create([
                'main_id' => $project->id,
                'code' => app(NumberingService::class)->next('PPAP', 'PPAP'),
                'ppap_level' => $data['ppap_level'] ?? 3,
                'psw_no' => $data['psw_no'] ?? null,
                'customer_pic' => $data['customer_pic'] ?? null,
                'note' => $data['note'] ?? null,
                'status' => 'DRAFT',
                'user_id' => $userId,
            ]);

            foreach (npd_ppap_std::orderBy('element_no')->get() as $std) {
                $ppap->detail()->create(['std_id' => $std->id, 'status' => 'OPEN']);
            }

            AuditLogger::record(request(), "Buat PPAP {$ppap->code} level {$ppap->ppap_level}", $ppap->code);

            return $this->syncFromProject($ppap->fresh());
        });
    }

    /**
     * Tandai elemen yang buktinya sudah ada di dalam sistem.
     *
     * Hanya menaikkan status, tidak pernah menurunkan: elemen yang sudah
     * ditandai selesai oleh manusia — dengan dokumen yang diunggahnya — tidak
     * boleh dibatalkan oleh pemeriksaan otomatis yang kebetulan tidak menemukan
     * padanannya.
     */
    public function syncFromProject(npd_ppap_main $ppap): npd_ppap_main
    {
        $projectId = $ppap->main_id;
        $project = npd_project::find($projectId);

        $evidence = [
            2 => DB::table('eng_ecn_main')->where('npd_project_id', $projectId)->exists(),
            4 => DB::table('npd_fmea_main')->where('main_id', $projectId)
                ->where('fmea_type', 'DESIGN')->where('status', 'FINAL')->exists(),
            6 => DB::table('npd_fmea_main')->where('main_id', $projectId)
                ->where('fmea_type', 'PROCESS')->where('status', 'FINAL')->exists(),
            7 => DB::table('npd_cp_main')->where('main_id', $projectId)->where('status', 'FINAL')->exists(),
            9 => DB::table('npd_trial_det as d')
                ->join('npd_trial_main as t', 't.id', '=', 'd.main_id')
                ->where('t.main_id', $projectId)->where('t.status', 'DONE')->exists(),
            14 => DB::table('npd_trial_main')->where('main_id', $projectId)
                ->where('status', 'DONE')->where('ok_qty', '>', 0)->exists(),
        ];

        $ppap->load('detail.std');

        foreach ($ppap->detail as $line) {
            $no = (int) ($line->std?->element_no ?? 0);

            if (! isset($evidence[$no]) || ! $evidence[$no] || $line->status !== 'OPEN') {
                continue;
            }

            $line->update([
                'status' => 'DONE',
                'auto_source' => self::AUTO[$no],
                'note' => $line->note ?: 'Ditandai sistem dari data proyek '.($project->code ?? ''),
            ]);
        }

        return $ppap->fresh()->load('detail.std', 'detail.doc');
    }

    /**
     * Perbarui satu elemen dengan tangan.
     *
     * NA menuntut alasan — "tidak berlaku" tanpa penjelasan adalah cara paling
     * halus melewatkan bukti yang sebenarnya diminta pelanggan.
     */
    public function updateElement(npd_ppap_main $ppap, int $detailId, array $data): npd_ppap_main
    {
        $this->assertDraft($ppap);

        if ($data['status'] === 'NA' && blank($data['note'] ?? null)) {
            throw BizException::make(
                'NPD_PPAP_NA_REASON',
                'Elemen yang ditandai tidak berlaku harus disertai alasan.'
            );
        }

        $line = $ppap->detail()->findOrFail($detailId);
        $line->update([
            'status' => $data['status'],
            'doc_id' => $data['doc_id'] ?? $line->doc_id,
            'note' => $data['note'] ?? $line->note,
            // Diubah tangan berarti bukan lagi hasil pemeriksaan otomatis.
            'auto_source' => null,
        ]);

        return $ppap->fresh()->load('detail.std', 'detail.doc');
    }

    /**
     * Kirim ke pelanggan.
     *
     * Ditolak selama masih ada elemen wajib untuk level ini yang belum beres,
     * dan pesannya menyebut nomor serta nama elemennya — itulah bahasa yang
     * dipakai pelanggan saat menolak, jadi itu pula yang harus dipakai di sini.
     */
    public function submit(npd_ppap_main $ppap, array $data): npd_ppap_main
    {
        $this->assertDraft($ppap);
        $ppap->load('detail.std');

        if (blank($ppap->psw_no) && blank($data['psw_no'] ?? null)) {
            throw BizException::make('NPD_PPAP_PSW', 'Nomor PSW harus diisi sebelum submission dikirim.');
        }

        $outstanding = $ppap->outstanding();

        if ($outstanding->isNotEmpty()) {
            $names = $outstanding
                ->map(fn ($d) => "#{$d->std->element_no} {$d->std->name}")
                ->implode('; ');

            throw BizException::make(
                'NPD_PPAP_INCOMPLETE',
                "Elemen wajib untuk PPAP level {$ppap->ppap_level} belum lengkap: {$names}. "
                .'Lengkapi buktinya, atau tandai tidak berlaku beserta alasannya.'
            );
        }

        $ppap->update([
            'psw_no' => $data['psw_no'] ?? $ppap->psw_no,
            'submission_date' => $data['submission_date'] ?? now()->toDateString(),
            'customer_pic' => $data['customer_pic'] ?? $ppap->customer_pic,
            'status' => 'SUBMITTED',
        ]);

        AuditLogger::record(request(), "Kirim PPAP {$ppap->code} ke pelanggan", $ppap->code);

        return $ppap->fresh()->load('detail.std', 'detail.doc');
    }

    /**
     * Catat jawaban pelanggan.
     *
     * INTERIM adalah izin sementara dengan syarat — bukan persetujuan penuh,
     * dan karena itu tidak membuka serah terima.
     */
    public function recordDecision(npd_ppap_main $ppap, array $data): npd_ppap_main
    {
        if (! in_array($ppap->status, ['SUBMITTED', 'INTERIM'], true)) {
            throw BizException::make(
                'NPD_PPAP_STATE',
                'Hanya PPAP yang sudah dikirim yang bisa dicatat keputusannya.'
            );
        }

        $ppap->update([
            'status' => $data['status'],
            'approval_date' => $data['approval_date'] ?? now()->toDateString(),
            'customer_pic' => $data['customer_pic'] ?? $ppap->customer_pic,
            'note' => $data['note'] ?? $ppap->note,
        ]);

        AuditLogger::record(request(), "PPAP {$ppap->code}: {$data['status']} oleh pelanggan", $ppap->code);

        return $ppap->fresh()->load('detail.std', 'detail.doc');
    }

    /** Apakah proyek ini sudah punya PPAP yang disetujui penuh. */
    public function hasApproved(int $projectId): bool
    {
        return npd_ppap_main::where('main_id', $projectId)->where('status', 'APPROVED')->exists();
    }

    private function assertDraft(npd_ppap_main $ppap): void
    {
        if ($ppap->status !== 'DRAFT') {
            throw BizException::make(
                'NPD_PPAP_LOCKED',
                'PPAP yang sudah dikirim tidak dapat diubah. Buat submission baru bila perlu.'
            );
        }
    }
}
