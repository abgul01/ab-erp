<?php

namespace App\Http\Controllers\Api\Sales;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\sls_do_main;
use App\Models\sls_inv_detail;
use App\Models\sls_inv_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Sales Invoice (Fase 5 → AR). Bills a customer for goods already shipped on
 * Delivery Orders. Lines are pulled from the DO details at the price frozen on
 * the originating Sales Order line, so what is invoiced always matches what was
 * ordered and delivered.
 *
 * Tax follows PMK 131/2024 like the SO: DPP nilai lain = 11/12 × DPP, VAT = 12%
 * of that (≈ 11% effective), total = DPP + VAT. Numbers are frozen onto the
 * document. Posting marks the covered DOs INVOICED.
 */
class SalesInvoiceController extends Controller
{
    private array $with = ['cus', 'user', 'detail.item'];

    public function index(Request $request)
    {
        $rows = sls_inv_main::query()
            ->with('cus')->withCount('detail')
            ->when($request->query('q'), fn ($q, $s) => $q->where('code', 'like', "%{$s}%")
                ->orWhereHas('cus', fn ($w) => $w->where('company_n', 'like', "%{$s}%")))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        return ApiResponse::item(sls_inv_main::with($this->with)->findOrFail($id));
    }

    /** DOs of a customer that are shipped/received but not yet invoiced. */
    public function openDos(int $cusId)
    {
        $dos = sls_do_main::with(['detail.item', 'detail.soDetail'])
            ->whereHas('so', fn ($q) => $q->where('cus_id', $cusId))
            ->whereIn('status', ['SHIPPED', 'RECEIVED'])
            ->orderBy('id')->get();

        $invoiced = $this->invoicedDoDetailIds();

        $rows = $dos->map(function ($do) use ($invoiced) {
            $lines = $do->detail
                ->filter(fn ($d) => ! $invoiced->contains($d->id))
                ->map(fn ($d) => [
                    'do_detail_id' => $d->id,
                    'item_id' => $d->item_id,
                    'item_code' => $d->item?->code,
                    'part_name' => $d->item?->part_name,
                    'qty' => (int) $d->qty,
                    'price' => (float) ($d->soDetail?->price ?? 0),
                ])->values();

            return $lines->isEmpty() ? null : [
                'do_id' => $do->id, 'do_code' => $do->code, 'do_date' => $do->date,
                'lines' => $lines,
            ];
        })->filter()->values();

        return ApiResponse::collection($rows);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'cus_id' => ['required', 'integer', 'exists:m_contacts,id'],
            'tax_inv_no' => ['nullable', 'string', 'max:30'],
            'due_date' => ['nullable', 'date'],
            'do_detail_ids' => ['required', 'array', 'min:1'],
            'do_detail_ids.*' => ['required', 'integer', 'exists:sls_do_detail,id'],
        ]);

        $lines = $this->resolveLines($data['cus_id'], $data['do_detail_ids']);
        $inv = DB::transaction(function () use ($data, $lines, $request) {
            $dpp = round($lines->sum('amount'), 2);
            $tax = $this->ppnTax();
            $nilaiLain = round($dpp * $tax['factor'], 2);
            $vat = round($nilaiLain * $tax['rate'] / 100, 2);

            $inv = sls_inv_main::create([
                'code' => (new NumberingService)->next('SINV', 'SI'),
                'date' => $data['date'],
                'cus_id' => $data['cus_id'],
                'dpp' => $dpp,
                'dpp_nilai_lain' => $nilaiLain,
                'vat' => $vat,
                'total' => round($dpp + $vat, 2),
                'tax_inv_no' => $data['tax_inv_no'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'user_id' => $request->user()->id,
                'status' => 'DRAFT',
            ]);
            foreach ($lines as $l) {
                sls_inv_detail::create([
                    'main_id' => $inv->id,
                    'do_detail_id' => $l['do_detail_id'],
                    'item_id' => $l['item_id'],
                    'qty' => $l['qty'],
                    'price' => $l['price'],
                    'amount' => $l['amount'],
                ]);
            }
            AuditLogger::record($request, "Create Sales Invoice {$inv->code}", $inv->code);

            return $inv;
        });

        return ApiResponse::item($inv->load($this->with), 201);
    }

    public function destroy(Request $request, int $id)
    {
        $inv = sls_inv_main::findOrFail($id);
        if ($inv->status !== 'DRAFT') {
            throw BizException::make('SINV_LOCKED', 'Invoice yang sudah di-posting tidak dapat dihapus.');
        }
        DB::transaction(function () use ($inv, $request) {
            $inv->detail()->delete();
            $inv->delete();
            AuditLogger::record($request, "Delete Sales Invoice {$inv->code}", $inv->code);
        });

        return ApiResponse::item(['message' => 'Invoice dihapus.']);
    }

    /** POST — freeze the invoice and mark the fully-invoiced DOs INVOICED. */
    public function post(Request $request, int $id)
    {
        $inv = sls_inv_main::with('detail')->findOrFail($id);
        if ($inv->status !== 'DRAFT') {
            throw BizException::make('SINV_STATE', 'Invoice harus DRAFT untuk diposting.');
        }

        DB::transaction(function () use ($inv, $request) {
            $inv->update(['status' => 'POSTED']);

            // any DO whose every line is now invoiced becomes INVOICED
            $invoiced = $this->invoicedDoDetailIds();
            $doIds = DB::table('sls_do_detail')
                ->whereIn('id', $inv->detail->pluck('do_detail_id'))
                ->distinct()->pluck('main_id');
            foreach ($doIds as $doId) {
                $allDone = DB::table('sls_do_detail')->where('main_id', $doId)
                    ->pluck('id')->every(fn ($did) => $invoiced->contains($did));
                if ($allDone) {
                    sls_do_main::where('id', $doId)->update(['status' => 'INVOICED']);
                }
            }
            AuditLogger::record($request, "Post Sales Invoice {$inv->code}", $inv->code);
        });

        return ApiResponse::item($inv->load($this->with));
    }

    /* ---------------- helpers ---------------- */

    /** Build invoice lines from DO details, pricing each at its SO line price. */
    private function resolveLines(int $cusId, array $doDetailIds)
    {
        $invoiced = $this->invoicedDoDetailIds();

        $rows = DB::table('sls_do_detail as dd')
            ->join('sls_do_main as dm', 'dm.id', '=', 'dd.main_id')
            ->join('sls_so_main as sm', 'sm.id', '=', 'dm.so_id')
            ->leftJoin('sls_so_detail as sd', 'sd.id', '=', 'dd.so_detail_id')
            ->whereIn('dd.id', $doDetailIds)
            ->get(['dd.id', 'dd.item_id', 'dd.qty', 'sm.cus_id', 'dm.status as do_status', 'sd.price']);

        return $rows->map(function ($r) use ($cusId, $invoiced) {
            if ((int) $r->cus_id !== $cusId) {
                throw BizException::make('SINV_CUS', 'Semua DO harus milik customer yang sama.');
            }
            if (! in_array($r->do_status, ['SHIPPED', 'RECEIVED'], true)) {
                throw BizException::make('SINV_DO', 'Hanya DO yang sudah dikirim yang dapat difakturkan.');
            }
            if ($invoiced->contains($r->id)) {
                throw BizException::make('SINV_DUP', 'Sebagian baris DO sudah difakturkan.');
            }
            $price = (float) ($r->price ?? 0);

            return [
                'do_detail_id' => (int) $r->id,
                'item_id' => (int) $r->item_id,
                'qty' => (int) $r->qty,
                'price' => $price,
                'amount' => round($price * (int) $r->qty, 2),
            ];
        });
    }

    /** DO-detail ids already committed on a POSTED invoice. */
    private function invoicedDoDetailIds()
    {
        return DB::table('sls_inv_detail as id')
            ->join('sls_inv_main as im', 'im.id', '=', 'id.main_id')
            ->where('im.status', 'POSTED')
            ->pluck('id.do_detail_id');
    }

    /** PPN-DN tariff: nilai-lain factor + rate; sane fallback if unseeded. */
    private function ppnTax(): array
    {
        $t = DB::table('m_tax')->where('code', 'PPN-DN')->first();

        return [
            'factor' => $t ? (float) $t->dpp_factor : 11 / 12,
            'rate' => $t ? (float) $t->rate_pct : 12.0,
        ];
    }
}
