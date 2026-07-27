<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\acc_ap_pay_det;
use App\Models\acc_ap_pay_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\JournalEngine;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AP Payment — settle vendor (AP) invoices. Posting draws down the payable and
 * cash, journaled as Dr Utang Usaha / Cr Kas. Amount per invoice is capped at
 * its outstanding balance.
 */
class ApPaymentController extends Controller
{
    public function index(Request $request)
    {
        $rows = acc_ap_pay_main::with('ven')->withCount('detail')
            ->when($request->query('q'), fn ($q, $s) => $q->where('code', 'like', "%{$s}%"))
            ->orderByDesc('id')->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        return ApiResponse::item(acc_ap_pay_main::with(['ven', 'detail.invoice'])->findOrFail($id));
    }

    /** Unpaid (or partly paid) AP invoices of a vendor. */
    public function openInvoices(int $venId)
    {
        $rows = DB::table('prc_inv_main')->where('ven_id', $venId)->where('status', 'POSTED')->orderBy('id')->get();

        return ApiResponse::collection($rows->map(function ($inv) {
            $paid = (int) DB::table('acc_ap_pay_det')->where('inv_id', $inv->id)->sum('amount');
            $out = (float) $inv->total - $paid;

            return $out <= 0 ? null : [
                'inv_id' => $inv->id, 'code' => $inv->code, 'inv_no' => $inv->inv_no,
                'date' => $inv->date, 'due_date' => $inv->due_date,
                'total' => (float) $inv->total, 'paid' => $paid, 'outstanding' => round($out, 2),
            ];
        })->filter()->values());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'ven_id' => ['required', 'integer', 'exists:m_contacts,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inv_id' => ['required', 'integer', 'exists:prc_inv_main,id'],
            'lines.*.amount' => ['required', 'numeric', 'min:0.01'],
        ]);
        $this->assertPayable($data['ven_id'], $data['lines']);
        $amount = round(collect($data['lines'])->sum('amount'), 2);

        $pay = DB::transaction(function () use ($data, $amount, $request) {
            $pay = acc_ap_pay_main::create([
                'code' => (new NumberingService)->next('APPAY', 'PAY'),
                'date' => $data['date'], 'ven_id' => $data['ven_id'],
                'amount' => $amount, 'user_id' => $request->user()->id, 'status' => 'POSTED',
            ]);
            foreach ($data['lines'] as $l) {
                acc_ap_pay_det::create(['main_id' => $pay->id, 'inv_id' => $l['inv_id'], 'amount' => round((float) $l['amount'], 2)]);
            }
            JournalEngine::post('AP_PAY', $pay->id, $data['date'], 'PAY', [
                ['coa' => '2100', 'debit' => $amount, 'memo' => "Bayar AP {$pay->code}"],
                ['coa' => '1100', 'credit' => $amount, 'memo' => 'Kas/Bank'],
            ], "Pembayaran AP {$pay->code}", $request->user()->id);
            AuditLogger::record($request, "AP Payment {$pay->code} Rp" . number_format($amount), $pay->code);

            return $pay;
        });

        return ApiResponse::item($pay->load(['ven', 'detail.invoice']), 201);
    }

    private function assertPayable(int $venId, array $lines): void
    {
        foreach ($lines as $i => $l) {
            $inv = DB::table('prc_inv_main')->where('id', $l['inv_id'])->first();
            if (! $inv || (int) $inv->ven_id !== (int) $venId || $inv->status !== 'POSTED') {
                throw BizException::make('PAY_INV', 'Baris #' . ($i + 1) . ': invoice bukan milik vendor ini / belum diposting.');
            }
            $paid = (float) DB::table('acc_ap_pay_det')->where('inv_id', $inv->id)->sum('amount');
            if (round((float) $l['amount'], 2) > round((float) $inv->total - $paid, 2)) {
                throw BizException::make('PAY_OVER', "Baris #" . ($i + 1) . ": jumlah melebihi sisa tagihan (" . number_format((float) $inv->total - $paid, 2) . ").");
            }
        }
    }
}
