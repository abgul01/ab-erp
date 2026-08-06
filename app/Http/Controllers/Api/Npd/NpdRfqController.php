<?php

namespace App\Http\Controllers\Api\Npd;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\npd_feasibility;
use App\Models\npd_rfq;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

/**
 * RFQ pelanggan dan studi kelayakannya.
 *
 * Kesimpulan feasibility yang menentukan: hanya GO yang boleh dilanjutkan
 * menjadi proyek. NO_GO menutup RFQ, CONDITIONAL berarti masih ada syarat yang
 * harus dijawab lebih dulu.
 *
 * PRD_Modul_NPD_FTPI.md §7.1
 */
class NpdRfqController extends Controller
{
    private array $with = ['cus', 'feasibility', 'project'];

    public function index(Request $request)
    {
        $rows = npd_rfq::with($this->with)
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$s}%")
                ->orWhere('part_name', 'like', "%{$s}%")))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->boolean('open_only'), fn ($q) => $q->whereNull('main_id'))
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        return ApiResponse::item(npd_rfq::with($this->with)->findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $this->validateRfq($request);
        $rfq = npd_rfq::create($data + ['user_id' => $request->user()->id, 'status' => 'OPEN']);

        AuditLogger::record($request, "Catat RFQ {$rfq->code}", $rfq->code);

        return ApiResponse::item($rfq->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $rfq = npd_rfq::findOrFail($id);

        if ($rfq->main_id) {
            throw BizException::make('NPD_RFQ_LOCKED', 'RFQ yang sudah menjadi proyek tidak dapat diubah.');
        }

        $rfq->update($this->validateRfq($request));
        AuditLogger::record($request, "Ubah RFQ {$rfq->code}", $rfq->code);

        return ApiResponse::item($rfq->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $rfq = npd_rfq::findOrFail($id);

        if ($rfq->main_id) {
            throw BizException::make('NPD_RFQ_LOCKED', 'RFQ yang sudah menjadi proyek tidak dapat dihapus.');
        }

        $rfq->feasibility()->delete();
        $rfq->delete();
        AuditLogger::record($request, "Hapus RFQ {$rfq->code}", $rfq->code);

        return ApiResponse::item(['message' => 'RFQ dihapus.']);
    }

    /**
     * Simpan studi kelayakan.
     *
     * Kesimpulan GO ditolak selama masih ada aspek yang belum sanggup —
     * menyatakan layak sambil mencentang "kapasitas tidak tersedia" adalah cara
     * proyek berhenti di gate pertama dengan alasan yang sudah diketahui sejak
     * awal.
     */
    public function saveFeasibility(Request $request, int $id)
    {
        $rfq = npd_rfq::findOrFail($id);

        $data = $request->validate([
            'tech_ok' => ['boolean'],
            'capacity_ok' => ['boolean'],
            'cost_ok' => ['boolean'],
            'material_avail' => ['nullable', 'string', 'max:150'],
            'conclusion' => ['required', 'in:GO,NO_GO,CONDITIONAL'],
            'note' => ['nullable', 'string', 'max:400'],
        ]);

        if ($data['conclusion'] === 'GO') {
            $gaps = collect([
                'teknis' => $data['tech_ok'] ?? false,
                'kapasitas' => $data['capacity_ok'] ?? false,
                'biaya' => $data['cost_ok'] ?? false,
            ])->filter(fn ($ok) => ! $ok)->keys();

            if ($gaps->isNotEmpty()) {
                throw BizException::make(
                    'NPD_FEAS_GAP',
                    'Kesimpulan GO tidak bisa diberikan selama aspek berikut belum sanggup: '
                    .$gaps->implode(', ').'. Pakai CONDITIONAL bila masih ada syarat.'
                );
            }
        }

        $feas = npd_feasibility::updateOrCreate(
            ['rfq_id' => $rfq->id],
            $data + [
                'main_id' => $rfq->main_id,
                'user_id' => $request->user()->id,
                'evaluated_at' => now(),
            ]
        );

        if ($data['conclusion'] === 'NO_GO') {
            $rfq->update(['status' => 'LOST']);
        }

        AuditLogger::record($request, "Feasibility RFQ {$rfq->code}: {$data['conclusion']}", $rfq->code);

        return ApiResponse::item($rfq->fresh()->load($this->with));
    }

    private function validateRfq(Request $request): array
    {
        return $request->validate([
            'cus_id' => ['required', 'integer', 'exists:m_contacts,id'],
            'code' => ['required', 'string', 'max:50'],
            'date' => ['required', 'date'],
            'part_name' => ['required', 'string', 'max:100'],
            'drawing_ref' => ['nullable', 'string', 'max:50'],
            'qty' => ['nullable', 'integer', 'min:0'],
            'target_price' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);
    }
}
