<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\prc_contract_main;
use App\Models\prc_quot_det;
use App\Models\prc_quot_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use App\Support\VendorQuotationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Penawaran vendor dan kontrak berperiode (PRD §4.7).
 *
 * Keduanya satu layar karena satu alur: penawaran masuk, dibandingkan, yang
 * menang jadi syarat beli — dan bila disepakati untuk jangka panjang, dikunci
 * sebagai kontrak.
 */
class QuotationController extends Controller
{
    private array $with = ['ven', 'detail.item'];

    public function __construct(private VendorQuotationService $svc) {}

    /* ---------------- penawaran ---------------- */

    public function index(Request $request)
    {
        $rows = prc_quot_main::with(['ven'])->withCount('detail')
            ->when($request->query('q'), fn ($q, $s) => $q->where('code', 'like', "%{$s}%")->orWhere('ref_no', 'like', "%{$s}%"))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('ven_id'), fn ($q, $s) => $q->where('ven_id', $s))
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        return ApiResponse::item(prc_quot_main::with($this->with)->findOrFail($id));
    }

    /** Perbandingan harga per material dari seluruh vendor. */
    public function comparison(Request $request)
    {
        $request->validate(['item_id' => ['nullable', 'integer', 'exists:m_item,id']]);

        return ApiResponse::collection(
            $this->svc->comparison($request->query('item_id') ? (int) $request->query('item_id') : null)
        );
    }

    public function store(Request $request)
    {
        $data = $this->validateQuot($request);

        $quot = DB::transaction(function () use ($data, $request) {
            $quot = prc_quot_main::create([
                'code' => app(NumberingService::class)->next('QUOT', 'QT'),
                'date' => $data['date'],
                'ven_id' => $data['ven_id'],
                'ref_no' => $data['ref_no'] ?? null,
                'currency_id' => $data['currency_id'] ?? null,
                'valid_from' => $data['valid_from'] ?? null,
                'valid_to' => $data['valid_to'] ?? null,
                'note' => $data['note'] ?? null,
                'status' => $data['status'] ?? 'RECEIVED',
                'user_id' => $request->user()->id,
            ]);
            $this->syncLines($quot, $data['lines'] ?? []);
            AuditLogger::record($request, "Catat penawaran {$quot->code}", $quot->code);

            return $quot;
        });

        return ApiResponse::item($quot->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $quot = prc_quot_main::findOrFail($id);
        $this->assertEditable($quot);
        $data = $this->validateQuot($request);

        DB::transaction(function () use ($quot, $data) {
            $quot->update([
                'date' => $data['date'],
                'ven_id' => $data['ven_id'],
                'ref_no' => $data['ref_no'] ?? null,
                'currency_id' => $data['currency_id'] ?? null,
                'valid_from' => $data['valid_from'] ?? null,
                'valid_to' => $data['valid_to'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
            $quot->detail()->delete();
            $this->syncLines($quot, $data['lines'] ?? []);
        });

        return ApiResponse::item($quot->fresh()->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $quot = prc_quot_main::findOrFail($id);
        $this->assertEditable($quot);

        DB::transaction(function () use ($quot, $request) {
            $quot->detail()->delete();
            $quot->delete();
            AuditLogger::record($request, "Hapus penawaran {$quot->code}", $quot->code);
        });

        return ApiResponse::item(['message' => 'Penawaran dihapus.']);
    }

    /** Pilih satu baris penawaran menjadi syarat beli material itu. */
    public function selectLine(Request $request, int $detailId)
    {
        $line = prc_quot_det::findOrFail($detailId);
        $terms = $this->svc->select($line, $request->user()->id);

        return ApiResponse::item([
            'supplier_item' => $terms,
            'message' => 'Syarat beli material ini kini mengikuti penawaran tersebut.',
        ]);
    }

    public function reject(Request $request, int $id)
    {
        $quot = prc_quot_main::findOrFail($id);
        $request->validate(['note' => ['required', 'string', 'max:300']]);

        $quot->update(['status' => 'REJECTED', 'note' => $request->input('note')]);
        AuditLogger::record($request, "Tolak penawaran {$quot->code}: ".$request->input('note'), $quot->code);

        return ApiResponse::item($quot->load($this->with));
    }

    /* ---------------- kontrak ---------------- */

    public function contracts(Request $request)
    {
        $rows = prc_contract_main::with(['ven'])->withCount('detail')
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function showContract(int $id)
    {
        return ApiResponse::item(prc_contract_main::with(['ven', 'detail.item'])->findOrFail($id));
    }

    public function storeContract(Request $request)
    {
        $data = $this->validateContract($request);

        $contract = DB::transaction(function () use ($data, $request) {
            $contract = prc_contract_main::create([
                'code' => app(NumberingService::class)->next('CONTRACT', 'CTR'),
                'date' => $data['date'],
                'ven_id' => $data['ven_id'],
                'ref_no' => $data['ref_no'] ?? null,
                'quot_id' => $data['quot_id'] ?? null,
                'valid_from' => $data['valid_from'],
                'valid_to' => $data['valid_to'],
                'note' => $data['note'] ?? null,
                'status' => 'DRAFT',
                'user_id' => $request->user()->id,
            ]);

            foreach ($data['lines'] as $l) {
                $contract->detail()->create($l);
            }

            AuditLogger::record($request, "Buat kontrak {$contract->code}", $contract->code);

            return $contract;
        });

        return ApiResponse::item($contract->load(['ven', 'detail.item']), 201);
    }

    /** Aktifkan kontrak: harganya mengunci syarat beli selama masa berlakunya. */
    public function activateContract(Request $request, int $id)
    {
        $contract = prc_contract_main::findOrFail($id);
        $n = $this->svc->activateContract($contract, $request->user()->id);

        return ApiResponse::item([
            'contract' => $contract->fresh()->load(['ven', 'detail.item']),
            'message' => "Kontrak aktif — {$n} material kini memakai harga kontrak.",
        ]);
    }

    public function cancelContract(Request $request, int $id)
    {
        $contract = prc_contract_main::findOrFail($id);
        $request->validate(['note' => ['required', 'string', 'max:300']]);

        if ($contract->status === 'CANCELLED') {
            throw BizException::make('CONTRACT_STATE', 'Kontrak ini sudah dibatalkan.');
        }

        DB::transaction(function () use ($contract, $request) {
            // Syarat beli tidak dihapus — harga terakhir tetap yang paling masuk
            // akal dipakai — tetapi ikatan kontraknya dilepas.
            DB::table('m_supplier_item')->where('contract_id', $contract->id)->update(['contract_id' => null]);
            $contract->update(['status' => 'CANCELLED', 'note' => $request->input('note')]);
            AuditLogger::record($request, "Batalkan kontrak {$contract->code}", $contract->code);
        });

        return ApiResponse::item($contract->fresh()->load(['ven', 'detail.item']));
    }

    /* ---------------- pembantu ---------------- */

    private function assertEditable(prc_quot_main $quot): void
    {
        if ($quot->status === 'SELECTED') {
            throw BizException::make(
                'QUOT_LOCKED',
                'Penawaran yang sudah dipilih menjadi syarat beli tidak dapat diubah — ia jadi dasar harga yang berlaku.'
            );
        }
    }

    private function syncLines(prc_quot_main $quot, array $lines): void
    {
        foreach ($lines as $l) {
            $quot->detail()->create([
                'item_id' => $l['item_id'],
                'price' => $l['price'],
                'moq' => $l['moq'] ?? 0,
                'order_lot' => $l['order_lot'] ?? 0,
                'lead_time_days' => $l['lead_time_days'] ?? 0,
                'note' => $l['note'] ?? null,
            ]);
        }
    }

    private function validateQuot(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'ven_id' => ['required', 'integer', 'exists:m_contacts,id'],
            'ref_no' => ['nullable', 'string', 'max:50'],
            'currency_id' => ['nullable', 'integer', 'exists:m_currency,id'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'status' => ['nullable', 'in:DRAFT,RECEIVED'],
            'note' => ['nullable', 'string', 'max:300'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:m_item,id'],
            'lines.*.price' => ['required', 'numeric', 'min:0'],
            'lines.*.moq' => ['nullable', 'integer', 'min:0'],
            'lines.*.order_lot' => ['nullable', 'integer', 'min:0'],
            'lines.*.lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'lines.*.note' => ['nullable', 'string', 'max:150'],
        ]);
    }

    private function validateContract(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'ven_id' => ['required', 'integer', 'exists:m_contacts,id'],
            'ref_no' => ['nullable', 'string', 'max:50'],
            'quot_id' => ['nullable', 'integer', 'exists:prc_quot_main,id'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['required', 'date', 'after_or_equal:valid_from'],
            'note' => ['nullable', 'string', 'max:300'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:m_item,id'],
            'lines.*.price' => ['required', 'numeric', 'min:0'],
            'lines.*.moq' => ['nullable', 'integer', 'min:0'],
            'lines.*.order_lot' => ['nullable', 'integer', 'min:0'],
            'lines.*.lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'lines.*.commit_qty' => ['nullable', 'integer', 'min:0'],
            'lines.*.note' => ['nullable', 'string', 'max:150'],
        ]);
    }
}
