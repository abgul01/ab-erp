<?php

namespace App\Http\Controllers\Api\Npd;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\npd_doc;
use App\Models\npd_project;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Dokumen proyek: drawing, spesifikasi, laporan trial, bukti PPAP.
 *
 * Revisi lama tidak dihapus. Yang berlaku hanya satu (`is_current`), tetapi
 * riwayatnya tetap bisa dibuka — pertanyaan "part ini dulu dibuat berdasarkan
 * drawing revisi berapa" hanya bisa dijawab kalau revisi lamanya masih ada.
 *
 * PRD_Modul_NPD_FTPI.md §7.3
 */
class NpdDocController extends Controller
{
    /** Batas ini ditegakkan di server; batas yang hanya ada di layar bukan batas. */
    private const MAX_KB = 20480;                   // 20 MB

    private const ALLOWED = [
        'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'dwg', 'dxf', 'step', 'stp', 'igs', 'csv', 'txt', 'zip',
    ];

    public function index(Request $request, int $projectId)
    {
        $rows = npd_doc::with('uploader')
            ->where('main_id', $projectId)
            ->when($request->query('ref_type'), fn ($q, $s) => $q->where('ref_type', $s))
            ->when($request->boolean('current_only'), fn ($q) => $q->where('is_current', 1))
            ->orderByDesc('id')
            ->get();

        return ApiResponse::collection($rows);
    }

    public function store(Request $request, int $projectId)
    {
        $project = npd_project::findOrFail($projectId);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.self::MAX_KB],
            'doc_type' => ['nullable', 'in:DRAWING,SPEC,REPORT,CERT,OTHER'],
            'ref_type' => ['nullable', 'string', 'max:20'],
            'ref_id' => ['nullable', 'integer'],
            'version' => ['nullable', 'string', 'max:20'],
            'supersedes_id' => ['nullable', 'integer', 'exists:npd_doc,id'],
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());

        if (! in_array($ext, self::ALLOWED, true)) {
            throw BizException::make(
                'NPD_DOC_TYPE',
                "Jenis berkas .{$ext} tidak diterima. Yang diterima: ".implode(', ', self::ALLOWED).'.'
            );
        }

        $doc = DB::transaction(function () use ($project, $file, $data, $request, $ext) {
            $path = $file->storeAs(
                "npd/{$project->id}",
                now()->format('YmdHis').'-'.Str::random(6).'.'.$ext
            );

            /*
             * Mengunggah revisi baru menurunkan revisi sebelumnya, bukan
             * menimpanya. Yang lama tetap bisa diunduh.
             */
            if (! empty($data['supersedes_id'])) {
                npd_doc::where('id', $data['supersedes_id'])
                    ->where('main_id', $project->id)
                    ->update(['is_current' => false]);
            }

            $doc = npd_doc::create([
                'main_id' => $project->id,
                'ref_type' => $data['ref_type'] ?? null,
                'ref_id' => $data['ref_id'] ?? null,
                'doc_type' => $data['doc_type'] ?? 'OTHER',
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime' => $file->getClientMimeType(),
                'size_kb' => (int) ceil($file->getSize() / 1024),
                'version' => $data['version'] ?? 'rev A',
                'is_current' => true,
                'user_id' => $request->user()->id,
            ]);

            AuditLogger::record($request, "Unggah dokumen NPD {$doc->file_name} ({$project->code})", $project->code);

            return $doc;
        });

        return ApiResponse::item($doc->load('uploader'), 201);
    }

    public function download(int $id)
    {
        $doc = npd_doc::findOrFail($id);

        if (! Storage::exists($doc->file_path)) {
            throw BizException::make('NPD_DOC_MISSING', 'Berkasnya tidak ada di penyimpanan.');
        }

        return Storage::download($doc->file_path, $doc->file_name);
    }

    public function destroy(Request $request, int $id)
    {
        $doc = npd_doc::findOrFail($id);

        // Dokumen yang menjadi bukti sebuah deliverable tidak boleh hilang
        // begitu saja — deliverable-nya akan menunjuk ke ruang kosong.
        if (DB::table('npd_deliverable')->where('doc_id', $doc->id)->exists()) {
            throw BizException::make(
                'NPD_DOC_USED',
                'Dokumen ini dipakai sebagai bukti deliverable. Lepaskan dulu tautannya.'
            );
        }

        Storage::delete($doc->file_path);
        $doc->delete();
        AuditLogger::record($request, "Hapus dokumen NPD {$doc->file_name}");

        return ApiResponse::item(['message' => 'Dokumen dihapus.']);
    }
}
