<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\TaxExportService;
use Illuminate\Http\Request;

/**
 * Statutory tax filings: e-Faktur (Coretax) and PPh 23 e-Bupot.
 *
 * PRD §6
 */
class TaxExportController extends Controller
{
    public function __construct(private TaxExportService $svc) {}

    /** @return array{0:string,1:string} */
    private function period(Request $request): array
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        return [$data['from'], $data['to']];
    }

    /** On-screen preview so the period can be checked before it is filed. */
    public function preview(Request $request)
    {
        [$from, $to] = $this->period($request);
        $vat = $this->svc->vatInvoices($from, $to);
        $wht = $this->svc->wht23Invoices($from, $to);

        return ApiResponse::item([
            'period' => compact('from', 'to'),
            'efaktur' => [
                'count' => $vat->count(),
                'dpp' => (float) $vat->sum('dpp'),
                'vat' => (float) $vat->sum('vat'),
            ],
            'ebupot23' => [
                'count' => $wht->count(),
                'dpp' => (float) $wht->sum('dpp'),
                'wht23' => (float) $wht->sum('wht23'),
            ],
        ]);
    }

    public function efaktur(Request $request)
    {
        [$from, $to] = $this->period($request);
        AuditLogger::record($request, "Export e-Faktur {$from}..{$to}");

        return response($this->svc->efakturXml($from, $to), 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"efaktur-{$from}-{$to}.xml\"",
        ]);
    }

    public function ebupot23(Request $request)
    {
        [$from, $to] = $this->period($request);
        AuditLogger::record($request, "Export e-Bupot PPh 23 {$from}..{$to}");

        return response($this->svc->ebupot23Csv($from, $to), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"ebupot23-{$from}-{$to}.csv\"",
        ]);
    }
}
