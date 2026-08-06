<?php

namespace App\Http\Middleware;

use App\Exceptions\BizException;
use App\Models\acc_period;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;

/**
 * Rejects a posting whose accounting period is no longer open.
 *
 * The period is taken from the document's own date rather than from today's,
 * because back-dating is exactly the case this guard exists for: a receipt
 * entered on the 3rd of the new month for the 28th of a closed one must fail.
 * A request that carries neither is treated as posting to the current month.
 *
 * A period with no row at all is open — periods are opened explicitly, and
 * refusing everything until someone creates the row would block a fresh install.
 *
 * LLD §5.1 — answers 423 Locked so a client can tell this apart from validation.
 */
class EnsurePeriodOpen
{
    private const CLOSED = ['CLOSED', 'LOCKED', 'FINAL'];

    public function handle(Request $request, Closure $next)
    {
        $period = $this->periodOf($request);
        $status = acc_period::where('period', $period)->value('status');

        if (in_array($status, self::CLOSED, true)) {
            throw BizException::make(
                'PERIOD_LOCKED',
                "Periode {$period} sudah ditutup (status: {$status}). Dokumen tidak bisa diposting ke periode ini.",
                423,
            );
        }

        return $next($request);
    }

    /** YYYYMM from an explicit period, else the document date, else this month. */
    private function periodOf(Request $request): string
    {
        if ($p = $request->input('period')) {
            return substr(preg_replace('/\D/', '', (string) $p), 0, 6);
        }

        foreach (['date', 'doc_date', 'trx_date', 'posting_date'] as $field) {
            if ($d = $request->input($field)) {
                try {
                    return Carbon::parse($d)->format('Ym');
                } catch (\Throwable) {
                    // Not a date we can read; validation downstream will say so.
                }
            }
        }

        return now()->format('Ym');
    }
}
