<?php

namespace App\Http\Controllers\Api\Npd;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\npd_doc;
use App\Models\npd_ppap_main;
use App\Models\npd_ppap_std;
use App\Models\npd_project;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NpdPpapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PPAP submission dan checklist 18 elemennya.
 *
 * PRD_Modul_NPD_FTPI.md §7.6
 */
class NpdPpapController extends Controller
{
    public function __construct(private NpdPpapService $svc) {}

    public function show(int $projectId)
    {
        $project = npd_project::with('item')->findOrFail($projectId);

        return ApiResponse::item([
            'project' => $project,
            'submissions' => npd_ppap_main::with(['detail.std', 'detail.doc'])
                ->where('main_id', $projectId)->orderByDesc('id')->get()
                ->map(fn ($p) => $p->toArray() + [
                    'outstanding' => $p->outstanding()->map(fn ($d) => [
                        'element_no' => $d->std->element_no,
                        'name' => $d->std->name,
                    ])->values(),
                ]),
            'elements' => npd_ppap_std::orderBy('element_no')->get(),
            // Dokumen proyek untuk dilampirkan sebagai bukti elemen.
            'docs' => npd_doc::where('main_id', $projectId)->where('is_current', 1)
                ->orderByDesc('id')->get(['id', 'file_name', 'doc_type', 'version']),
        ]);
    }

    public function store(Request $request, int $projectId)
    {
        $project = npd_project::findOrFail($projectId);

        $data = $request->validate([
            'ppap_level' => ['required', 'integer', 'min:1', 'max:5'],
            'psw_no' => ['nullable', 'string', 'max:50'],
            'customer_pic' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:400'],
        ]);

        $ppap = $this->svc->create($project, $data, $request->user()->id);

        return ApiResponse::item($ppap, 201);
    }

    /** Periksa ulang elemen yang buktinya sudah ada di dalam sistem. */
    public function sync(Request $request, int $id)
    {
        $ppap = npd_ppap_main::findOrFail($id);
        $synced = $this->svc->syncFromProject($ppap);

        AuditLogger::record($request, "Cek otomatis elemen PPAP {$ppap->code}", $ppap->code);

        return ApiResponse::item($synced);
    }

    public function updateElement(Request $request, int $id, int $detailId)
    {
        $ppap = npd_ppap_main::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'in:OPEN,DONE,NA'],
            'doc_id' => ['nullable', 'integer', 'exists:npd_doc,id'],
            'note' => ['nullable', 'string', 'max:250'],
        ]);

        return ApiResponse::item($this->svc->updateElement($ppap, $detailId, $data));
    }

    public function submit(Request $request, int $id)
    {
        $ppap = npd_ppap_main::findOrFail($id);

        $data = $request->validate([
            'psw_no' => ['nullable', 'string', 'max:50'],
            'submission_date' => ['nullable', 'date'],
            'customer_pic' => ['nullable', 'string', 'max:60'],
        ]);

        return ApiResponse::item($this->svc->submit($ppap, $data));
    }

    /** Catat jawaban pelanggan: disetujui, sementara, atau ditolak. */
    public function decision(Request $request, int $id)
    {
        $ppap = npd_ppap_main::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'in:INTERIM,APPROVED,REJECTED'],
            'approval_date' => ['nullable', 'date'],
            'customer_pic' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:400'],
        ]);

        return ApiResponse::item($this->svc->recordDecision($ppap, $data));
    }

    public function destroy(Request $request, int $id)
    {
        $ppap = npd_ppap_main::findOrFail($id);

        if ($ppap->status !== 'DRAFT') {
            throw BizException::make(
                'NPD_PPAP_LOCKED',
                'PPAP yang sudah dikirim ke pelanggan tidak dapat dihapus — riwayatnya bagian dari jejak audit.'
            );
        }

        DB::transaction(function () use ($ppap, $request) {
            $ppap->detail()->delete();
            $ppap->delete();
            AuditLogger::record($request, "Hapus PPAP {$ppap->code}", $ppap->code);
        });

        return ApiResponse::item(['message' => 'PPAP dihapus.']);
    }
}
