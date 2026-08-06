<?php

use App\Http\Controllers\Api\Accounting\ApPaymentController;
use App\Http\Controllers\Api\Accounting\ArReceiptController;
use App\Http\Controllers\Api\Accounting\CoaController;
use App\Http\Controllers\Api\Accounting\JournalController;
use App\Http\Controllers\Api\Accounting\PeriodController;
use App\Http\Controllers\Api\Accounting\ReportController;
use App\Http\Controllers\Api\Accounting\TaxExportController;
use App\Http\Controllers\Api\Admin\MenuController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Costing\AssetCategoryController;
use App\Http\Controllers\Api\Costing\AssetController;
use App\Http\Controllers\Api\Costing\CogmController;
use App\Http\Controllers\Api\Costing\CostRateController;
use App\Http\Controllers\Api\Costing\ValuationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Engineering\EcnController;
use App\Http\Controllers\Api\Engineering\ItemController;
use App\Http\Controllers\Api\Engineering\ProcessMainController;
use App\Http\Controllers\Api\Engineering\RouteTimeController;
use App\Http\Controllers\Api\MasterData\BomToolController;
use App\Http\Controllers\Api\MasterData\CategoryController;
use App\Http\Controllers\Api\MasterData\ContactCategoryController;
use App\Http\Controllers\Api\MasterData\ContactController;
use App\Http\Controllers\Api\MasterData\CurrencyController;
use App\Http\Controllers\Api\MasterData\DefectiveController;
use App\Http\Controllers\Api\MasterData\ExchangeRateController;
use App\Http\Controllers\Api\MasterData\HolidayController;
use App\Http\Controllers\Api\MasterData\InspectionParamController;
use App\Http\Controllers\Api\MasterData\MachineController;
use App\Http\Controllers\Api\MasterData\MakerController;
use App\Http\Controllers\Api\MasterData\ProcessController;
use App\Http\Controllers\Api\MasterData\ProductFamilyController;
use App\Http\Controllers\Api\MasterData\ProductionLineController;
use App\Http\Controllers\Api\MasterData\TaxController;
use App\Http\Controllers\Api\MasterData\UomController;
use App\Http\Controllers\Api\MasterData\WorkCalendarController;
use App\Http\Controllers\Api\Mes\MesSyncController;
use App\Http\Controllers\Api\Npd\NpdCostingController;
use App\Http\Controllers\Api\Npd\NpdDocController;
use App\Http\Controllers\Api\Npd\NpdPpapController;
use App\Http\Controllers\Api\Npd\NpdProjectController;
use App\Http\Controllers\Api\Npd\NpdQualityController;
use App\Http\Controllers\Api\Npd\NpdRfqController;
use App\Http\Controllers\Api\Npd\NpdTaskController;
use App\Http\Controllers\Api\Npd\NpdTrialController;
use App\Http\Controllers\Api\Procurement\CostController;
use App\Http\Controllers\Api\Procurement\GrController;
use App\Http\Controllers\Api\Procurement\InvoiceController;
use App\Http\Controllers\Api\Procurement\PoController;
use App\Http\Controllers\Api\Procurement\PoScheduleController;
use App\Http\Controllers\Api\Procurement\PrController;
use App\Http\Controllers\Api\Procurement\QasController;
use App\Http\Controllers\Api\Procurement\QuotaController;
use App\Http\Controllers\Api\Procurement\QuotationController;
use App\Http\Controllers\Api\Procurement\RejectController;
use App\Http\Controllers\Api\Procurement\SubcontController;
use App\Http\Controllers\Api\Procurement\SubcontItemController;
use App\Http\Controllers\Api\Procurement\SubcontPoController;
use App\Http\Controllers\Api\Procurement\SupplierItemController;
use App\Http\Controllers\Api\Production\AbnormalController;
use App\Http\Controllers\Api\Production\CrpController;
use App\Http\Controllers\Api\Production\CuttingController;
use App\Http\Controllers\Api\Production\FcsController;
use App\Http\Controllers\Api\Production\KanbanController;
use App\Http\Controllers\Api\Production\MesReportController;
use App\Http\Controllers\Api\Production\MppController;
use App\Http\Controllers\Api\Production\MpsController;
use App\Http\Controllers\Api\Production\MpsRescheduleController;
use App\Http\Controllers\Api\Production\MrpController;
use App\Http\Controllers\Api\Production\ProcessingController;
use App\Http\Controllers\Api\Production\WoController;
use App\Http\Controllers\Api\Sales\DoController;
use App\Http\Controllers\Api\Sales\ForecastAnalysisController;
use App\Http\Controllers\Api\Sales\ForecastController;
use App\Http\Controllers\Api\Sales\PackingListController;
use App\Http\Controllers\Api\Sales\PricelistController;
use App\Http\Controllers\Api\Sales\SalesInvoiceController;
use App\Http\Controllers\Api\Sales\SalesReturnController;
use App\Http\Controllers\Api\Sales\ShippingOrderController;
use App\Http\Controllers\Api\Sales\SoController;
use App\Http\Controllers\Api\Vendor\VendorPortalController;
use App\Http\Controllers\Api\Whs\WhsIncomingController;
use App\Http\Controllers\Api\Whs\WhsItemController;
use App\Http\Controllers\Api\Whs\WhsOutgoingController;
use App\Http\Controllers\Api\Whs\WhsPoController;
use App\Http\Controllers\Api\Whs\WhsReturnController;
use App\Http\Controllers\Api\Whs\WhsStockController;
use App\Http\Controllers\Api\Wms\AdjustmentController;
use App\Http\Controllers\Api\Wms\FgDowngradeController;
use App\Http\Controllers\Api\Wms\FgIncomingController;
use App\Http\Controllers\Api\Wms\FgOutgoingController;
use App\Http\Controllers\Api\Wms\FgTransferController;
use App\Http\Controllers\Api\Wms\IncomingController;
use App\Http\Controllers\Api\Wms\OutgoingController;
use App\Http\Controllers\Api\Wms\PutawayController;
use App\Http\Controllers\Api\Wms\RackController;
use App\Http\Controllers\Api\Wms\RemainingController;
use App\Http\Controllers\Api\Wms\ScrapController;
use App\Http\Controllers\Api\Wms\StockRmController;
use App\Models\m_shift;
use App\Support\ApiResponse;
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

    /*
     * Supplier portal. Isolated on purpose: these routes carry `vendor` instead
     * of `perm:` and scope every query to the caller's own ven_id, and `perm:`
     * refuses vendor accounts, so neither population can reach the other's URLs.
     */
    Route::middleware(['auth:sanctum', 'vendor'])->prefix('vendor')->group(function () {
        Route::get('me', [VendorPortalController::class, 'me']);
        Route::get('purchase-orders', [VendorPortalController::class, 'purchaseOrders']);
        Route::get('schedules', [VendorPortalController::class, 'schedules']);
        Route::post('schedules/{id}/confirm', [VendorPortalController::class, 'confirmSchedule'])->whereNumber('id');
        Route::get('delivery-notes', [VendorPortalController::class, 'deliveryNotes']);
        Route::get('progress', [VendorPortalController::class, 'progress']);
        Route::post('progress', [VendorPortalController::class, 'reportProgress']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/change-password', [AuthController::class, 'changePassword']);

        // ---- Manajemen User & Menu (PRD 2.4 Module Administrator) ----
        // Registrasi user baru, edit, reset password, status & hak akses per user.
        Route::get('users/statuses', [UserController::class, 'statuses'])->middleware('perm:users,view');
        crudRoutes('users', UserController::class);
        Route::post('users/{id}/password', [UserController::class, 'changePassword'])->whereNumber('id')->middleware('perm:users,edit');
        Route::get('users/{id}/permissions', [UserController::class, 'permissions'])->whereNumber('id')->middleware('perm:users,view');
        Route::put('users/{id}/permissions', [UserController::class, 'updatePermissions'])->whereNumber('id')->middleware('perm:users,edit');

        // CRUD pohon menu sidebar; perubahan langsung tampil setelah refresh sesi.
        crudRoutes('menus', MenuController::class);

        // ---- Dashboard ----
        Route::get('dashboard', DashboardController::class);

        // ---- Multi-level Approval (cross-cutting, LLD §4.2) ----
        Route::get('approvals/pending', [ApprovalController::class, 'myPending'])->middleware('perm:approvals,view');
        Route::get('approvals/types', [ApprovalController::class, 'types'])->middleware('perm:approvals,view');
        Route::get('approvals/history/{docType}/{docId}', [ApprovalController::class, 'history'])->whereNumber('docId')->middleware('perm:approvals,view');
        Route::post('approvals/{id}/approve', [ApprovalController::class, 'approve'])->whereNumber('id')->middleware('perm:approvals,edit');
        Route::post('approvals/{id}/reject', [ApprovalController::class, 'reject'])->whereNumber('id')->middleware('perm:approvals,edit');
        Route::post('approvals/submit/{docType}/{docId}', [ApprovalController::class, 'submit'])->whereNumber('docId')->middleware('perm:approvals,create');

        // ---- Peringatan operasional: kuota impor & stok minimum (LLD §2.2) ----
        Route::get('alerts', [AlertController::class, 'index'])->middleware('perm:alerts,view');
        Route::post('alerts/refresh', [AlertController::class, 'refresh'])->middleware('perm:alerts,create');
        Route::post('alerts/{id}/resolve', [AlertController::class, 'resolve'])->whereNumber('id')->middleware('perm:alerts,edit');

        /*
         * ---- Penawaran vendor & kontrak (PRD §4.7) ----
         * Hulu dari Supplier Item & Price: harga di master lahir dari penawaran
         * yang bisa ditunjuk, bukan angka tanpa riwayat.
         */
        Route::get('quotations/comparison', [QuotationController::class, 'comparison'])->middleware('perm:quotations,view');
        Route::post('quotations/line/{detailId}/select', [QuotationController::class, 'selectLine'])->whereNumber('detailId')->middleware('perm:quotations,edit');
        Route::post('quotations/{id}/reject', [QuotationController::class, 'reject'])->whereNumber('id')->middleware('perm:quotations,edit');
        crudRoutes('quotations', QuotationController::class);

        Route::get('contracts', [QuotationController::class, 'contracts'])->middleware('perm:contracts,view');
        Route::get('contracts/{id}', [QuotationController::class, 'showContract'])->whereNumber('id')->middleware('perm:contracts,view');
        Route::post('contracts', [QuotationController::class, 'storeContract'])->middleware('perm:contracts,create');
        Route::post('contracts/{id}/activate', [QuotationController::class, 'activateContract'])->whereNumber('id')->middleware('perm:contracts,edit');
        Route::post('contracts/{id}/cancel', [QuotationController::class, 'cancelContract'])->whereNumber('id')->middleware('perm:contracts,edit');

        // ---- Supplier Item & Price: satu material banyak vendor (PRD §4.7) ----
        // Syarat beli satu material (vendor prioritas, harga, MOQ, lead time) —
        // dipakai layar PR & PO agar pembeli tidak menebak-nebak.
        Route::get('supplier-items/terms', [SupplierItemController::class, 'terms'])->middleware('perm:pr,view');
        crudRoutes('supplier-items', SupplierItemController::class);

        // ---- General Data Master ----
        crudRoutes('categories', CategoryController::class);
        // Pengelompokan komersial part dan lintasan mesin (PRD §4.1, §4.5).
        crudRoutes('product-families', ProductFamilyController::class);
        crudRoutes('production-lines', ProductionLineController::class);
        crudRoutes('uoms', UomController::class);
        crudRoutes('currencies', CurrencyController::class);

        // Kalender kerja (PRD §4.2) — dipakai penjadwalan MPS & kapasitas CRP
        Route::get('work-calendar/effective', [WorkCalendarController::class, 'effective'])->middleware('perm:work-calendar,view');
        Route::post('work-calendar/generate', [WorkCalendarController::class, 'generate'])->middleware('perm:work-calendar,create');
        Route::get('work-calendar', [WorkCalendarController::class, 'index'])->middleware('perm:work-calendar,view');
        Route::post('work-calendar', [WorkCalendarController::class, 'store'])->middleware('perm:work-calendar,create');
        Route::put('work-calendar/{id}', [WorkCalendarController::class, 'update'])->whereNumber('id')->middleware('perm:work-calendar,edit');
        Route::delete('work-calendar/{id}', [WorkCalendarController::class, 'destroy'])->whereNumber('id')->middleware('perm:work-calendar,delete');
        // Master hari libur (PRD §4.2) — dipakai generate kalender kerja
        Route::get('holidays', [HolidayController::class, 'index'])->middleware('perm:work-calendar,view');
        Route::post('holidays', [HolidayController::class, 'store'])->middleware('perm:work-calendar,create');
        Route::put('holidays/{id}', [HolidayController::class, 'update'])->whereNumber('id')->middleware('perm:work-calendar,edit');
        Route::delete('holidays/{id}', [HolidayController::class, 'destroy'])->whereNumber('id')->middleware('perm:work-calendar,delete');

        // Kurs pajak KMK & kurs bank (PRD §6) — dipakai landed cost impor
        Route::get('exchange-rates/effective', [ExchangeRateController::class, 'effective'])->middleware('perm:exchange-rates,view');
        crudRoutes('exchange-rates', ExchangeRateController::class);
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

        // BOM Tools — WhereUsed / Copy / Compare (Fase 3 item 11)
        Route::get('bom-tools/list', [BomToolController::class, 'list'])->middleware('perm:bom-tools,view');
        Route::get('bom-tools/where-used/{itemId}', [BomToolController::class, 'whereUsed'])->whereNumber('itemId')->middleware('perm:bom-tools,view');
        Route::post('bom-tools/copy', [BomToolController::class, 'copy'])->middleware('perm:bom-tools,create');
        Route::post('bom-tools/compare', [BomToolController::class, 'compare'])->middleware('perm:bom-tools,view');

        // ECN — kontrol revisi item/BOM/routing (PRD §4.3)
        Route::get('ecn/impact', [EcnController::class, 'impact'])->middleware('perm:ecn,view');
        Route::post('ecn/{id}/submit', [EcnController::class, 'submit'])->whereNumber('id')->middleware('perm:ecn,edit');
        Route::post('ecn/{id}/approve', [EcnController::class, 'approve'])->whereNumber('id')->middleware('perm:ecn,edit');
        Route::post('ecn/{id}/reject', [EcnController::class, 'reject'])->whereNumber('id')->middleware('perm:ecn,edit');
        // Menerapkan perubahan ke master adalah tindakan tersendiri, bukan bagian
        // dari approve — tanggalnya bisa jatuh berminggu-minggu kemudian.
        Route::post('ecn/{id}/apply', [EcnController::class, 'apply'])->whereNumber('id')->middleware('perm:ecn,edit');
        crudRoutes('ecn', EcnController::class);

        /*
         * ---- NPD — New Product Development (PRD_Modul_NPD_FTPI.md, Tahap 1) ----
         *
         * Gate antar-fase memakai mesin approval bersama, jadi tidak ada rute
         * approval khusus di sini selain pintu masuknya.
         */
        Route::get('npd/dashboard', [NpdProjectController::class, 'dashboard'])->middleware('perm:npd-projects,view');
        Route::get('npd/report', [NpdProjectController::class, 'report'])->middleware('perm:npd-reports,view');
        Route::get('npd/my-tasks', [NpdTaskController::class, 'myTasks'])->middleware('perm:npd-tasks,view');

        // RFQ & feasibility
        Route::post('npd-rfq/{id}/feasibility', [NpdRfqController::class, 'saveFeasibility'])->whereNumber('id')->middleware('perm:npd-rfq,edit');
        crudRoutes('npd-rfq', NpdRfqController::class);

        // Proyek & gate
        Route::post('npd-projects/{id}/hold', [NpdProjectController::class, 'hold'])->whereNumber('id')->middleware('perm:npd-projects,edit');
        Route::post('npd-projects/{id}/cancel', [NpdProjectController::class, 'cancel'])->whereNumber('id')->middleware('perm:npd-projects,edit');
        Route::post('npd-projects/{id}/members', [NpdProjectController::class, 'saveMembers'])->whereNumber('id')->middleware('perm:npd-projects,edit');
        Route::post('npd-projects/{id}/gate/{phaseNo}/submit', [NpdProjectController::class, 'submitGate'])->whereNumber('id')->whereNumber('phaseNo')->middleware('perm:npd-projects,edit');
        // Persetujuan gate level 2 dipegang SPV lewat menu npd-handover.
        Route::post('npd-projects/{id}/gate/{phaseNo}/approve', [NpdProjectController::class, 'approveGate'])->whereNumber('id')->whereNumber('phaseNo')->middleware('perm:npd-projects,edit');
        Route::post('npd-projects/{id}/gate/{phaseNo}/reject', [NpdProjectController::class, 'rejectGate'])->whereNumber('id')->whereNumber('phaseNo')->middleware('perm:npd-projects,edit');
        /*
         * Serah terima ke produksi: kewenangan SPV, bukan pembuat proyek.
         * Satu pintu — BOM, routing, cycle time, parameter QC, dan kenaikan part
         * ke produksi massal terjadi bersama atau tidak sama sekali.
         */
        Route::get('npd-projects/{id}/handover-preview', [NpdProjectController::class, 'handoverPreview'])->whereNumber('id')->middleware('perm:npd-handover,view');
        Route::post('npd-projects/{id}/handover', [NpdProjectController::class, 'handover'])->whereNumber('id')->middleware('perm:npd-handover,edit');
        crudRoutes('npd-projects', NpdProjectController::class);

        // Task, deliverable, milestone
        Route::post('npd-tasks/phase/{phaseId}', [NpdTaskController::class, 'storeTask'])->whereNumber('phaseId')->middleware('perm:npd-tasks,create');
        Route::put('npd-tasks/{id}', [NpdTaskController::class, 'updateTask'])->whereNumber('id')->middleware('perm:npd-tasks,edit');
        Route::delete('npd-tasks/{id}', [NpdTaskController::class, 'destroyTask'])->whereNumber('id')->middleware('perm:npd-tasks,delete');
        Route::put('npd-deliverables/{id}', [NpdTaskController::class, 'updateDeliverable'])->whereNumber('id')->middleware('perm:npd-tasks,edit');
        Route::post('npd-milestones/project/{projectId}', [NpdTaskController::class, 'storeMilestone'])->whereNumber('projectId')->middleware('perm:npd-tasks,create');
        Route::put('npd-milestones/{id}', [NpdTaskController::class, 'updateMilestone'])->whereNumber('id')->middleware('perm:npd-tasks,edit');
        Route::delete('npd-milestones/{id}', [NpdTaskController::class, 'destroyMilestone'])->whereNumber('id')->middleware('perm:npd-tasks,delete');

        // BOM & costing (Tahap 2)
        Route::get('npd-costing/project/{projectId}', [NpdCostingController::class, 'show'])->whereNumber('projectId')->middleware('perm:npd-costing,view');
        // Mendaftarkan part ke master item: prasyarat BOM, WO trial, dan pricelist.
        Route::post('npd-costing/project/{projectId}/register-part', [NpdCostingController::class, 'registerPart'])->whereNumber('projectId')->middleware('perm:npd-costing,create');
        Route::post('npd-costing/project/{projectId}/bom', [NpdCostingController::class, 'storeBom'])->whereNumber('projectId')->middleware('perm:npd-costing,create');
        Route::put('npd-costing/bom/{id}', [NpdCostingController::class, 'updateBom'])->whereNumber('id')->middleware('perm:npd-costing,edit');
        Route::delete('npd-costing/bom/{id}', [NpdCostingController::class, 'destroyBom'])->whereNumber('id')->middleware('perm:npd-costing,delete');
        Route::post('npd-costing/project/{projectId}/cost', [NpdCostingController::class, 'storeCost'])->whereNumber('projectId')->middleware('perm:npd-costing,create');
        Route::put('npd-costing/cost/{id}', [NpdCostingController::class, 'updateCost'])->whereNumber('id')->middleware('perm:npd-costing,edit');
        Route::post('npd-costing/cost/{id}/recalculate', [NpdCostingController::class, 'recalculate'])->whereNumber('id')->middleware('perm:npd-costing,edit');
        Route::delete('npd-costing/cost/{id}', [NpdCostingController::class, 'destroyCost'])->whereNumber('id')->middleware('perm:npd-costing,delete');
        Route::post('npd-costing/cost/{id}/submit', [NpdCostingController::class, 'submitCost'])->whereNumber('id')->middleware('perm:npd-costing,edit');
        Route::post('npd-costing/cost/{id}/approve', [NpdCostingController::class, 'approveCost'])->whereNumber('id')->middleware('perm:npd-costing,edit');
        Route::post('npd-costing/cost/{id}/reject', [NpdCostingController::class, 'rejectCost'])->whereNumber('id')->middleware('perm:npd-costing,edit');
        // Hanya quotation APPROVED yang boleh jadi pricelist (keputusan 4 Agu 2026).
        Route::post('npd-costing/cost/{id}/to-pricelist', [NpdCostingController::class, 'toPricelist'])->whereNumber('id')->middleware('perm:npd-costing,edit');

        // Trial & hasil ukur (Tahap 3). Setiap trial membuat Work Order-nya sendiri.
        Route::get('npd-trials/project/{projectId}', [NpdTrialController::class, 'index'])->whereNumber('projectId')->middleware('perm:npd-trials,view');
        Route::post('npd-trials/project/{projectId}', [NpdTrialController::class, 'store'])->whereNumber('projectId')->middleware('perm:npd-trials,create');
        Route::get('npd-trials/{id}', [NpdTrialController::class, 'show'])->whereNumber('id')->middleware('perm:npd-trials,view');
        Route::put('npd-trials/{id}', [NpdTrialController::class, 'update'])->whereNumber('id')->middleware('perm:npd-trials,edit');
        Route::post('npd-trials/{id}/results', [NpdTrialController::class, 'saveResults'])->whereNumber('id')->middleware('perm:npd-trials,edit');
        Route::post('npd-trials/{id}/finish', [NpdTrialController::class, 'finish'])->whereNumber('id')->middleware('perm:npd-trials,edit');
        Route::delete('npd-trials/{id}', [NpdTrialController::class, 'destroy'])->whereNumber('id')->middleware('perm:npd-trials,delete');

        /*
         * FMEA & Control Plan (Tahap 4). Keduanya sepasang — control plan
         * disusun dari risiko PFMEA di atas ambang — jadi satu controller.
         * Izinnya tetap dua menu berbeda sesuai pemilik pekerjaannya.
         */
        Route::get('npd-quality/project/{projectId}', [NpdQualityController::class, 'show'])->whereNumber('projectId')->middleware('perm:npd-fmea,view');
        Route::post('npd-quality/project/{projectId}/fmea', [NpdQualityController::class, 'storeFmea'])->whereNumber('projectId')->middleware('perm:npd-fmea,create');
        Route::post('npd-quality/fmea/{id}/lines', [NpdQualityController::class, 'saveFmeaLines'])->whereNumber('id')->middleware('perm:npd-fmea,edit');
        Route::post('npd-quality/fmea/{id}/finalize', [NpdQualityController::class, 'finalizeFmea'])->whereNumber('id')->middleware('perm:npd-fmea,edit');
        Route::delete('npd-quality/fmea/{id}', [NpdQualityController::class, 'destroyFmea'])->whereNumber('id')->middleware('perm:npd-fmea,delete');

        Route::post('npd-quality/project/{projectId}/control-plan', [NpdQualityController::class, 'storeCp'])->whereNumber('projectId')->middleware('perm:npd-control-plan,create');
        Route::post('npd-quality/control-plan/{id}/lines', [NpdQualityController::class, 'saveCpLines'])->whereNumber('id')->middleware('perm:npd-control-plan,edit');
        Route::post('npd-quality/control-plan/{id}/from-fmea', [NpdQualityController::class, 'generateFromFmea'])->whereNumber('id')->middleware('perm:npd-control-plan,edit');
        Route::post('npd-quality/control-plan/{id}/finalize', [NpdQualityController::class, 'finalizeCp'])->whereNumber('id')->middleware('perm:npd-control-plan,edit');
        Route::delete('npd-quality/control-plan/{id}', [NpdQualityController::class, 'destroyCp'])->whereNumber('id')->middleware('perm:npd-control-plan,delete');

        // PPAP (Tahap 5). Enam dari 18 elemen dijawab sistem dari data proyek.
        Route::get('npd-ppap/project/{projectId}', [NpdPpapController::class, 'show'])->whereNumber('projectId')->middleware('perm:npd-ppap,view');
        Route::post('npd-ppap/project/{projectId}', [NpdPpapController::class, 'store'])->whereNumber('projectId')->middleware('perm:npd-ppap,create');
        Route::post('npd-ppap/{id}/sync', [NpdPpapController::class, 'sync'])->whereNumber('id')->middleware('perm:npd-ppap,edit');
        Route::put('npd-ppap/{id}/element/{detailId}', [NpdPpapController::class, 'updateElement'])->whereNumber('id')->whereNumber('detailId')->middleware('perm:npd-ppap,edit');
        Route::post('npd-ppap/{id}/submit', [NpdPpapController::class, 'submit'])->whereNumber('id')->middleware('perm:npd-ppap,edit');
        Route::post('npd-ppap/{id}/decision', [NpdPpapController::class, 'decision'])->whereNumber('id')->middleware('perm:npd-ppap,edit');
        Route::delete('npd-ppap/{id}', [NpdPpapController::class, 'destroy'])->whereNumber('id')->middleware('perm:npd-ppap,delete');

        // Dokumen berversi
        Route::get('npd-docs/project/{projectId}', [NpdDocController::class, 'index'])->whereNumber('projectId')->middleware('perm:npd-projects,view');
        Route::post('npd-docs/project/{projectId}', [NpdDocController::class, 'store'])->whereNumber('projectId')->middleware('perm:npd-projects,edit');
        Route::get('npd-docs/{id}/download', [NpdDocController::class, 'download'])->whereNumber('id')->middleware('perm:npd-projects,view');
        Route::delete('npd-docs/{id}', [NpdDocController::class, 'destroy'])->whereNumber('id')->middleware('perm:npd-projects,delete');

        // ---- Procurement (Fase 2) ----
        // Purchase Requisition
        crudRoutes('pr', PrController::class);
        Route::post('pr/{id}/submit', [PrController::class, 'submit'])->whereNumber('id')->middleware('perm:pr,edit');
        Route::post('pr/{id}/approve', [PrController::class, 'approve'])->whereNumber('id')->middleware('perm:pr,edit');
        Route::post('pr/{id}/reject', [PrController::class, 'reject'])->whereNumber('id')->middleware('perm:pr,edit');

        // Purchase Order
        crudRoutes('po', PoController::class);
        Route::post('po/{id}/submit', [PoController::class, 'submit'])->whereNumber('id')->middleware('perm:po,edit');
        Route::post('po/{id}/approve', [PoController::class, 'approve'])->whereNumber('id')->middleware('perm:po,edit');
        Route::post('po/{id}/close', [PoController::class, 'close'])->whereNumber('id')->middleware('perm:po,edit');
        Route::post('po/{id}/cancel', [PoController::class, 'cancel'])->whereNumber('id')->middleware('perm:po,edit');

        // Supplier Delivery Confirmation (Fase 3 item 8)
        Route::get('po-schedules', [PoScheduleController::class, 'index'])->middleware('perm:po,view');
        Route::post('po-schedules', [PoScheduleController::class, 'store'])->middleware('perm:po,create');
        Route::post('po-schedules/{id}/confirm', [PoScheduleController::class, 'confirm'])->whereNumber('id')->middleware('perm:po,edit');
        Route::delete('po-schedules/{id}', [PoScheduleController::class, 'destroy'])->whereNumber('id')->middleware('perm:po,delete');
        // Supplier Delivery Schedule template (Fase 3 item 13)
        Route::get('po-schedules/template', [PoScheduleController::class, 'template'])->middleware('perm:po,view');
        Route::post('po-schedules/import', [PoScheduleController::class, 'import'])->middleware('perm:po,create');

        // Goods Receipt (no update: post-on-create, reverse via delete)
        Route::get('grn', [GrController::class, 'index'])->middleware('perm:grn,view');
        Route::get('grn/{id}', [GrController::class, 'show'])->whereNumber('id')->middleware('perm:grn,view');
        Route::post('grn', [GrController::class, 'store'])->middleware('period.open', 'perm:grn,create');
        Route::put('grn/{id}', [GrController::class, 'update'])->whereNumber('id')->middleware('perm:grn,edit');
        Route::delete('grn/{id}', [GrController::class, 'destroy'])->whereNumber('id')->middleware('perm:grn,delete');

        // ---- QAS / Quality Inspection (PRD §4.6) ----
        Route::get('qas/pending', [QasController::class, 'pending'])->middleware('perm:qas,view');
        Route::get('qas/pending/{grId}/serials', [QasController::class, 'serials'])->whereNumber('grId')->middleware('perm:qas,view');
        Route::post('qas/inspect', [QasController::class, 'inspect'])->middleware('perm:qas,edit');
        Route::get('qas/plan/{itemId}', [QasController::class, 'plan'])->whereNumber('itemId')->middleware('perm:qas,view');
        Route::get('qas/readings/{grId}', [QasController::class, 'readings'])->whereNumber('grId')->middleware('perm:qas,view');
        Route::post('qas/readings/{grId}', [QasController::class, 'saveReadings'])->whereNumber('grId')->middleware('perm:qas,edit');
        Route::post('qas/confirm/{grId}', [QasController::class, 'confirm'])->whereNumber('grId')->middleware('perm:qas,edit');

        // Master Inspection & Master Defective (PRD §4.6)
        crudRoutes('inspection-params', InspectionParamController::class);
        crudRoutes('defectives', DefectiveController::class);

        // Import Quota
        crudRoutes('quotas', QuotaController::class);
        Route::get('quotas/{id}/balance', [QuotaController::class, 'balance'])->whereNumber('id')->middleware('perm:quotas,view');

        // Landed Cost sheet
        crudRoutes('landed-costs', CostController::class);
        Route::post('landed-costs/{id}/finalize', [CostController::class, 'finalize'])->whereNumber('id')->middleware('period.open', 'perm:landed-costs,edit');

        // GR Reject / retur vendor
        crudRoutes('gr-rejects', RejectController::class);
        Route::post('gr-rejects/{id}/return', [RejectController::class, 'markReturned'])->whereNumber('id')->middleware('perm:gr-rejects,edit');
        Route::post('gr-rejects/{id}/claim', [RejectController::class, 'markClaimed'])->whereNumber('id')->middleware('perm:gr-rejects,edit');

        // AP Invoice (3-way match)
        crudRoutes('ap-invoices', InvoiceController::class);
        Route::post('ap-invoices/{id}/match', [InvoiceController::class, 'match'])->whereNumber('id')->middleware('period.open', 'perm:ap-invoices,edit');
        Route::post('ap-invoices/{id}/post', [InvoiceController::class, 'post'])->whereNumber('id')->middleware('period.open', 'perm:ap-invoices,edit');

        // ---- Subcontract (Fase 2 tail): Master → PO subcont → kirim (DN) → terima (GR) ----
        // Master: barang apa saja yang dikerjakan tiap vendor subcont
        Route::get('subcont-items/for-vendor/{venId}', [SubcontItemController::class, 'forVendor'])->whereNumber('venId')->middleware('perm:subcont-items,view');
        Route::get('subcont-items', [SubcontItemController::class, 'index'])->middleware('perm:subcont-items,view');
        Route::post('subcont-items', [SubcontItemController::class, 'store'])->middleware('perm:subcont-items,create');
        Route::put('subcont-items/{id}', [SubcontItemController::class, 'update'])->whereNumber('id')->middleware('perm:subcont-items,edit');
        Route::delete('subcont-items/{id}', [SubcontItemController::class, 'destroy'])->whereNumber('id')->middleware('perm:subcont-items,delete');
        // Subcont PO (dokumen terpisah dari PO umum)
        crudRoutes('subcont-po', SubcontPoController::class);
        Route::post('subcont-po/{id}/approve', [SubcontPoController::class, 'approve'])->whereNumber('id')->middleware('perm:subcont-po,edit');
        Route::post('subcont-po/{id}/close', [SubcontPoController::class, 'close'])->whereNumber('id')->middleware('perm:subcont-po,edit');

        Route::get('subcont/pos', [SubcontController::class, 'pos'])->middleware('perm:subcont-dn,view');
        Route::post('subcont/scan-pallet', [SubcontController::class, 'scanPallet'])->middleware('perm:subcont-dn,view');
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
        Route::get('shifts', fn () => ApiResponse::collection(m_shift::orderBy('id')->get()));
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

        // Putaway + Booking Serial WO (Fase 3 item 9)
        // Stock Opname / Adjustment / Begin Balance (PRD §4.8)
        Route::get('stock-adjustments/system-stock', [AdjustmentController::class, 'systemStock'])->middleware('perm:stock-adjustments,view');
        Route::post('stock-adjustments/{id}/post', [AdjustmentController::class, 'post'])->whereNumber('id')->middleware('period.open', 'perm:stock-adjustments,edit');
        crudRoutes('stock-adjustments', AdjustmentController::class);

        // Scrap RM — kandidat otomatis + keputusan manual dua arah (PRD §5.3)
        Route::get('scrap-rm', [ScrapController::class, 'index'])->middleware('perm:scrap-rm,view');
        Route::get('scrap-rm/{id}/history', [ScrapController::class, 'history'])->whereNumber('id')->middleware('perm:scrap-rm,view');
        Route::post('scrap-rm/{id}/decide', [ScrapController::class, 'decide'])->whereNumber('id')->middleware('perm:scrap-rm,edit');

        // Putaway (scan serial → rak) + booking serial ke WO — LLD §5.3b
        Route::get('putaway/serials', [PutawayController::class, 'serials'])->middleware('perm:putaway,view');
        Route::post('putaway', [PutawayController::class, 'putaway'])->middleware('perm:putaway,create');
        Route::post('putaway/book', [PutawayController::class, 'book'])->middleware('perm:putaway,create');
        Route::delete('putaway/book/{id}', [PutawayController::class, 'unbook'])->whereNumber('id')->middleware('perm:putaway,delete');
        Route::post('booking', [PutawayController::class, 'book'])->middleware('perm:work-orders,create');
        Route::delete('booking/{id}', [PutawayController::class, 'unbook'])->whereNumber('id')->middleware('perm:work-orders,edit');

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

        // FG Transfer & Downgrade (Fase 2 item 9)
        Route::get('fg-transfer/lots', [FgTransferController::class, 'lots'])->middleware('perm:stock-fg,view');
        Route::post('fg-transfer', [FgTransferController::class, 'transfer'])->middleware('period.open', 'perm:stock-fg,create');
        Route::get('fg-downgrade/mappings', [FgDowngradeController::class, 'mappings'])->middleware('perm:stock-fg,view');
        Route::post('fg-downgrade', [FgDowngradeController::class, 'downgrade'])->middleware('period.open', 'perm:stock-fg,create');

        /*
         * ---- WHS Tools — gudang non-material (PRD §4.8) ----
         *
         * Menggantikan General Store lama seluruhnya. Master, pembelian,
         * penerimaan, pengeluaran, dan stoknya berdiri sendiri; alat dilacak per
         * unit karena ia dipinjam lalu kembali.
         */
        crudRoutes('whs-items', WhsItemController::class);

        Route::get('whs-po/{id}/open-lines', [WhsPoController::class, 'openLines'])->whereNumber('id')->middleware('perm:whs-po,view');
        Route::post('whs-po/{id}/submit', [WhsPoController::class, 'submit'])->whereNumber('id')->middleware('perm:whs-po,edit');
        Route::post('whs-po/{id}/approve', [WhsPoController::class, 'approve'])->whereNumber('id')->middleware('perm:whs-po,edit');
        Route::post('whs-po/{id}/reject', [WhsPoController::class, 'reject'])->whereNumber('id')->middleware('perm:whs-po,edit');
        Route::post('whs-po/{id}/close', [WhsPoController::class, 'close'])->whereNumber('id')->middleware('perm:whs-po,edit');
        crudRoutes('whs-po', WhsPoController::class);

        Route::get('whs-incoming/open-pos', [WhsIncomingController::class, 'openPos'])->middleware('perm:whs-incoming,view');
        // Post memindahkan stok dan menulis jurnal, jadi periodenya harus terbuka.
        Route::post('whs-incoming/{id}/post', [WhsIncomingController::class, 'post'])->whereNumber('id')->middleware('period.open', 'perm:whs-incoming,edit');
        crudRoutes('whs-incoming', WhsIncomingController::class);

        Route::get('whs-outgoing/available-items', [WhsOutgoingController::class, 'availableItems'])->middleware('perm:whs-outgoing,view');
        Route::get('whs-outgoing/serials', [WhsOutgoingController::class, 'serials'])->middleware('perm:whs-outgoing,view');
        Route::post('whs-outgoing/{id}/post', [WhsOutgoingController::class, 'post'])->whereNumber('id')->middleware('period.open', 'perm:whs-outgoing,edit');
        crudRoutes('whs-outgoing', WhsOutgoingController::class);

        Route::get('whs-returns/on-loan', [WhsReturnController::class, 'onLoan'])->middleware('perm:whs-returns,view');
        Route::post('whs-returns/{id}/post', [WhsReturnController::class, 'post'])->whereNumber('id')->middleware('period.open', 'perm:whs-returns,edit');
        crudRoutes('whs-returns', WhsReturnController::class);

        Route::get('whs-stock/on-loan', [WhsStockController::class, 'onLoan'])->middleware('perm:whs-stock,view');
        Route::get('whs-stock/serials', [WhsStockController::class, 'serials'])->middleware('perm:whs-stock,view');
        /*
         * Daftar kode WHS untuk layar downtime. Didaftarkan dua kali dengan izin
         * masing-masing layar MES: operator cutting dan operator processing
         * belum tentu punya hak atas menu gudang, dan keduanya butuh daftar yang
         * sama saat mencatat penggantian sparepart.
         */
        Route::get('mes/cutting/whs-codes', [WhsStockController::class, 'codes'])->middleware('perm:mes-cutting,view');
        Route::get('mes/processing/whs-codes', [WhsStockController::class, 'codes'])->middleware('perm:mes-processing,view');
        Route::get('whs-stock/unit/{id}', [WhsStockController::class, 'unit'])->whereNumber('id')->middleware('perm:whs-stock,view');
        Route::get('whs-stock', [WhsStockController::class, 'index'])->middleware('perm:whs-stock,view');

        // ---- Order Management (Fase 5): Forecast + Sales Order ----
        crudRoutes('forecasts', ForecastController::class);
        Route::get('forecast-analysis', [ForecastAnalysisController::class, 'analyze'])->middleware('perm:forecasts,view');
        Route::get('pricelists/lookup', [PricelistController::class, 'lookup'])->middleware('perm:sales-orders,view');
        crudRoutes('pricelists', PricelistController::class);
        Route::get('sales-orders/customer-items/{cusId}', [SoController::class, 'customerItems'])->whereNumber('cusId')->middleware('perm:sales-orders,view');
        crudRoutes('sales-orders', SoController::class);
        Route::post('sales-orders/{id}/submit', [SoController::class, 'submit'])->whereNumber('id')->middleware('perm:sales-orders,edit');
        Route::post('sales-orders/{id}/approve', [SoController::class, 'approve'])->whereNumber('id')->middleware('perm:sales-orders,edit');
        Route::post('sales-orders/{id}/close', [SoController::class, 'close'])->whereNumber('id')->middleware('perm:sales-orders,edit');
        Route::post('sales-orders/{id}/cancel', [SoController::class, 'cancel'])->whereNumber('id')->middleware('perm:sales-orders,edit');

        // Delivery Order: ship an approved SO from FG stock
        Route::get('delivery-orders/open-sos', [DoController::class, 'openSos'])->middleware('perm:delivery-orders,view');
        Route::get('delivery-orders/so-lines/{soId}', [DoController::class, 'soLines'])->whereNumber('soId')->middleware('perm:delivery-orders,view');
        Route::get('delivery-orders/{id}/ship-info', [DoController::class, 'shipInfo'])->whereNumber('id')->middleware('perm:delivery-orders,view');
        crudRoutes('delivery-orders', DoController::class);
        Route::post('delivery-orders/{id}/ship', [DoController::class, 'ship'])->whereNumber('id')->middleware('period.open', 'perm:delivery-orders,edit');
        Route::post('delivery-orders/{id}/receive', [DoController::class, 'receive'])->whereNumber('id')->middleware('perm:delivery-orders,edit');

        // Packing List — pembagian satu DO ke dalam kotak (PRD §5.7)
        Route::get('packing-lists/do-lines/{doId}', [PackingListController::class, 'doLines'])->whereNumber('doId')->middleware('perm:packing-lists,view');
        Route::post('packing-lists/{id}/finalize', [PackingListController::class, 'finalize'])->whereNumber('id')->middleware('perm:packing-lists,edit');
        crudRoutes('packing-lists', PackingListController::class);

        // Shipping Order — satu kendaraan, satu perjalanan, beberapa DO (PRD §5.7)
        Route::get('shipping-orders/available-dos', [ShippingOrderController::class, 'availableDos'])->middleware('perm:shipping-orders,view');
        Route::post('shipping-orders/{id}/dispatch', [ShippingOrderController::class, 'dispatch'])->whereNumber('id')->middleware('perm:shipping-orders,edit');
        Route::post('shipping-orders/{id}/deliver', [ShippingOrderController::class, 'deliver'])->whereNumber('id')->middleware('perm:shipping-orders,edit');
        Route::post('shipping-orders/{id}/cancel', [ShippingOrderController::class, 'cancel'])->whereNumber('id')->middleware('perm:shipping-orders,edit');
        crudRoutes('shipping-orders', ShippingOrderController::class);

        // Sales Return: goods back from a shipped DO
        Route::get('sales-returns/shipped-dos', [SalesReturnController::class, 'shippedDos'])->middleware('perm:sales-returns,view');
        Route::get('sales-returns', [SalesReturnController::class, 'index'])->middleware('perm:sales-returns,view');
        Route::get('sales-returns/{id}', [SalesReturnController::class, 'show'])->whereNumber('id')->middleware('perm:sales-returns,view');
        Route::post('sales-returns', [SalesReturnController::class, 'store'])->middleware('period.open', 'perm:sales-returns,create');
        Route::delete('sales-returns/{id}', [SalesReturnController::class, 'destroy'])->whereNumber('id')->middleware('perm:sales-returns,delete');
        Route::post('sales-returns/{id}/post', [SalesReturnController::class, 'post'])->whereNumber('id')->middleware('period.open', 'perm:sales-returns,edit');

        // Sales Invoice (AR): bill shipped DOs (create-then-post; no edit)
        Route::get('sales-invoices/open-dos/{cusId}', [SalesInvoiceController::class, 'openDos'])->whereNumber('cusId')->middleware('perm:sales-invoices,view');
        Route::get('sales-invoices', [SalesInvoiceController::class, 'index'])->middleware('perm:sales-invoices,view');
        Route::get('sales-invoices/{id}', [SalesInvoiceController::class, 'show'])->whereNumber('id')->middleware('perm:sales-invoices,view');
        Route::post('sales-invoices', [SalesInvoiceController::class, 'store'])->middleware('period.open', 'perm:sales-invoices,create');
        Route::delete('sales-invoices/{id}', [SalesInvoiceController::class, 'destroy'])->whereNumber('id')->middleware('perm:sales-invoices,delete');
        Route::post('sales-invoices/{id}/post', [SalesInvoiceController::class, 'post'])->whereNumber('id')->middleware('period.open', 'perm:sales-invoices,edit');

        // ---- Manufacturing: Planning (MPP -> MPS) + Work Order (Fase 3/4) ----
        Route::post('mpp/generate', [MppController::class, 'generate'])->middleware('perm:mpp,create');
        crudRoutes('mpp', MppController::class);
        Route::post('mpp/{id}/approve', [MppController::class, 'approve'])->whereNumber('id')->middleware('perm:mpp,edit');
        // MRP: explode approved MPP into net RM/FG requirements
        Route::get('mrp', [MrpController::class, 'index'])->middleware('perm:mrp,view');
        Route::get('mrp/{id}', [MrpController::class, 'show'])->whereNumber('id')->middleware('perm:mrp,view');
        Route::post('mrp/run', [MrpController::class, 'run'])->middleware('perm:mrp,create');
        Route::delete('mrp/{id}', [MrpController::class, 'destroy'])->whereNumber('id')->middleware('perm:mrp,delete');
        Route::post('mrp/{id}/generate-pr', [MrpController::class, 'generatePr'])->whereNumber('id')->middleware('perm:mrp,create');

        Route::post('mps/generate', [MpsController::class, 'generate'])->middleware('perm:mps,create');
        crudRoutes('mps', MpsController::class);
        Route::post('mps/{id}/approve', [MpsController::class, 'approve'])->whereNumber('id')->middleware('perm:mps,edit');

        // ---- CRP / Capacity Planning (LLD §5.9) ----
        Route::get('crp', [CrpController::class, 'index'])->middleware('perm:crp,view');
        Route::get('crp/{id}', [CrpController::class, 'show'])->whereNumber('id')->middleware('perm:crp,view');
        Route::post('crp/run', [CrpController::class, 'run'])->middleware('perm:crp,create');
        Route::delete('crp/{id}', [CrpController::class, 'destroy'])->whereNumber('id')->middleware('perm:crp,delete');

        // Reschedule request → approval (maker-checker) for locked MPS lots
        Route::get('mps-approvals', [MpsRescheduleController::class, 'index'])->middleware('perm:mps,view');
        Route::post('mps-approvals', [MpsRescheduleController::class, 'store'])->middleware('perm:mps,edit'); // requester
        Route::post('mps-approvals/{id}/cancel', [MpsRescheduleController::class, 'cancel'])->whereNumber('id')->middleware('perm:mps,edit');
        Route::post('mps-approvals/{id}/approve', [MpsRescheduleController::class, 'approve'])->whereNumber('id')->middleware('perm:mps-approvals,edit'); // approver
        Route::post('mps-approvals/{id}/reject', [MpsRescheduleController::class, 'reject'])->whereNumber('id')->middleware('perm:mps-approvals,edit');

        /*
         * MES scan endpoints. The whole block carries `client-uuid`: a terminal
         * replaying its offline queue stamps every write with the uuid it
         * generated locally, and the middleware turns a re-send into 409 instead
         * of a duplicate scan. Reads never carry one, so it is a no-op for them.
         */
        Route::middleware('client-uuid')->group(function () {

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

        });

        // ---- MES Offline: snapshot + queue flush (Fase 3) ----
        Route::get('mes/snapshot', [MesSyncController::class, 'snapshot'])->middleware('perm:mes-cutting,view');
        Route::post('mes/sync', [MesSyncController::class, 'sync'])->middleware('perm:mes-cutting,create');
        Route::get('mes/sync-failed', [MesSyncController::class, 'failed'])->middleware('perm:mes-cutting,view');
        Route::delete('mes/sync-clear', [MesSyncController::class, 'clear'])->middleware('perm:mes-cutting,edit');

        // ---- Kanban / Material Issue (PRD §4.8, LLD §5.3) ----
        Route::get('kanbans', [KanbanController::class, 'index'])->middleware('perm:kanbans,view');
        Route::get('kanbans/{id}', [KanbanController::class, 'show'])->whereNumber('id')->middleware('perm:kanbans,view');
        Route::post('kanbans', [KanbanController::class, 'store'])->middleware('perm:kanbans,create');
        Route::post('kanbans/{id}/issue', [KanbanController::class, 'issue'])->whereNumber('id')->middleware('perm:kanbans,edit');
        Route::post('kanbans/{id}/close', [KanbanController::class, 'close'])->whereNumber('id')->middleware('perm:kanbans,edit');

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
        Route::post('journals', [JournalController::class, 'store'])->middleware('period.open', 'perm:journals,create');
        Route::post('journals/generate', [JournalController::class, 'generate'])->middleware('period.open', 'perm:journals,create');
        Route::post('journals/{id}/reverse', [JournalController::class, 'reverse'])->whereNumber('id')->middleware('period.open', 'perm:journals,edit');

        // ---- Laporan keuangan (PRD §4.14) ----
        Route::get('reports/balance-sheet', [ReportController::class, 'balanceSheet'])->middleware('perm:fin-reports,view');
        Route::get('reports/income-statement', [ReportController::class, 'incomeStatement'])->middleware('perm:fin-reports,view');
        Route::get('reports/ap-aging', [ReportController::class, 'apAging'])->middleware('perm:fin-reports,view');
        Route::get('reports/ar-aging', [ReportController::class, 'arAging'])->middleware('perm:fin-reports,view');

        // ---- Ekspor pajak: e-Faktur Coretax + e-Bupot PPh 23 (Fase 3) ----
        Route::get('tax-export/preview', [TaxExportController::class, 'preview'])->middleware('perm:tax-export,view');
        Route::get('tax-export/efaktur', [TaxExportController::class, 'efaktur'])->middleware('perm:tax-export,download');
        Route::get('tax-export/ebupot23', [TaxExportController::class, 'ebupot23'])->middleware('perm:tax-export,download');

        Route::get('ap-payments/open-invoices/{venId}', [ApPaymentController::class, 'openInvoices'])->whereNumber('venId')->middleware('perm:ap-payments,view');
        Route::get('ap-payments', [ApPaymentController::class, 'index'])->middleware('perm:ap-payments,view');
        Route::get('ap-payments/{id}', [ApPaymentController::class, 'show'])->whereNumber('id')->middleware('perm:ap-payments,view');
        Route::post('ap-payments', [ApPaymentController::class, 'store'])->middleware('period.open', 'perm:ap-payments,create');

        Route::get('ar-receipts/open-invoices/{cusId}', [ArReceiptController::class, 'openInvoices'])->whereNumber('cusId')->middleware('perm:ar-receipts,view');
        Route::get('ar-receipts', [ArReceiptController::class, 'index'])->middleware('perm:ar-receipts,view');
        Route::get('ar-receipts/{id}', [ArReceiptController::class, 'show'])->whereNumber('id')->middleware('perm:ar-receipts,view');
        Route::post('ar-receipts', [ArReceiptController::class, 'store'])->middleware('period.open', 'perm:ar-receipts,create');

        // ---- Costing & Asset (Fase 6) ----
        Route::get('cost-rates', [CostRateController::class, 'index'])->middleware('perm:cost-rates,view');
        Route::post('cost-rates', [CostRateController::class, 'store'])->middleware('perm:cost-rates,create');
        Route::put('cost-rates/{id}', [CostRateController::class, 'update'])->whereNumber('id')->middleware('perm:cost-rates,edit');
        Route::delete('cost-rates/{id}', [CostRateController::class, 'destroy'])->whereNumber('id')->middleware('perm:cost-rates,delete');

        Route::get('cogm', [CogmController::class, 'index'])->middleware('perm:cogm,view');
        Route::post('cogm/run', [CogmController::class, 'run'])->middleware('period.open', 'perm:cogm,create');

        // Valuasi persediaan RM/WIP/FG & margin per produk (PRD §7)
        Route::get('inventory-valuation', [ValuationController::class, 'valuation'])->middleware('perm:inventory-valuation,view');
        Route::get('inventory-valuation/margin', [ValuationController::class, 'margin'])->middleware('perm:inventory-valuation,view');

        Route::get('asset-categs', [AssetCategoryController::class, 'index'])->middleware('perm:asset-categs,view');
        Route::post('asset-categs', [AssetCategoryController::class, 'store'])->middleware('perm:asset-categs,create');
        Route::put('asset-categs/{id}', [AssetCategoryController::class, 'update'])->whereNumber('id')->middleware('perm:asset-categs,edit');
        Route::delete('asset-categs/{id}', [AssetCategoryController::class, 'destroy'])->whereNumber('id')->middleware('perm:asset-categs,delete');

        Route::post('assets/depreciate', [AssetController::class, 'depreciate'])->middleware('period.open', 'perm:assets,create');
        // Dispose / transfer / retire — PRD §4.12
        Route::post('assets/{id}/retire', [AssetController::class, 'retire'])->whereNumber('id')->middleware('period.open', 'perm:assets,edit');
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

        // Final Check Sheet (Fase 2 item 8)
        Route::get('fcs', [FcsController::class, 'index'])->middleware('perm:work-orders,view');
        Route::get('fcs/eligible-wos', [FcsController::class, 'eligibleWOs'])->middleware('perm:work-orders,view');
        Route::get('fcs/{id}', [FcsController::class, 'show'])->whereNumber('id')->middleware('perm:work-orders,view');
        Route::post('fcs', [FcsController::class, 'create'])->middleware('perm:work-orders,create');
        Route::post('fcs/{id}/approve', [FcsController::class, 'approve'])->whereNumber('id')->middleware('perm:work-orders,edit');
        Route::post('fcs/{id}/reject', [FcsController::class, 'reject'])->whereNumber('id')->middleware('perm:work-orders,edit');
    });
});

/**
 * Register standard CRUD routes for a resource, guarded by per-action permissions
 * whose menu link equals the resource path segment.
 */
