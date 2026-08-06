<?php

namespace App\Http\Controllers\Api\Whs;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\whs_inc_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use App\Support\WhsPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Penerimaan barang WHS.
 *
 * Dokumen DRAFT belum menyentuh stok apa pun; barang baru dianggap ada setelah
 * di-post. Pemisahan itu yang membuat petugas gudang bisa mengetik dulu,
 * mencocokkan dengan surat jalan, baru mengesahkan.
 */
class WhsIncomingController extends Controller
{
    private array $with = ['detail.item', 'po', 'ven'];

    public function __construct(private WhsPostingService $posting) {}

    public function index(Request $request)
    {
        $rows = whs_inc_main::with(['po', 'ven'])->withCount('detail')
            ->when($request->query('q'), fn ($q, $s) => $q->where('code', 'like', "%{$s}%")
                ->orWhere('do_no', 'like', "%{$s}%"))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        return ApiResponse::item(whs_inc_main::with($this->with)->findOrFail($id));
    }

    /** PO WHS yang masih menunggu barang — untuk pemilih di layar penerimaan. */
    public function openPos()
    {
        $rows = DB::table('whs_po_main as m')
            ->leftJoin('m_contacts as v', 'v.id', '=', 'm.ven_id')
            ->whereIn('m.status', ['APPROVED'])
            ->whereExists(fn ($q) => $q->from('whs_po_det as d')
                ->whereColumn('d.main_id', 'm.id')
                ->whereColumn('d.qty_received', '<', 'd.qty'))
            ->orderByDesc('m.id')
            ->get(['m.id', 'm.code', 'm.date', 'm.ven_id', 'v.company_n as vendor']);

        return ApiResponse::collection($rows);
    }

    public function store(Request $request)
    {
        $data = $this->validateInc($request);

        $inc = DB::transaction(function () use ($data, $request) {
            $inc = whs_inc_main::create([
                'code' => app(NumberingService::class)->next('WHSIN', 'WIN'),
                'date' => $data['date'],
                'po_id' => $data['po_id'] ?? null,
                'ven_id' => $data['ven_id'] ?? null,
                'do_no' => $data['do_no'] ?? null,
                'note' => $data['note'] ?? null,
                'user_id' => $request->user()->id,
                'status' => 'DRAFT',
            ]);
            $this->syncLines($inc, $data['lines']);
            AuditLogger::record($request, "Create Penerimaan WHS {$inc->code}", $inc->code);

            return $inc;
        });

        return ApiResponse::item($inc->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $inc = whs_inc_main::findOrFail($id);
        $this->assertDraft($inc);
        $data = $this->validateInc($request);

        DB::transaction(function () use ($inc, $data, $request) {
            $inc->update([
                'date' => $data['date'],
                'po_id' => $data['po_id'] ?? null,
                'ven_id' => $data['ven_id'] ?? null,
                'do_no' => $data['do_no'] ?? null,
                'note' => $data['note'] ?? null,
            ]);
            $inc->detail()->delete();
            $this->syncLines($inc, $data['lines']);
            AuditLogger::record($request, "Update Penerimaan WHS {$inc->code}", $inc->code);
        });

        return ApiResponse::item($inc->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $inc = whs_inc_main::findOrFail($id);
        $this->assertDraft($inc);

        DB::transaction(function () use ($inc, $request) {
            $inc->detail()->delete();
            $inc->delete();
            AuditLogger::record($request, "Delete Penerimaan WHS {$inc->code}", $inc->code);
        });

        return ApiResponse::item(['message' => 'Penerimaan WHS dihapus.']);
    }

    /** Sahkan: stok bertambah, unit alat dibuat, jurnal ditulis. */
    public function post(Request $request, int $id)
    {
        $inc = whs_inc_main::findOrFail($id);
        $posted = $this->posting->postIncoming($inc, $request->user()->id);

        AuditLogger::record($request, "Post Penerimaan WHS {$inc->code}", $inc->code);

        return ApiResponse::item($posted->load($this->with));
    }

    /* ---------------- helpers ---------------- */

    private function assertDraft(whs_inc_main $inc): void
    {
        if ($inc->status !== 'DRAFT') {
            throw BizException::make('WHS_INC_LOCKED', 'Penerimaan yang sudah di-post tidak dapat diubah.');
        }
    }

    private function syncLines(whs_inc_main $inc, array $lines): void
    {
        foreach ($lines as $l) {
            $inc->detail()->create([
                'po_det_id' => $l['po_det_id'] ?? null,
                'item_id' => $l['item_id'],
                'qty' => $l['qty'],
                'unit_cost' => $l['unit_cost'] ?? 0,
                'note' => $l['note'] ?? null,
            ]);
        }
    }

    private function validateInc(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'po_id' => ['nullable', 'integer', 'exists:whs_po_main,id'],
            'ven_id' => ['nullable', 'integer', 'exists:m_contacts,id'],
            'do_no' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:300'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.po_det_id' => ['nullable', 'integer', 'exists:whs_po_det,id'],
            'lines.*.item_id' => ['required', 'integer', 'exists:m_whs_item,id'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'lines.*.note' => ['nullable', 'string', 'max:150'],
        ]);
    }
}
