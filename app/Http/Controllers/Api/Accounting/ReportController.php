<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\FinancialReportService;
use Illuminate\Http\Request;

/**
 * Financial statements: balance sheet, income statement, AP/AR ageing.
 *
 * PRD §4.14
 */
class ReportController extends Controller
{
    public function __construct(private FinancialReportService $svc) {}

    public function balanceSheet(Request $request)
    {
        $data = $request->validate(['period' => ['nullable', 'regex:/^\d{6}$/']]);

        return ApiResponse::item($this->svc->balanceSheet($data['period'] ?? now()->format('Ym')));
    }

    public function incomeStatement(Request $request)
    {
        $data = $request->validate([
            'period' => ['nullable', 'regex:/^\d{6}$/'],
            'from_period' => ['nullable', 'regex:/^\d{6}$/'],
        ]);

        return ApiResponse::item($this->svc->incomeStatement(
            $data['period'] ?? now()->format('Ym'),
            $data['from_period'] ?? null,
        ));
    }

    public function apAging(Request $request)
    {
        $data = $request->validate(['as_of' => ['nullable', 'date']]);

        return ApiResponse::item($this->svc->apAging($data['as_of'] ?? null));
    }

    public function arAging(Request $request)
    {
        $data = $request->validate(['as_of' => ['nullable', 'date']]);

        return ApiResponse::item($this->svc->arAging($data['as_of'] ?? null));
    }
}
