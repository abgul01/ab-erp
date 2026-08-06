<?php

namespace App\Http\Controllers\Api\Costing;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\InventoryValuationService;
use Illuminate\Http\Request;

/**
 * Inventory valuation (RM / WIP / FG) and margin per product.
 *
 * PRD §7
 */
class ValuationController extends Controller
{
    public function __construct(private InventoryValuationService $svc) {}

    public function valuation()
    {
        return ApiResponse::item($this->svc->valuation());
    }

    public function margin(Request $request)
    {
        $request->validate(['period' => ['nullable', 'regex:/^\d{6}$/']]);

        return ApiResponse::item($this->svc->margin($request->query('period', now()->format('Ym'))));
    }
}
