<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\acc_ar_rec_det;
use App\Models\acc_ar_rec_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\JournalEngine;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AR Receipt — collect against customer (sales) invoices. Posting reduces the
 * receivable and increases cash, journaled as Dr Kas / Cr Piutang Usaha. Amount
 * per invoice is capped at its outstanding balance.
 */
class ArReceiptController extends Controller
{
    public function index(Request $request)
    {
        $rows = acc_ar_rec_main::with('cus')->withCount('detail')
            ->when($request->query('q'), fn ($q, $s) => $q->where('code', 'like', "%{$s}%"))
            ->orderByDesc('id')->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        return ApiResponse::item(acc_ar_rec_main::with(['cus', 'detail.invoice'])->findOrFail($id));
    }

    /** Unpaid (or partly paid) sales invoices of a customer. */
    public function openInvoices(int $cusId)
    {
        $rows = DB::table('sls_inv_main')->where('cus_id', $cusId)->where('status', 'POSTED')->orderBy('id')->get();

        return ApiResponse::collection($rows->map(function ($inv) {
            $recv = (int) DB::table('acc_ar_rec_det')->where('inv_id', $inv->id)->sum('amount');
            $out = (float) $inv->total - $recv;

            return $out <= 0 ? null : [
                'inv_id' => $inv->id, 'code' => $inv->code, 'date' => $inv->date, 'due_date' => $inv->due_date,
                'total' => (float) $inv->total, 'received' => $recv, 'outstanding' => round($out, 2),
            ];
        })->filter()->values());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'cus_id' => ['required', 'integer', 'exists:m_contacts,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inv_id' => ['required', 'integer', 'exists:sls_inv_main,id'],
            'lines.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);
        $this->assertReceivable($data['cus_id'], $data['lines']);
        $amount = round(collect($data['lines'])->sum('amount'), 2);

        $rec = DB::transaction(function () use ($data, $amount, $request) {
            $rec = acc_ar_rec_main::create([
                'code' => (new NumberingService)->next('ARREC', 'RCV'),
                'date' => $data['date'], 'cus_id' => $data['cus_id'],
                'amount' => $amount, 'user_id' => $request->user()->id, 'status' => 'POSTED',
            ]);
            foreach ($data['lines'] as $l) {
                acc_ar_rec_det::create(['main_id' => $rec->id, 'inv_id' => $l['inv_id'], 'amount' => round((float) $l['amount'], 2)]);
            }
            JournalEngine::post('AR_REC', $rec->id, $data['date'], 'RCV', [
                ['coa' => '1100', 'debit' => $amount, 'memo' => 'Kas/Bank'],
                ['coa' => '1200', 'credit' => $amount, 'memo' => "Terima AR {$rec->code}"],
            ], "Penerimaan AR {$rec->code}", $request->user()->id);
            AuditLogger::record($request, "AR Receipt {$rec->code} Rp" . number_format($amount), $rec->code);

            return $rec;
        });

        return ApiResponse::item($rec->load(['cus', 'detail.invoice']), 201);
    }

    private function assertReceivable(int $cusId, array $lines): void
    {
        foreach ($lines as $i => $l) {
            $inv = DB::table('sls_inv_main')->where('id', $l['inv_id'])->first();
            if (! $inv || (int) $inv->cus_id !== (int) $cusId || $inv->status !== 'POSTED') {
                throw BizException::make('REC_INV', 'Baris #' . ($i + 1) . ': invoice bukan milik customer ini / belum diposting.');
            }
            $recv = (float) DB::table('acc_ar_rec_det')->where('inv_id', $inv->id)->sum('amount');
            if (round((float) $l['amount'], 2) > round((float) $inv->total - $recv, 2)) {
                throw BizException::make('REC_OVER', "Baris #" . ($i + 1) . ": jumlah melebihi sisa tagihan (" . number_format((float) $inv->total - $recv, 2) . ").");
            }
        }
    }
}
