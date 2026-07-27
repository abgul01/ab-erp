<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\sub_dn_detail;
use App\Models\sub_dn_main;
use App\Models\sub_gr_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Subcontract (Fase 2 tail). Against a SUBCONT purchase order, goods are sent to
 * the vendor on a Delivery Note (sub_dn) and received back on a subcont Goods
 * Receipt (sub_gr, qty ok/ng). Sent qty per PO line is capped at what was
 * ordered; a DN closes once everything it sent has come back.
 */
class SubcontController extends Controller
{
    /* ---------------- subcont PO picker ---------------- */

    /** Open SUBCONT POs with per-line ordered vs already-sent qty. */
    public function pos()
    {
        $pos = DB::table('prc_po_main as m')
            ->leftJoin('m_contacts as v', 'v.id', '=', 'm.ven_id')
            ->where('m.po_type', 'SUBCONT')
            ->whereIn('m.status', ['OPEN', 'INPROGRESS'])
            ->orderByDesc('m.id')
            ->get(['m.id', 'm.code', 'm.ven_id', 'v.company_n as vendor']);

        return ApiResponse::collection($pos->map(function ($po) {
            $lines = DB::table('prc_po_detail as d')
                ->join('m_item as i', 'i.id', '=', 'd.item_id')
                ->where('d.main_id', $po->id)
                ->get(['d.item_id', 'i.code as item_code', 'i.part_name', 'd.qty']);

            return [
                'id' => $po->id, 'code' => $po->code, 'ven_id' => $po->ven_id, 'vendor' => $po->vendor,
                'lines' => $lines->map(fn ($l) => [
                    'item_id' => $l->item_id, 'item_code' => $l->item_code, 'part_name' => $l->part_name,
                    'qty_order' => (int) $l->qty,
                    'sent' => $this->sentQty($po->id, $l->item_id),
                ])->values(),
            ];
        }));
    }

    /* ---------------- Delivery Note (send to vendor) ---------------- */

    public function dnIndex(Request $request)
    {
        $rows = sub_dn_main::query()->with(['po', 'ven'])->withCount('detail')
            ->when($request->query('q'), fn ($q, $s) => $q->where('code', 'like', "%{$s}%"))
            ->orderByDesc('id')->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function dnShow(int $id)
    {
        return ApiResponse::item(sub_dn_main::with(['po', 'ven', 'detail.item'])->findOrFail($id));
    }

    public function dnStore(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'po_id' => ['required', 'integer', 'exists:prc_po_main,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:m_item,id'],
            'lines.*.wo_id' => ['nullable', 'integer'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.serial_id' => ['nullable', 'string', 'max:50'],
            'lines.*.pallet_code' => ['nullable', 'string', 'max:50'],
        ]);
        $po = DB::table('prc_po_main')->where('id', $data['po_id'])->first();
        if (! $po || $po->po_type !== 'SUBCONT') {
            throw BizException::make('SUB_PO', 'PO ini bukan tipe SUBCONT.');
        }
        $this->assertSendable($data['po_id'], $data['lines']);

        $dn = DB::transaction(function () use ($data, $po, $request) {
            $dn = sub_dn_main::create([
                'code' => (new NumberingService)->next('SDN', 'SDN'),
                'date' => $data['date'],
                'po_id' => $data['po_id'],
                'ven_id' => $po->ven_id,
                'user_id' => $request->user()->id,
                'status' => 'DRAFT',
            ]);
            foreach ($data['lines'] as $l) {
                sub_dn_detail::create([
                    'main_id' => $dn->id, 'wo_id' => $l['wo_id'] ?? null,
                    'item_id' => $l['item_id'], 'qty' => $l['qty'],
                    'serial_id' => $l['serial_id'] ?? null, 'pallet_code' => $l['pallet_code'] ?? null,
                ]);
            }
            AuditLogger::record($request, "Create Subcont DN {$dn->code}", $dn->code);

            return $dn;
        });

        return ApiResponse::item($dn->load(['po', 'ven', 'detail.item']), 201);
    }

    public function dnSend(Request $request, int $id)
    {
        $dn = sub_dn_main::findOrFail($id);
        if ($dn->status !== 'DRAFT') {
            throw BizException::make('SUB_DN_STATE', 'Hanya DN DRAFT yang dapat dikirim.');
        }
        $dn->update(['status' => 'SENT']);
        AuditLogger::record($request, "Send Subcont DN {$dn->code}", $dn->code);

        return ApiResponse::item($dn->load(['po', 'ven', 'detail.item']));
    }

    public function dnDestroy(Request $request, int $id)
    {
        $dn = sub_dn_main::findOrFail($id);
        if ($dn->status !== 'DRAFT') {
            throw BizException::make('SUB_DN_LOCKED', 'DN yang sudah dikirim tidak dapat dihapus.');
        }
        DB::transaction(function () use ($dn, $request) {
            $dn->detail()->delete();
            $dn->delete();
            AuditLogger::record($request, "Delete Subcont DN {$dn->code}", $dn->code);
        });

        return ApiResponse::item(['message' => 'DN subcont dihapus.']);
    }

    /* ---------------- Goods Receipt (back from vendor) ---------------- */

    /** DNs already sent that still have qty outstanding to receive. */
    public function openDns()
    {
        $rows = sub_dn_main::with(['po', 'ven', 'detail'])
            ->whereIn('status', ['SENT', 'PARTIAL'])->orderByDesc('id')->get();

        return ApiResponse::collection($rows->map(fn ($dn) => [
            'id' => $dn->id, 'code' => $dn->code, 'po_id' => $dn->po_id,
            'po_code' => $dn->po?->code, 'vendor' => $dn->ven?->company_n,
            'sent' => (int) $dn->detail->sum('qty'),
            'received' => $this->receivedQty($dn->id),
            'outstanding' => max(0, (int) $dn->detail->sum('qty') - $this->receivedQty($dn->id)),
        ])->filter(fn ($r) => $r['outstanding'] > 0)->values());
    }

    public function grIndex(Request $request)
    {
        $rows = sub_gr_main::query()->with(['po', 'dn'])
            ->when($request->query('q'), fn ($q, $s) => $q->where('code', 'like', "%{$s}%"))
            ->orderByDesc('id')->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function grShow(int $id)
    {
        return ApiResponse::item(sub_gr_main::with(['po', 'dn'])->findOrFail($id));
    }

    public function grStore(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'dn_id' => ['required', 'integer', 'exists:sub_dn_main,id'],
            'ven_dn_no' => ['nullable', 'string', 'max:50'],
            'qty_ok' => ['required', 'integer', 'min:0'],
            'qty_ng' => ['nullable', 'integer', 'min:0'],
        ]);
        $dn = sub_dn_main::with('detail')->findOrFail($data['dn_id']);
        $recv = (int) $data['qty_ok'] + (int) ($data['qty_ng'] ?? 0);
        if ($recv < 1) {
            throw BizException::make('SUB_GR_QTY', 'Qty diterima tidak boleh nol.');
        }
        $outstanding = (int) $dn->detail->sum('qty') - $this->receivedQty($dn->id);
        if ($recv > $outstanding) {
            throw BizException::make('SUB_GR_OVER', "Qty diterima melebihi sisa DN ({$outstanding}).");
        }

        $gr = DB::transaction(function () use ($data, $dn, $recv, $request) {
            $gr = sub_gr_main::create([
                'code' => (new NumberingService)->next('SGR', 'SGR'),
                'date' => $data['date'],
                'po_id' => $dn->po_id,
                'dn_id' => $dn->id,
                'ven_dn_no' => $data['ven_dn_no'] ?? null,
                'qty_ok' => (int) $data['qty_ok'],
                'qty_ng' => (int) ($data['qty_ng'] ?? 0),
                'user_id' => $request->user()->id,
                'status' => 'POSTED',
            ]);
            // close the DN once everything sent has come back
            $totalRecv = $this->receivedQty($dn->id);
            $dn->update(['status' => $totalRecv >= (int) $dn->detail->sum('qty') ? 'COMPLETED' : 'PARTIAL']);
            AuditLogger::record($request, "Subcont GR {$gr->code} (DN {$dn->code})", $gr->code);

            return $gr;
        });

        return ApiResponse::item($gr->load(['po', 'dn']));
    }

    public function grDestroy(Request $request, int $id)
    {
        $gr = sub_gr_main::findOrFail($id);
        DB::transaction(function () use ($gr, $request) {
            $dnId = $gr->dn_id;
            $gr->delete();
            if ($dnId) {
                $dn = sub_dn_main::with('detail')->find($dnId);
                $dn?->update(['status' => $this->receivedQty($dnId) > 0 ? 'PARTIAL' : 'SENT']);
            }
            AuditLogger::record($request, "Delete Subcont GR {$gr->code}", $gr->code);
        });

        return ApiResponse::item(['message' => 'GR subcont dihapus.']);
    }

    /* ---------------- helpers ---------------- */

    /** Qty of a PO item already sent out across all its DNs. */
    private function sentQty(int $poId, int $itemId): int
    {
        return (int) sub_dn_detail::whereIn('main_id', sub_dn_main::where('po_id', $poId)->pluck('id'))
            ->where('item_id', $itemId)->sum('qty');
    }

    /** Qty (ok+ng) received back for a DN across all its subcont GRs. */
    private function receivedQty(int $dnId): int
    {
        return (int) sub_gr_main::where('dn_id', $dnId)->selectRaw('COALESCE(SUM(qty_ok + qty_ng),0) as q')->value('q');
    }

    /** Each DN line's qty must fit within the PO order less what was already sent. */
    private function assertSendable(int $poId, array $lines): void
    {
        $ordered = DB::table('prc_po_detail')->where('main_id', $poId)
            ->groupBy('item_id')->selectRaw('item_id, SUM(qty) as q')->pluck('q', 'item_id');

        $byItem = [];
        foreach ($lines as $i => $l) {
            if (! isset($ordered[$l['item_id']])) {
                throw BizException::make('SUB_ITEM', 'Baris #' . ($i + 1) . ': item tidak ada pada PO subcont ini.');
            }
            $byItem[$l['item_id']] = ($byItem[$l['item_id']] ?? 0) + (int) $l['qty'];
        }
        foreach ($byItem as $itemId => $qty) {
            $remain = (int) $ordered[$itemId] - $this->sentQty($poId, $itemId);
            if ($qty > $remain) {
                throw BizException::make('SUB_OVER', "Qty kirim item #{$itemId} melebihi sisa PO ({$remain}).");
            }
        }
    }
}
