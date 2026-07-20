<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Engineering\ItemController;
use App\Http\Controllers\Api\Procurement\CostController;
use App\Http\Controllers\Api\Procurement\GrController;
use App\Http\Controllers\Api\Procurement\InvoiceController;
use App\Http\Controllers\Api\Procurement\RejectController;
use App\Http\Controllers\Api\Procurement\PoController;
use App\Http\Controllers\Api\Procurement\PrController;
use App\Http\Controllers\Api\Procurement\QuotaController;
use App\Http\Controllers\Api\Wms\IncomingController;
use App\Http\Controllers\Api\Wms\OutgoingController;
use App\Http\Controllers\Api\Wms\RackController;
use App\Http\Controllers\Api\Wms\RemainingController;
use App\Http\Controllers\Api\Wms\StockRmController;
use App\Http\Controllers\Api\Sales\ForecastController;
use App\Http\Controllers\Api\Sales\SoController;
use App\Http\Controllers\Api\Production\MppController;
use App\Http\Controllers\Api\Production\MpsController;
use App\Http\Controllers\Api\Production\WoController;
use App\Http\Controllers\Api\MasterData\CategoryController;
use App\Http\Controllers\Api\MasterData\ContactCategoryController;
use App\Http\Controllers\Api\MasterData\ContactController;
use App\Http\Controllers\Api\MasterData\CurrencyController;
use App\Http\Controllers\Api\MasterData\MachineController;
use App\Http\Controllers\Api\MasterData\MakerController;
use App\Http\Controllers\Api\MasterData\ProcessController;
use App\Http\Controllers\Api\MasterData\TaxController;
use App\Http\Controllers\Api\MasterData\UomController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // ---- Auth (public) ----
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        // ---- General Data Master ----
        crudRoutes('categories', CategoryController::class);
        crudRoutes('uoms', UomController::class);
        crudRoutes('currencies', CurrencyController::class);
        crudRoutes('taxes', TaxController::class);
        crudRoutes('makers', MakerController::class);
        crudRoutes('machines', MachineController::class);
        crudRoutes('contact-categories', ContactCategoryController::class);
        crudRoutes('contacts', ContactController::class);
        crudRoutes('processes', ProcessController::class);

        // ---- Engineering ----
        crudRoutes('items', ItemController::class);

        // ---- Procurement (Fase 2) ----
        // Purchase Requisition
        crudRoutes('pr', PrController::class);
        Route::post('pr/{id}/submit', [PrController::class, 'submit'])->whereNumber('id')->middleware('perm:pr,edit');
        Route::post('pr/{id}/approve', [PrController::class, 'approve'])->whereNumber('id')->middleware('perm:pr,edit');
        Route::post('pr/{id}/reject', [PrController::class, 'reject'])->whereNumber('id')->middleware('perm:pr,edit');

        // Purchase Order
        crudRoutes('po', PoController::class);
        Route::post('po/{id}/approve', [PoController::class, 'approve'])->whereNumber('id')->middleware('perm:po,edit');
        Route::post('po/{id}/close', [PoController::class, 'close'])->whereNumber('id')->middleware('perm:po,edit');
        Route::post('po/{id}/cancel', [PoController::class, 'cancel'])->whereNumber('id')->middleware('perm:po,edit');

        // Goods Receipt (no update: post-on-create, reverse via delete)
        Route::get('grn', [GrController::class, 'index'])->middleware('perm:grn,view');
        Route::get('grn/{id}', [GrController::class, 'show'])->whereNumber('id')->middleware('perm:grn,view');
        Route::post('grn', [GrController::class, 'store'])->middleware('perm:grn,create');
        Route::put('grn/{id}', [GrController::class, 'update'])->whereNumber('id')->middleware('perm:grn,edit');
        Route::delete('grn/{id}', [GrController::class, 'destroy'])->whereNumber('id')->middleware('perm:grn,delete');

        // Import Quota
        crudRoutes('quotas', QuotaController::class);
        Route::get('quotas/{id}/balance', [QuotaController::class, 'balance'])->whereNumber('id')->middleware('perm:quotas,view');

        // Landed Cost sheet
        crudRoutes('landed-costs', CostController::class);
        Route::post('landed-costs/{id}/finalize', [CostController::class, 'finalize'])->whereNumber('id')->middleware('perm:landed-costs,edit');

        // GR Reject / retur vendor
        crudRoutes('gr-rejects', RejectController::class);
        Route::post('gr-rejects/{id}/return', [RejectController::class, 'markReturned'])->whereNumber('id')->middleware('perm:gr-rejects,edit');
        Route::post('gr-rejects/{id}/claim', [RejectController::class, 'markClaimed'])->whereNumber('id')->middleware('perm:gr-rejects,edit');

        // AP Invoice (3-way match)
        crudRoutes('ap-invoices', InvoiceController::class);
        Route::post('ap-invoices/{id}/match', [InvoiceController::class, 'match'])->whereNumber('id')->middleware('perm:ap-invoices,edit');
        Route::post('ap-invoices/{id}/post', [InvoiceController::class, 'post'])->whereNumber('id')->middleware('perm:ap-invoices,edit');

        // ---- WMS RM (Fase 2 tail) ----
        Route::get('shifts', fn () => \App\Support\ApiResponse::collection(\App\Models\m_shift::orderBy('id')->get()));
        crudRoutes('racks', RackController::class);

        // Incoming RM (multi-GR; satu dokumen per GR)
        Route::get('incoming-rm', [IncomingController::class, 'index'])->middleware('perm:incoming-rm,view');
        Route::get('incoming-rm/available', [IncomingController::class, 'available'])->middleware('perm:incoming-rm,view');
        Route::get('incoming-rm/{id}', [IncomingController::class, 'show'])->whereNumber('id')->middleware('perm:incoming-rm,view');
        Route::post('incoming-rm', [IncomingController::class, 'store'])->middleware('perm:incoming-rm,create');
        Route::delete('incoming-rm/{id}', [IncomingController::class, 'destroy'])->whereNumber('id')->middleware('perm:incoming-rm,delete');

        // Outgoing RM (nantinya per Work Order)
        Route::get('outgoing-rm', [OutgoingController::class, 'index'])->middleware('perm:outgoing-rm,view');
        Route::get('outgoing-rm/{id}', [OutgoingController::class, 'show'])->whereNumber('id')->middleware('perm:outgoing-rm,view');
        Route::post('outgoing-rm', [OutgoingController::class, 'store'])->middleware('perm:outgoing-rm,create');
        Route::delete('outgoing-rm/{id}', [OutgoingController::class, 'destroy'])->whereNumber('id')->middleware('perm:outgoing-rm,delete');

        // Remaining / Tankan
        Route::get('remaining-rm', [RemainingController::class, 'index'])->middleware('perm:remaining-rm,view');
        Route::get('remaining-rm/available/{outId}', [RemainingController::class, 'available'])->whereNumber('outId')->middleware('perm:remaining-rm,view');
        Route::get('remaining-rm/{id}', [RemainingController::class, 'show'])->whereNumber('id')->middleware('perm:remaining-rm,view');
        Route::post('remaining-rm', [RemainingController::class, 'store'])->middleware('perm:remaining-rm,create');
        Route::delete('remaining-rm/{id}', [RemainingController::class, 'destroy'])->whereNumber('id')->middleware('perm:remaining-rm,delete');

        Route::get('stock-rm', [StockRmController::class, 'index'])->middleware('perm:stock-rm,view');
        Route::get('stock-rm/summary', [StockRmController::class, 'summary'])->middleware('perm:stock-rm,view');

        // ---- Order Management (Fase 5): Forecast + Sales Order ----
        crudRoutes('forecasts', ForecastController::class);
        Route::get('sales-orders/customer-items/{cusId}', [SoController::class, 'customerItems'])->whereNumber('cusId')->middleware('perm:sales-orders,view');
        crudRoutes('sales-orders', SoController::class);
        Route::post('sales-orders/{id}/approve', [SoController::class, 'approve'])->whereNumber('id')->middleware('perm:sales-orders,edit');
        Route::post('sales-orders/{id}/close', [SoController::class, 'close'])->whereNumber('id')->middleware('perm:sales-orders,edit');
        Route::post('sales-orders/{id}/cancel', [SoController::class, 'cancel'])->whereNumber('id')->middleware('perm:sales-orders,edit');

        // ---- Manufacturing: Planning (MPP -> MPS) + Work Order (Fase 3/4) ----
        Route::post('mpp/generate', [MppController::class, 'generate'])->middleware('perm:mpp,create');
        crudRoutes('mpp', MppController::class);
        Route::post('mpp/{id}/approve', [MppController::class, 'approve'])->whereNumber('id')->middleware('perm:mpp,edit');
        crudRoutes('mps', MpsController::class);
        Route::post('mps/{id}/approve', [MpsController::class, 'approve'])->whereNumber('id')->middleware('perm:mps,edit');

        Route::get('work-orders/fg-info/{fgId}', [WoController::class, 'fgInfo'])->whereNumber('fgId')->middleware('perm:work-orders,view');
        Route::get('work-orders/available-serials', [WoController::class, 'availableSerials'])->middleware('perm:work-orders,view');
        crudRoutes('work-orders', WoController::class);
        Route::post('work-orders/{id}/release', [WoController::class, 'release'])->whereNumber('id')->middleware('perm:work-orders,edit');
        Route::post('work-orders/{id}/close', [WoController::class, 'close'])->whereNumber('id')->middleware('perm:work-orders,edit');
        Route::post('work-orders/{id}/cancel', [WoController::class, 'cancel'])->whereNumber('id')->middleware('perm:work-orders,edit');
    });
});

/**
 * Register standard CRUD routes for a resource, guarded by per-action permissions
 * whose menu link equals the resource path segment.
 */
function crudRoutes(string $path, string $controller): void
{
    Route::get($path, [$controller, 'index'])->middleware("perm:{$path},view");
    Route::get("{$path}/{id}", [$controller, 'show'])->whereNumber('id')->middleware("perm:{$path},view");
    Route::post($path, [$controller, 'store'])->middleware("perm:{$path},create");
    Route::put("{$path}/{id}", [$controller, 'update'])->whereNumber('id')->middleware("perm:{$path},edit");
    Route::delete("{$path}/{id}", [$controller, 'destroy'])->whereNumber('id')->middleware("perm:{$path},delete");
}
