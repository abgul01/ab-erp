<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Accounting\ApPaymentController;
use App\Http\Controllers\Api\Accounting\ArReceiptController;
use App\Http\Controllers\Api\Accounting\CoaController;
use App\Http\Controllers\Api\Accounting\JournalController;
use App\Http\Controllers\Api\Accounting\PeriodController;
use App\Http\Controllers\Api\Engineering\ItemController;
use App\Http\Controllers\Api\Engineering\ProcessMainController;
use App\Http\Controllers\Api\Engineering\RouteTimeController;
use App\Http\Controllers\Api\Procurement\CostController;
use App\Http\Controllers\Api\Procurement\GrController;
use App\Http\Controllers\Api\Procurement\InvoiceController;
use App\Http\Controllers\Api\Procurement\RejectController;
use App\Http\Controllers\Api\Procurement\PoController;
use App\Http\Controllers\Api\Procurement\PrController;
use App\Http\Controllers\Api\Procurement\QuotaController;
use App\Http\Controllers\Api\Procurement\SubcontController;
use App\Http\Controllers\Api\Wms\FgIncomingController;
use App\Http\Controllers\Api\Wms\FgOutgoingController;
use App\Http\Controllers\Api\Wms\IncomingController;
use App\Http\Controllers\Api\Wms\OutgoingController;
use App\Http\Controllers\Api\Wms\RackController;
use App\Http\Controllers\Api\Wms\RemainingController;
use App\Http\Controllers\Api\Wms\StockRmController;
use App\Http\Controllers\Api\Sales\DoController;
use App\Http\Controllers\Api\Sales\ForecastController;
use App\Http\Controllers\Api\Sales\PricelistController;
use App\Http\Controllers\Api\Sales\SalesInvoiceController;
use App\Http\Controllers\Api\Sales\SalesReturnController;
use App\Http\Controllers\Api\Sales\SoController;
use App\Http\Controllers\Api\Production\MppController;
use App\Http\Controllers\Api\Production\AbnormalController;
use App\Http\Controllers\Api\Production\CuttingController;
use App\Http\Controllers\Api\Production\MesReportController;
use App\Http\Controllers\Api\Production\MpsController;
use App\Http\Controllers\Api\Production\MrpController;
use App\Http\Controllers\Api\Production\ProcessingController;
use App\Http\Controllers\Api\Production\MpsRescheduleController;
use App\Http\Controllers\Api\Production\WoController;
use App\Http\Controllers\Api\Costing\AssetCategoryController;
use App\Http\Controllers\Api\Costing\AssetController;
use App\Http\Controllers\Api\Costing\CogmController;
use App\Http\Controllers\Api\Costing\CostRateController;
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

/**
 * Register standard CRUD routes for a resource, guarded by per-action permissions
 * whose menu link equals the resource path segment. Defined up-front (and guarded)
 * so re-including this file on a fresh app boot per test is safe.
 */
if (! function_exists('crudRoutes')) {
    function crudRoutes(string $path, string $controller): void
    {
        Route::get($path, [$controller, 'index'])->middleware("perm:{$path},view");
        Route::get("{$path}/{id}", [$controller, 'show'])->whereNumber('id')->middleware("perm:{$path},view");
        Route::post($path, [$controller, 'store'])->middleware("perm:{$path},create");
        Route::put("{$path}/{id}", [$controller, 'update'])->whereNumber('id')->middleware("perm:{$path},edit");
        Route::delete("{$path}/{id}", [$controller, 'destroy'])->whereNumber('id')->middleware("perm:{$path},delete");
    }
}

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
        crudRoutes('process-mains', ProcessMainController::class);
        Route::get('route-times/item-procs/{itemId}', [RouteTimeController::class, 'itemProcs'])->whereNumber('itemId')->middleware('perm:route-times,view');
        crudRoutes('route-times', RouteTimeController::class);

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

        // ---- Subcontract (Fase 2 tail): PO SUBCONT → kirim (DN) → terima (GR) ----
        Route::get('subcont/pos', [SubcontController::class, 'pos'])->middleware('perm:subcont-dn,view');
        Route::get('subcont/dn', [SubcontController::class, 'dnIndex'])->middleware('perm:subcont-dn,view');
        Route::get('subcont/dn/{id}', [SubcontController::class, 'dnShow'])->whereNumber('id')->middleware('perm:subcont-dn,view');
        Route::post('subcont/dn', [SubcontController::class, 'dnStore'])->middleware('perm:subcont-dn,create');
        Route::post('subcont/dn/{id}/send', [SubcontController::class, 'dnSend'])->whereNumber('id')->middleware('perm:subcont-dn,edit');
        Route::delete('subcont/dn/{id}', [SubcontController::class, 'dnDestroy'])->whereNumber('id')->middleware('perm:subcont-dn,delete');
        Route::get('subcont/open-dns', [SubcontController::class, 'openDns'])->middleware('perm:subcont-gr,view');
        Route::get('subcont/gr', [SubcontController::class, 'grIndex'])->middleware('perm:subcont-gr,view');
        Route::get('subcont/gr/{id}', [SubcontController::class, 'grShow'])->whereNumber('id')->middleware('perm:subcont-gr,view');
        Route::post('subcont/gr', [SubcontController::class, 'grStore'])->middleware('perm:subcont-gr,create');
        Route::delete('subcont/gr/{id}', [SubcontController::class, 'grDestroy'])->whereNumber('id')->middleware('perm:subcont-gr,delete');

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
        Route::get('outgoing-rm/wo/{code}', [OutgoingController::class, 'checkWo'])->middleware('perm:outgoing-rm,view');
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

        // ---- WMS FG: receive finished goods from the floor ----
        Route::get('incoming-fg/available', [FgIncomingController::class, 'available'])->middleware('perm:incoming-fg,view');
        Route::get('incoming-fg', [FgIncomingController::class, 'index'])->middleware('perm:incoming-fg,view');
        Route::post('incoming-fg/start', [FgIncomingController::class, 'start'])->middleware('perm:incoming-fg,create');
        Route::post('incoming-fg/{id}/scan', [FgIncomingController::class, 'scan'])->whereNumber('id')->middleware('perm:incoming-fg,create');
        Route::post('incoming-fg/{id}/finish', [FgIncomingController::class, 'finish'])->whereNumber('id')->middleware('perm:incoming-fg,create');
        Route::delete('incoming-fg/det/{detId}', [FgIncomingController::class, 'removeDet'])->whereNumber('detId')->middleware('perm:incoming-fg,create');
        Route::get('incoming-fg/{id}', [FgIncomingController::class, 'show'])->whereNumber('id')->middleware('perm:incoming-fg,view');
        Route::post('incoming-fg', [FgIncomingController::class, 'store'])->middleware('perm:incoming-fg,create');
        Route::delete('incoming-fg/{id}', [FgIncomingController::class, 'destroy'])->whereNumber('id')->middleware('perm:incoming-fg,delete');
        // FG Outgoing: scan-based shipment against a DRAFT DO
        Route::get('outgoing-fg/open-dos', [FgOutgoingController::class, 'openDos'])->middleware('perm:outgoing-fg,view');
        Route::post('outgoing-fg/check-sj', [FgOutgoingController::class, 'checkSj'])->middleware('perm:outgoing-fg,view');
        Route::get('outgoing-fg', [FgOutgoingController::class, 'index'])->middleware('perm:outgoing-fg,view');
        Route::post('outgoing-fg/start', [FgOutgoingController::class, 'start'])->middleware('perm:outgoing-fg,create');
        Route::post('outgoing-fg/{id}/scan', [FgOutgoingController::class, 'scan'])->whereNumber('id')->middleware('perm:outgoing-fg,create');
        Route::post('outgoing-fg/{id}/finish', [FgOutgoingController::class, 'finish'])->whereNumber('id')->middleware('perm:outgoing-fg,create');
        Route::delete('outgoing-fg/det/{detId}', [FgOutgoingController::class, 'removeDet'])->whereNumber('detId')->middleware('perm:outgoing-fg,create');
        Route::get('outgoing-fg/{id}', [FgOutgoingController::class, 'show'])->whereNumber('id')->middleware('perm:outgoing-fg,view');
        Route::delete('outgoing-fg/{id}', [FgOutgoingController::class, 'destroy'])->whereNumber('id')->middleware('perm:outgoing-fg,delete');

        // FG on-hand summary
        Route::get('stock-fg', [FgIncomingController::class, 'stockSummary'])->middleware('perm:stock-fg,view');

        // ---- Order Management (Fase 5): Forecast + Sales Order ----
        crudRoutes('forecasts', ForecastController::class);
        Route::get('pricelists/lookup', [PricelistController::class, 'lookup'])->middleware('perm:sales-orders,view');
        crudRoutes('pricelists', PricelistController::class);
        Route::get('sales-orders/customer-items/{cusId}', [SoController::class, 'customerItems'])->whereNumber('cusId')->middleware('perm:sales-orders,view');
        crudRoutes('sales-orders', SoController::class);
        Route::post('sales-orders/{id}/approve', [SoController::class, 'approve'])->whereNumber('id')->middleware('perm:sales-orders,edit');
        Route::post('sales-orders/{id}/close', [SoController::class, 'close'])->whereNumber('id')->middleware('perm:sales-orders,edit');
        Route::post('sales-orders/{id}/cancel', [SoController::class, 'cancel'])->whereNumber('id')->middleware('perm:sales-orders,edit');

        // Delivery Order: ship an approved SO from FG stock
        Route::get('delivery-orders/open-sos', [DoController::class, 'openSos'])->middleware('perm:delivery-orders,view');
        Route::get('delivery-orders/so-lines/{soId}', [DoController::class, 'soLines'])->whereNumber('soId')->middleware('perm:delivery-orders,view');
        Route::get('delivery-orders/{id}/ship-info', [DoController::class, 'shipInfo'])->whereNumber('id')->middleware('perm:delivery-orders,view');
        crudRoutes('delivery-orders', DoController::class);
        Route::post('delivery-orders/{id}/ship', [DoController::class, 'ship'])->whereNumber('id')->middleware('perm:delivery-orders,edit');
        Route::post('delivery-orders/{id}/receive', [DoController::class, 'receive'])->whereNumber('id')->middleware('perm:delivery-orders,edit');

        // Sales Return: goods back from a shipped DO
        Route::get('sales-returns/shipped-dos', [SalesReturnController::class, 'shippedDos'])->middleware('perm:sales-returns,view');
        Route::get('sales-returns', [SalesReturnController::class, 'index'])->middleware('perm:sales-returns,view');
        Route::get('sales-returns/{id}', [SalesReturnController::class, 'show'])->whereNumber('id')->middleware('perm:sales-returns,view');
        Route::post('sales-returns', [SalesReturnController::class, 'store'])->middleware('perm:sales-returns,create');
        Route::delete('sales-returns/{id}', [SalesReturnController::class, 'destroy'])->whereNumber('id')->middleware('perm:sales-returns,delete');
        Route::post('sales-returns/{id}/post', [SalesReturnController::class, 'post'])->whereNumber('id')->middleware('perm:sales-returns,edit');

        // Sales Invoice (AR): bill shipped DOs (create-then-post; no edit)
        Route::get('sales-invoices/open-dos/{cusId}', [SalesInvoiceController::class, 'openDos'])->whereNumber('cusId')->middleware('perm:sales-invoices,view');
        Route::get('sales-invoices', [SalesInvoiceController::class, 'index'])->middleware('perm:sales-invoices,view');
        Route::get('sales-invoices/{id}', [SalesInvoiceController::class, 'show'])->whereNumber('id')->middleware('perm:sales-invoices,view');
        Route::post('sales-invoices', [SalesInvoiceController::class, 'store'])->middleware('perm:sales-invoices,create');
        Route::delete('sales-invoices/{id}', [SalesInvoiceController::class, 'destroy'])->whereNumber('id')->middleware('perm:sales-invoices,delete');
        Route::post('sales-invoices/{id}/post', [SalesInvoiceController::class, 'post'])->whereNumber('id')->middleware('perm:sales-invoices,edit');

        // ---- Manufacturing: Planning (MPP -> MPS) + Work Order (Fase 3/4) ----
        Route::post('mpp/generate', [MppController::class, 'generate'])->middleware('perm:mpp,create');
        crudRoutes('mpp', MppController::class);
        Route::post('mpp/{id}/approve', [MppController::class, 'approve'])->whereNumber('id')->middleware('perm:mpp,edit');
        // MRP: explode approved MPP into net RM/FG requirements
        Route::get('mrp', [MrpController::class, 'index'])->middleware('perm:mrp,view');
        Route::get('mrp/{id}', [MrpController::class, 'show'])->whereNumber('id')->middleware('perm:mrp,view');
        Route::post('mrp/run', [MrpController::class, 'run'])->middleware('perm:mrp,create');
        Route::delete('mrp/{id}', [MrpController::class, 'destroy'])->whereNumber('id')->middleware('perm:mrp,delete');

        Route::post('mps/generate', [MpsController::class, 'generate'])->middleware('perm:mps,create');
        crudRoutes('mps', MpsController::class);
        Route::post('mps/{id}/approve', [MpsController::class, 'approve'])->whereNumber('id')->middleware('perm:mps,edit');

        // Reschedule request → approval (maker-checker) for locked MPS lots
        Route::get('mps-approvals', [MpsRescheduleController::class, 'index'])->middleware('perm:mps,view');
        Route::post('mps-approvals', [MpsRescheduleController::class, 'store'])->middleware('perm:mps,edit'); // requester
        Route::post('mps-approvals/{id}/cancel', [MpsRescheduleController::class, 'cancel'])->whereNumber('id')->middleware('perm:mps,edit');
        Route::post('mps-approvals/{id}/approve', [MpsRescheduleController::class, 'approve'])->whereNumber('id')->middleware('perm:mps-approvals,edit'); // approver
        Route::post('mps-approvals/{id}/reject', [MpsRescheduleController::class, 'reject'])->whereNumber('id')->middleware('perm:mps-approvals,edit');

        // ---- MES: Cutting Transaction (original tr_cut_* schema) ----
        Route::get('mes/cutting', [CuttingController::class, 'index'])->middleware('perm:mes-cutting,view');
        Route::post('mes/cutting/check', [CuttingController::class, 'checkDenpyou'])->middleware('perm:mes-cutting,view');
        Route::get('mes/cutting/wip/{code}', [CuttingController::class, 'wip'])->middleware('perm:mes-cutting,view');
        Route::post('mes/cutting/start', [CuttingController::class, 'start'])->middleware('perm:mes-cutting,create');
        Route::get('mes/cutting/{id}/view', [CuttingController::class, 'view'])->whereNumber('id')->middleware('perm:mes-cutting,view');
        Route::get('mes/cutting/{id}', [CuttingController::class, 'show'])->whereNumber('id')->middleware('perm:mes-cutting,view');
        Route::post('mes/cutting/{id}/machine', [CuttingController::class, 'addMachine'])->whereNumber('id')->middleware('perm:mes-cutting,create');
        Route::post('mes/cutting/{id}/finish', [CuttingController::class, 'finish'])->whereNumber('id')->middleware('perm:mes-cutting,edit');
        Route::delete('mes/cutting/machine/{detailId}', [CuttingController::class, 'removeMachine'])->whereNumber('detailId')->middleware('perm:mes-cutting,edit');
        Route::post('mes/cutting/machine/{detailId}/serial', [CuttingController::class, 'addSerial'])->whereNumber('detailId')->middleware('perm:mes-cutting,create');
        Route::post('mes/cutting/machine/{detailId}/serials', [CuttingController::class, 'addSerials'])->whereNumber('detailId')->middleware('perm:mes-cutting,create');
        Route::get('mes/cutting/serial-list/{noDp}', [CuttingController::class, 'serialList'])->middleware('perm:mes-cutting,view');
        Route::get('mes/dt-categories', [CuttingController::class, 'dtCategories'])->middleware('perm:mes-cutting,view');
        Route::get('mes/cutting/{id}/downtimes', [CuttingController::class, 'downtimes'])->whereNumber('id')->middleware('perm:mes-cutting,view');
        Route::post('mes/cutting/downtime/start', [CuttingController::class, 'downtimeStart'])->middleware('perm:mes-cutting,create');
        Route::post('mes/cutting/downtime/{id}/save', [CuttingController::class, 'downtimeSave'])->whereNumber('id')->middleware('perm:mes-cutting,edit');
        Route::post('mes/cutting/abnormal', [CuttingController::class, 'abnormalSave'])->middleware('perm:mes-cutting,create');
        Route::put('mes/cutting/serial/{id}', [CuttingController::class, 'updateSerial'])->whereNumber('id')->middleware('perm:mes-cutting,edit');
        Route::delete('mes/cutting/serial/{id}', [CuttingController::class, 'removeSerial'])->whereNumber('id')->middleware('perm:mes-cutting,edit');

        // ---- MES: Processing Transaction (original tr_pro_* schema) ----
        Route::get('mes/kpl/{code}', [ProcessingController::class, 'kpl'])->middleware('perm:mes-cutting,view');
        Route::get('mes/processing', [ProcessingController::class, 'index'])->middleware('perm:mes-processing,view');
        Route::post('mes/processing/check', [ProcessingController::class, 'checkPallet'])->middleware('perm:mes-processing,view');
        Route::get('mes/processing/wip/{code}', [ProcessingController::class, 'wip'])->middleware('perm:mes-processing,view');
        Route::post('mes/processing/start', [ProcessingController::class, 'start'])->middleware('perm:mes-processing,create');
        Route::get('mes/processing/{id}/view', [ProcessingController::class, 'view'])->whereNumber('id')->middleware('perm:mes-processing,view');
        Route::get('mes/processing/{id}', [ProcessingController::class, 'show'])->whereNumber('id')->middleware('perm:mes-processing,view');
        Route::post('mes/processing/{id}/machine', [ProcessingController::class, 'addMachine'])->whereNumber('id')->middleware('perm:mes-processing,create');
        Route::post('mes/processing/{id}/finish', [ProcessingController::class, 'finish'])->whereNumber('id')->middleware('perm:mes-processing,edit');
        Route::delete('mes/processing/machine/{detailId}', [ProcessingController::class, 'removeMachine'])->whereNumber('detailId')->middleware('perm:mes-processing,edit');
        Route::post('mes/processing/machine/{detailId}/pallet', [ProcessingController::class, 'addPallet'])->whereNumber('detailId')->middleware('perm:mes-processing,create');
        Route::get('mes/processing/{id}/downtimes', [ProcessingController::class, 'downtimes'])->whereNumber('id')->middleware('perm:mes-processing,view');
        Route::get('mes/processing/dt-categories', [ProcessingController::class, 'dtCategories'])->middleware('perm:mes-processing,view');
        Route::post('mes/processing/abnormal', [ProcessingController::class, 'abnormalSave'])->middleware('perm:mes-processing,create');
        Route::post('mes/processing/downtime/start', [ProcessingController::class, 'dtStart'])->middleware('perm:mes-processing,create');
        Route::post('mes/processing/downtime/{id}/save', [ProcessingController::class, 'dtSave'])->whereNumber('id')->middleware('perm:mes-processing,edit');
        Route::put('mes/processing/pallet/{id}', [ProcessingController::class, 'updatePallet'])->whereNumber('id')->middleware('perm:mes-processing,edit');
        Route::delete('mes/processing/pallet/{id}', [ProcessingController::class, 'removePallet'])->whereNumber('id')->middleware('perm:mes-processing,edit');

        Route::get('mes/vs-mps', [MesReportController::class, 'vsMps'])->middleware('perm:mes-report,view');

        // ---- MES: abnormal decision (repaired vs NG, judged after the finding) ----
        Route::get('mes/abnormal', [AbnormalController::class, 'index'])->middleware('perm:mes-abnormal,view');
        Route::post('mes/abnormal/decide', [AbnormalController::class, 'decide'])->middleware('perm:mes-abnormal,edit');

        // ---- Accounting (Fase 7) ----
        Route::get('coa', [CoaController::class, 'index'])->middleware('perm:coa,view');
        Route::post('coa', [CoaController::class, 'store'])->middleware('perm:coa,create');
        Route::put('coa/{id}', [CoaController::class, 'update'])->whereNumber('id')->middleware('perm:coa,edit');
        Route::delete('coa/{id}', [CoaController::class, 'destroy'])->whereNumber('id')->middleware('perm:coa,delete');

        Route::get('acc-periods', [PeriodController::class, 'index'])->middleware('perm:acc-periods,view');
        Route::post('acc-periods', [PeriodController::class, 'store'])->middleware('perm:acc-periods,create');
        Route::post('acc-periods/{id}/status', [PeriodController::class, 'setStatus'])->whereNumber('id')->middleware('perm:acc-periods,edit');

        Route::get('journals/trial-balance', [JournalController::class, 'trialBalance'])->middleware('perm:journals,view');
        Route::get('journals', [JournalController::class, 'index'])->middleware('perm:journals,view');
        Route::get('journals/{id}', [JournalController::class, 'show'])->whereNumber('id')->middleware('perm:journals,view');
        Route::post('journals', [JournalController::class, 'store'])->middleware('perm:journals,create');
        Route::post('journals/generate', [JournalController::class, 'generate'])->middleware('perm:journals,create');
        Route::post('journals/{id}/reverse', [JournalController::class, 'reverse'])->whereNumber('id')->middleware('perm:journals,edit');

        Route::get('ap-payments/open-invoices/{venId}', [ApPaymentController::class, 'openInvoices'])->whereNumber('venId')->middleware('perm:ap-payments,view');
        Route::get('ap-payments', [ApPaymentController::class, 'index'])->middleware('perm:ap-payments,view');
        Route::get('ap-payments/{id}', [ApPaymentController::class, 'show'])->whereNumber('id')->middleware('perm:ap-payments,view');
        Route::post('ap-payments', [ApPaymentController::class, 'store'])->middleware('perm:ap-payments,create');

        Route::get('ar-receipts/open-invoices/{cusId}', [ArReceiptController::class, 'openInvoices'])->whereNumber('cusId')->middleware('perm:ar-receipts,view');
        Route::get('ar-receipts', [ArReceiptController::class, 'index'])->middleware('perm:ar-receipts,view');
        Route::get('ar-receipts/{id}', [ArReceiptController::class, 'show'])->whereNumber('id')->middleware('perm:ar-receipts,view');
        Route::post('ar-receipts', [ArReceiptController::class, 'store'])->middleware('perm:ar-receipts,create');

        // ---- Costing & Asset (Fase 6) ----
        Route::get('cost-rates', [CostRateController::class, 'index'])->middleware('perm:cost-rates,view');
        Route::post('cost-rates', [CostRateController::class, 'store'])->middleware('perm:cost-rates,create');
        Route::put('cost-rates/{id}', [CostRateController::class, 'update'])->whereNumber('id')->middleware('perm:cost-rates,edit');
        Route::delete('cost-rates/{id}', [CostRateController::class, 'destroy'])->whereNumber('id')->middleware('perm:cost-rates,delete');

        Route::get('cogm', [CogmController::class, 'index'])->middleware('perm:cogm,view');
        Route::post('cogm/run', [CogmController::class, 'run'])->middleware('perm:cogm,create');

        Route::get('asset-categs', [AssetCategoryController::class, 'index'])->middleware('perm:asset-categs,view');
        Route::post('asset-categs', [AssetCategoryController::class, 'store'])->middleware('perm:asset-categs,create');
        Route::put('asset-categs/{id}', [AssetCategoryController::class, 'update'])->whereNumber('id')->middleware('perm:asset-categs,edit');
        Route::delete('asset-categs/{id}', [AssetCategoryController::class, 'destroy'])->whereNumber('id')->middleware('perm:asset-categs,delete');

        Route::post('assets/depreciate', [AssetController::class, 'depreciate'])->middleware('perm:assets,create');
        Route::get('assets', [AssetController::class, 'index'])->middleware('perm:assets,view');
        Route::get('assets/{id}', [AssetController::class, 'show'])->whereNumber('id')->middleware('perm:assets,view');
        Route::post('assets', [AssetController::class, 'store'])->middleware('perm:assets,create');
        Route::put('assets/{id}', [AssetController::class, 'update'])->whereNumber('id')->middleware('perm:assets,edit');
        Route::delete('assets/{id}', [AssetController::class, 'destroy'])->whereNumber('id')->middleware('perm:assets,delete');

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
