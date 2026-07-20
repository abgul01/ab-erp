<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\prc_cost_main;
use App\Models\prc_inv_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Landed Cost sheet (Fase 2F). One sheet references ONE AP invoice
 * (prc_cost_main.inv_id): additional costs (freight/duty/VAT import/...) are
 * allocated across the invoice's GR receipt lines by invoiced weight into
 * prc_cost_alloc (unit_cost_kg). Lifecycle: DRAFT → FINAL.
 */
class CostController extends Controller
{
    private array $with = ['inv.ven', 'po', 'user', 'detail.currency', 'alloc.grDetail.item'];

    public function index(Request $request)
    {
        $query = prc_cost_main::with(['inv'])->withCount('detail');
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where('code', 'like', "%{$q}%");
        }
        $query->orderByDesc('id');

        return ApiResponse::paginated($query->paginate(min(max((int) $request->query('per_page', 20), 1), 200)));
    }

    public function show(int $id)
    {
        return ApiResponse::item(prc_cost_main::with($this->with)->findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $this->validateCost($request);
        $inv = prc_inv_main::with('detail.grDetail')->findOrFail($data['inv_id']);

        $cost = DB::transaction(function () use ($data, $inv, $request) {
            $cost = prc_cost_main::create([
                'code' => (new NumberingService)->next('COST', 'LC'),
                'date' => $data['date'],
                'inv_id' => $inv->id,
                'po_id' => $this->poIdFor($inv),
                'gr_id' => optional(optional($inv->detail->first())->grDetail)->id_prim,
                'alloc_basis' => $data['alloc_basis'] ?? 'WEIGHT',
                'status' => 'DRAFT',
                'user_id' => $request->user()->id,
            ]);
            $this->syncCosts($cost, $data['costs'] ?? []);
            AuditLogger::record($request, "Create Landed Cost {$cost->code} (INV {$inv->inv_no})", $cost->code);

            return $cost;
        });

        return ApiResponse::item($cost->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $cost = prc_cost_main::findOrFail($id);
        $this->assertDraft($cost);
        $data = $this->validateCost($request);
        $inv = prc_inv_main::with('detail.grDetail')->findOrFail($data['inv_id']);

        DB::transaction(function () use ($cost, $data, $inv, $request) {
            $cost->update([
                'date' => $data['date'],
                'inv_id' => $inv->id,
                'po_id' => $this->poIdFor($inv),
                'gr_id' => optional(optional($inv->detail->first())->grDetail)->id_prim,
                'alloc_basis' => $data['alloc_basis'] ?? 'WEIGHT',
            ]);
            $cost->detail()->delete();
            $cost->alloc()->delete();
            $this->syncCosts($cost, $data['costs'] ?? []);
            AuditLogger::record($request, "Update Landed Cost {$cost->code}", $cost->code);
        });

        return ApiResponse::item($cost->fresh()->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $cost = prc_cost_main::findOrFail($id);
        $this->assertDraft($cost);
        DB::transaction(function () use ($cost, $request) {
            $cost->detail()->delete();
            $cost->alloc()->delete();
            $cost->delete();
            AuditLogger::record($request, "Delete Landed Cost {$cost->code}", $cost->code);
        });

        return ApiResponse::item(['message' => 'Landed cost berhasil dihapus.']);
    }

    /** Finalize: allocate the total cost over the invoice's receipt lines by invoiced weight. */
    public function finalize(Request $request, int $id)
    {
        $cost = prc_cost_main::with('detail')->findOrFail($id);
        $this->assertDraft($cost);

        $inv = prc_inv_main::with('detail.grDetail')->findOrFail($cost->inv_id);
        if ($inv->detail->isEmpty()) {
            throw BizException::make('LC_NO_LINES', 'Invoice terpilih tidak memiliki baris penerimaan.');
        }

        // Invoiced weight per line = (qty invoice / qty GR) × w_total GR.
        $weights = [];
        foreach ($inv->detail as $line) {
            $grDet = $line->grDetail;
            if (! $grDet || (int) $grDet->qty <= 0) {
                continue;
            }
            $w = ((int) $line->qty / (int) $grDet->qty) * (float) $grDet->w_total;
            if ($w > 0) {
                $weights[$grDet->id] = ($weights[$grDet->id] ?? 0) + $w;
            }
        }
        $totalWeight = array_sum($weights);
        if ($totalWeight <= 0) {
            throw BizException::make('LC_NO_WEIGHT', 'Berat tertagih nol, alokasi berbasis berat tidak mungkin.');
        }
        $totalCost = (float) $cost->detail->sum('amount_idr');

        DB::transaction(function () use ($cost, $weights, $totalWeight, $totalCost, $request) {
            $cost->alloc()->delete();
            foreach ($weights as $grDetailId => $w) {
                $amount = round($totalCost * ($w / $totalWeight), 2);
                $cost->alloc()->create([
                    'gr_detail_id' => $grDetailId,
                    'serial_id' => null,
                    'amount' => $amount,
                    'unit_cost_kg' => round($amount / $w, 4),
                ]);
            }
            $cost->update(['status' => 'FINAL']);
            AuditLogger::record($request, "Finalize Landed Cost {$cost->code}", $cost->code);
        });

        return ApiResponse::item($cost->load($this->with));
    }

    /** Legacy po_id NOT NULL: derive from the invoice header or its first GR line. */
    private function poIdFor(prc_inv_main $inv): int
    {
        $poId = $inv->po_id ?: optional(optional($inv->detail->first())->grDetail)->po_id;
        if (! $poId) {
            throw BizException::make('LC_NO_PO', 'PO asal tidak dapat ditentukan dari invoice ini.');
        }

        return (int) $poId;
    }

    private function assertDraft(prc_cost_main $cost): void
    {
        if ($cost->status !== 'DRAFT') {
            throw BizException::make('LC_LOCKED', 'Landed cost yang sudah FINAL tidak dapat diubah.');
        }
    }

    private function syncCosts(prc_cost_main $cost, array $costs): void
    {
        foreach ($costs as $c) {
            $rate = (float) ($c['rate'] ?? 1);
            $amount = (float) $c['amount'];
            $cost->detail()->create([
                'cost_type' => $c['cost_type'],
                'descrip' => $c['descrip'] ?? null,
                'amount' => $amount,
                'currency_id' => $c['currency_id'] ?? null,
                'rate' => $rate,
                'amount_idr' => round($amount * $rate, 2),
            ]);
        }
    }

    private function validateCost(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'inv_id' => ['required', 'integer', 'exists:prc_inv_main,id'],
            'alloc_basis' => ['nullable', 'in:WEIGHT'],
            'costs' => ['array'],
            'costs.*.cost_type' => ['required', 'string', 'max:30'],
            'costs.*.descrip' => ['nullable', 'string', 'max:200'],
            'costs.*.amount' => ['required', 'numeric', 'min:0'],
            'costs.*.currency_id' => ['nullable', 'integer', 'exists:m_currency,id'],
            'costs.*.rate' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
