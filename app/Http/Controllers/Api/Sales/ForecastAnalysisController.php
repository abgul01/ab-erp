<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\ForecastAnalysisService;
use Illuminate\Http\Request;

class ForecastAnalysisController extends Controller
{
    public function __construct(private ForecastAnalysisService $svc) {}

    public function analyze(Request $request)
    {
        $data = $request->validate([
            'period_from' => ['required', 'string', 'regex:/^\d{6}$/'],
            'period_to' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        return ApiResponse::item($this->svc->analyze($data['period_from'], $data['period_to']));
    }
}
