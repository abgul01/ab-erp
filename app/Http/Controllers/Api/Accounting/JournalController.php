<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\acc_journal_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\GlPostingService;
use App\Support\JournalEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * General ledger journals: list/view, manual balanced entries, one-click
 * generation of journals from source documents (sales/AP invoices, depreciation),
 * a reversal, and a trial balance.
 */
class JournalController extends Controller
{
    public function index(Request $request)
    {
        $q = acc_journal_main::query()->withCount('detail')
            ->withSum('detail as debit_total', 'debit');
        if ($p = $request->query('period')) {
            $q->where('period', $p);
        }
        if ($t = $request->query('ref_type')) {
            $q->where('ref_type', $t);
        }

        return ApiResponse::paginated($q->orderByDesc('id')->paginate(min(max((int) $request->query('per_page', 20), 1), 200)));
    }

    public function show(int $id)
    {
        $jrn = acc_journal_main::with('detail.coa')->findOrFail($id);

        return ApiResponse::item($jrn);
    }

    /** Manual balanced journal entry. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'descrip' => ['nullable', 'string', 'max:300'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.coa_id' => ['required', 'integer', 'exists:acc_coa,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:200'],
        ]);

        $jrn = JournalEngine::postRaw('MANUAL', null, $data['date'], 'GEN', $data['lines'], $data['descrip'] ?? 'Jurnal manual', $request->user()->id);
        AuditLogger::record($request, "Manual journal {$jrn->code}", $jrn->code);

        return ApiResponse::item($jrn->load('detail.coa'), 201);
    }

    /** Generate journals for all posted documents in the period. */
    public function generate(Request $request)
    {
        $data = $request->validate(['period' => ['required', 'regex:/^\d{6}$/']]);
        $res = (new GlPostingService)->generateForPeriod($data['period'], $request->user()->id);
        AuditLogger::record($request, "Generate GL {$data['period']}: " . json_encode($res));

        return ApiResponse::item(['period' => $data['period']] + $res);
    }

    public function reverse(Request $request, int $id)
    {
        $jrn = acc_journal_main::with('detail')->findOrFail($id);
        if ($jrn->status !== 'POSTED') {
            throw BizException::make('JRN_STATE', 'Hanya jurnal POSTED yang dapat dibalik.');
        }
        $rev = JournalEngine::reverse($jrn, $request->user()->id);
        AuditLogger::record($request, "Reverse journal {$jrn->code} → {$rev->code}", $rev->code);

        return ApiResponse::item($rev->load('detail.coa'));
    }

    /** Trial balance up to and including the period (posted journals only). */
    public function trialBalance(Request $request)
    {
        $period = $request->query('period', now()->format('Ym'));

        $rows = DB::table('acc_journal_det as d')
            ->join('acc_journal_main as m', 'm.id', '=', 'd.main_id')
            ->join('acc_coa as c', 'c.id', '=', 'd.coa_id')
            ->where('m.status', 'POSTED')
            ->where('m.period', '<=', $period)
            ->groupBy('c.id', 'c.code', 'c.name', 'c.acc_group')
            ->orderBy('c.code')
            ->get([
                'c.code', 'c.name', 'c.acc_group',
                DB::raw('SUM(d.debit) as debit'), DB::raw('SUM(d.credit) as credit'),
            ]);

        $out = $rows->map(function ($r) {
            $bal = (float) $r->debit - (float) $r->credit;

            return [
                'code' => $r->code, 'name' => $r->name, 'acc_group' => $r->acc_group,
                'debit' => round((float) $r->debit, 2), 'credit' => round((float) $r->credit, 2),
                // assets/expenses/COGS carry a debit balance; the rest a credit balance
                'balance_debit' => $bal > 0 ? round($bal, 2) : 0,
                'balance_credit' => $bal < 0 ? round(-$bal, 2) : 0,
            ];
        });

        return ApiResponse::item([
            'period' => $period,
            'rows' => $out->values(),
            'total_debit' => round($out->sum('balance_debit'), 2),
            'total_credit' => round($out->sum('balance_credit'), 2),
        ]);
    }
}
