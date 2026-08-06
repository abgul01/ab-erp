# Graph Report - ab-erp  (2026-08-06)

## Corpus Check
- 637 files · ~303,884 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 3608 nodes · 10272 edges · 277 communities (165 shown, 112 thin omitted)
- Extraction: 85% EXTRACTED · 15% INFERRED · 0% AMBIGUOUS · INFERRED: 1545 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `9d51f6d2`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- Eloquent Factory Models
- Eloquent Model Base Layer
- Purchase Order & Goods Receipt
- Sales DO & FG Outgoing
- FG Stock & MRP Planning
- React App Shell & Auth
- Shared React UI Components
- API Core Services & Routing
- WMS Incoming/Outgoing API
- Item Master API
- Purchase Invoice & Landed Cost
- Work Order API
- NPM Package Manifest
- Generic CRUD Controller
- MES Cutting & Serial Trace
- Frontend API Client & CRUD Pages
- Asset Category & Cost Rate
- Import Quota Management
- Subcontract DN & GR
- MES Terminal Pages
- WIP Processing & Pallets
- Demo Seeder Data Models
- Demo Data Seeder Logic
- Purchase Requisition
- MPS Master Production Schedule
- Fixed Asset & Depreciation
- MPP Production Plan
- Auth, Menus & Permissions
- Route Time Master
- MPS Reschedule Workflow
- Cutting Transaction Model
- Composer Manifest Metadata
- FG Pricelist Master
- Contacts Master Relations
- Composer Lifecycle Scripts
- Core Engine Design Concepts
- User Model & Permissions
- Work Order React Page
- Process Main Routing Master
- MES & Planning Source Files
- Database Seeder Entrypoint
- Item Master React Page
- Sales Forecast
- Processing Transaction Model
- Planning & Costing Engine Design
- Sales Return
- Machine Master Relations
- Process Master Relations
- Abnormal Cutting Transaction
- Abnormal Processing Transaction
- WIP Legacy Model
- Dev Dependencies
- MES Dispatch & Traceability Design
- RM Flow & Costing Concepts
- React Error Boundary
- Sales Order React Page
- PO Detail Model
- Downtime Processing Model
- Warehouse Outgoing Model
- COGM Costing Service
- Project Setup Scripts
- System Landscape & UI Design
- Contact CRUD Controller
- Currency CRUD Controller
- Machine CRUD Controller
- Process CRUD Controller
- UoM CRUD Controller
- Rack CRUD Controller
- RM Stock Query API
- Rack Master Relations
- GR Detail Model
- Cutting Production Model
- Sales Order Detail Model
- Downtime Cutting Model
- Project Docs & Tech Stack
- Composer Config Section
- MPP React Page
- Category CRUD Controller
- Permission Middleware
- Cutting Detail Model
- Processing Detail Model
- Warehouse Incoming Model
- Warehouse Remnant Model
- App Service Provider
- User Factory
- Warehouse Serial Schema Design
- Abnormal Decision API
- BOM Process Model
- Pricelist Detail Model
- PR Detail Model
- WO Detail PM Model
- WO Detail RM Model
- Sales Invoice Model
- Stock Check Model
- RM Stock Summary Model
- Processing Pallet Model
- Remnant Detail Model
- PSR-4 Autoload Map
- PHP Runtime Requirements
- Pest Test Bootstrap
- FG Incoming React Page
- BOM PM Detail Model
- BOM Process Detail Model
- Pallet Item Model
- Pallet Item Detail Model
- Pallet Master Model
- Process Main Detail Model
- Quota Item Model
- Cost Allocation Model
- PM Stock Summary Model
- Downtime Category Model
- FG Incoming UDF Model
- User Menu Permissions Model
- Approval & Status Workflow
- Security, RBAC & Audit
- MRP React Page
- AP Payment Model
- AR Receipt Model
- Procurement Log Model
- Item PM Mapping Model
- Item Customer Mapping
- PO Schedule Model
- Incoming QC Model
- Cutting Pallet Process Model
- Downtime Cutting Detail
- General Store Out Detail
- General Store Request Detail
- Laravel Discovery Config
- Post Autoload Dump Script
- prc_cost_alloc
- sls_inv_detail
- Document Numbering Service
- Artisan Console Entry
- Deployment & NFR
- Modular Monolith Conventions
- Cache Config
- Queue Config
- Sanctum Config
- FG Transfer Spec Group
- Multi-Vendor Sourcing
- sub_po_detail
- sum_stock_pm
- tr_inc_fg_det_udf
- MesReportController.php
- prc_gr_serial
- prd_cut_serial
- tr_out_fg_det
- wh_gen_out_main
- autoload-dev
- CostingService
- FgOutgoingPage.jsx
- prc_cost_detail
- prc_inv_detail
- ProcurementSeeder
- tr_dt_pro_detail
- WorkCalendarController
- acc_period
- .terms
- FcsService
- m_item_customer.php
- AccountingSeeder
- tr_cut_pal_pr.php
- wh_inc_detail
- AlertPage.jsx
- prd_wo_serial_pm
- tr_dt_pro_detail
- .store
- keywords
- sls_inv_detail
- sub_dn_detail
- m_machine
- UomConversionService
- static
- m_shift
- prd_wo_detail_rm
- extra
- m_quota_item
- ScrapController
- m_pal_item_det
- npd_trial_det
- tr_ab_cut_det
- prc_cost_alloc
- m_pal_item
- sum_stock_pm
- tr_inc_fg_det_udf
- sub_dn_detail
- tr_cut_detail
- prd_wo_serial_pm
- keywords
- sub_po_detail
- tr_pro_pallet.php
- m_bom_pro_det
- whs_out_det

## God Nodes (most connected - your core abstractions)
1. `ApiResponse` - 521 edges
2. `AuditLogger` - 282 edges
3. `BizException` - 223 edges
4. `Controller` - 169 edges
5. `apiError()` - 168 edges
6. `useAuth` - 161 edges
7. `money()` - 131 edges
8. `api` - 94 edges
9. `Icon()` - 93 edges
10. `npd_project` - 81 edges

## Surprising Connections (you probably didn't know these)
- `menuId()` --calls--> `menus`  [INFERRED]
  tests/Feature/UserManagementTest.php → app/Models/menus.php
- `Graphify Knowledge-Graph Workflow` --semantically_similar_to--> `Laravel Boost (Agentic Development Tooling)`  [INFERRED] [semantically similar]
  CLAUDE.md → README.md
- `kmk()` --calls--> `m_rate`  [INFERRED]
  tests/Feature/ImportTaxTest.php → app/Models/m_rate.php
- `handoverReadyProject()` --calls--> `npd_bom_main`  [INFERRED]
  tests/Feature/NpdHandoverTest.php → app/Models/npd_bom_main.php
- `handoverReadyProject()` --calls--> `npd_cp_main`  [INFERRED]
  tests/Feature/NpdHandoverTest.php → app/Models/npd_cp_main.php

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Core Shared Engines (Numbering, Approval, Tax, Journal, UoM, Serial)** — docs_lld_erp_manufaktur_pipa_numberingservice, docs_lld_erp_manufaktur_pipa_approvalengine, docs_lld_erp_manufaktur_pipa_taxengine, docs_lld_erp_manufaktur_pipa_journalengine, docs_lld_erp_manufaktur_pipa_uomconversionservice, docs_lld_erp_manufaktur_pipa_serialservice [EXTRACTED 1.00]
- **RM Serial Lifecycle: GRN generate → putaway → WO booking → cutting consume → scrap decision** — docs_lld_erp_manufaktur_pipa_grn_serial_flow, docs_lld_erp_manufaktur_pipa_putaway_layout, docs_lld_erp_manufaktur_pipa_woallocationservice, docs_lld_erp_manufaktur_pipa_trcutservice, docs_lld_erp_manufaktur_pipa_scrapservice, docs_lld_erp_manufaktur_pipa_wh_serial_table [EXTRACTED 1.00]
- **Indonesian Tax Compliance Cluster (PPN PMK 131/2024, Coretax e-Faktur, PPh 22/23 e-Bupot, landed cost)** — docs_prd_erp_manufaktur_pipa_ppn_pmk131, docs_prd_erp_manufaktur_pipa_coretax_efaktur, docs_prd_erp_manufaktur_pipa_pph23_ebupot, docs_prd_erp_manufaktur_pipa_landed_cost_sheet, docs_lld_erp_manufaktur_pipa_taxengine [EXTRACTED 1.00]

## Communities (277 total, 112 thin omitted)

### Community 0 - "Eloquent Factory Models"
Cohesion: 0.10
Nodes (5): EcnController, eng_ecn_main, BomToolService, EcnService, ecnFor()

### Community 1 - "Eloquent Model Base Layer"
Cohesion: 0.03
Nodes (31): acc_ap_pay_main, acc_ar_rec_det, cst_cogm, log_prc, m_cont_categ, m_currency, m_function_m, m_i_category (+23 more)

### Community 2 - "Purchase Order & Goods Receipt"
Cohesion: 0.18
Nodes (3): AdjustmentController, wh_adj_main, StockAdjustmentService

### Community 4 - "FG Stock & MRP Planning"
Cohesion: 0.19
Nodes (4): MrpController, RunMrpJob, prd_mrp_main, Throwable

### Community 5 - "React App Shell & Auth"
Cohesion: 0.06
Nodes (49): App(), ProtectedRoute(), ApprovalBadge(), LEVEL_LABEL, MAP, CoaPage(), EMPTY, GROUP_STYLE (+41 more)

### Community 8 - "WMS Incoming/Outgoing API"
Cohesion: 0.04
Nodes (15): ApPaymentController, ArReceiptController, ReportController, ApprovalController, DashboardController, BomToolController, QasController, CrpController (+7 more)

### Community 9 - "Item Master API"
Cohesion: 0.06
Nodes (3): ItemController, m_item, newItem()

### Community 10 - "Purchase Invoice & Landed Cost"
Cohesion: 0.05
Nodes (12): ExchangeRateController, CostController, InvoiceController, m_rate, prc_cost_main, prc_inv_main, EfakturService, ImportTaxService (+4 more)

### Community 11 - "Work Order API"
Cohesion: 0.14
Nodes (5): AdminUserManagementSeeder, DatabaseSeeder, DemandSeeder, ReplanSeeder, Illuminate\Database\Seeder

### Community 12 - "NPM Package Manifest"
Cohesion: 0.05
Nodes (42): axios, chart.js, concurrently, dexie, laravel-vite-plugin, lucide-react, dependencies, axios (+34 more)

### Community 13 - "Generic CRUD Controller"
Cohesion: 0.05
Nodes (10): TaxExportController, CogmController, CurrencyController, MakerController, ProcessController, UomController, MesReportController, PutawayController (+2 more)

### Community 15 - "Frontend API Client & CRUD Pages"
Cohesion: 0.19
Nodes (4): AlertController, sys_alert, AlertService, Carbon

### Community 16 - "Asset Category & Cost Rate"
Cohesion: 0.09
Nodes (7): BizException, CuttingController, ProcessingController, prd_wip, prd_wo_serial_rm, tr_cut_serial, RuntimeException

### Community 17 - "Import Quota Management"
Cohesion: 0.14
Nodes (4): QuotaController, m_quota, prc_quota_txn, QuotaService

### Community 18 - "Subcontract DN & GR"
Cohesion: 0.15
Nodes (3): SubcontController, sub_dn_main, sub_gr_main

### Community 19 - "MES Terminal Pages"
Cohesion: 0.05
Nodes (9): DoController, SoController, FgOutgoingController, sls_do_main, sls_so_main, tr_out_fg_main, FgStockService, ItemLifecycle (+1 more)

### Community 20 - "WIP Processing & Pallets"
Cohesion: 0.08
Nodes (34): ChooseGrModal(), Icon(), Aging(), BalanceSheet(), BUCKETS, FinReportPage(), IncomeStatement(), Section() (+26 more)

### Community 21 - "Demo Seeder Data Models"
Cohesion: 0.21
Nodes (3): AssetController, ast_depre, ast_main

### Community 22 - "Demo Data Seeder Logic"
Cohesion: 0.12
Nodes (4): NpdTaskController, npd_deliverable, npd_milestone, npd_task

### Community 25 - "Fixed Asset & Depreciation"
Cohesion: 0.15
Nodes (3): m_machine, CrpService, WorkCalendarService

### Community 26 - "MPP Production Plan"
Cohesion: 0.11
Nodes (20): Modal(), ELEMENT_STATUS, LEVELS, NpdPpapPage(), today(), EMPTY, RejectPage(), CuttingPage() (+12 more)

### Community 27 - "Auth, Menus & Permissions"
Cohesion: 0.07
Nodes (28): 0. Apa yang berubah dari versi 1, dan mengapa, 10. Kebutuhan non-fungsional (disesuaikan), 11. Metrik keberhasilan, 12. Tahapan implementasi (disesuaikan), 13. Asumsi dan batasan, 14. Keputusan yang sudah diambil (4 Agustus 2026), 1. Ringkasan, 2. Peta penyesuaian: modul lama → AB-ERP (+20 more)

### Community 29 - "MPS Reschedule Workflow"
Cohesion: 0.08
Nodes (41): apiError(), BomToolsPage(), Compare(), Copy(), TABS, EcnPage(), EMPTY, SCOPE (+33 more)

### Community 31 - "Composer Manifest Metadata"
Cohesion: 0.14
Nodes (13): autoload-dev, psr-4, description, extra, laravel, dont-discover, license, minimum-stability (+5 more)

### Community 32 - "FG Pricelist Master"
Cohesion: 0.18
Nodes (3): prc_gr_detail, SerialService, Illuminate\Database\Eloquent\Collection

### Community 33 - "Contacts Master Relations"
Cohesion: 0.12
Nodes (5): NpdPpapController, npd_ppap_main, npd_ppap_std, NpdPpapService, ppapFor()

### Community 34 - "Composer Lifecycle Scripts"
Cohesion: 0.13
Nodes (15): scripts, dev, post-autoload-dump, post-update-cmd, pre-package-uninstall, test, Composer\\Config::disableProcessTimeout, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump (+7 more)

### Community 35 - "Core Engine Design Concepts"
Cohesion: 0.26
Nodes (12): GRN Serial Flow (generate → print → actualize → confirm), NumberingService (race-safe document numbers), Optimistic Locking & Explicit Row Locks, QuotaService (reserve / actualize), ScrapService (evaluate / decide), SerialService (generateForGrLine, actualize, consume), Testing Strategy (Pest unit → Playwright E2E), WoAllocationService.book (serial booking to WO) (+4 more)

### Community 36 - "User Model & Permissions"
Cohesion: 0.12
Nodes (7): User, Illuminate\Foundation\Auth\User, Illuminate\Notifications\Notifiable, Laravel\Sanctum\HasApiTokens, menuId(), staffUser(), vendorUser()

### Community 37 - "Work Order React Page"
Cohesion: 0.07
Nodes (32): PickerModal(), RouteTimePage(), EMPTY, ITEM_COLUMNS, PO_TYPES, EMPTY, SubcontItemPage(), addMonths() (+24 more)

### Community 39 - "MES & Planning Source Files"
Cohesion: 0.08
Nodes (24): 1. Core Engines (LLD Bab 4) — ✅ LENGKAP, 2. Fitur PRD, 3. MES & Offline (PRD §2.1, LLD §6.2) — ✅ LENGKAP, 4. Infrastruktur & Non-Functional, 5. Testing, 6. Tax Compliance (PRD §6) — ✅ LENGKAP, 7. Arsitektur & Code Quality, 8. Prioritas Perbaikan — Rekomendasi (+16 more)

### Community 40 - "Database Seeder Entrypoint"
Cohesion: 0.25
Nodes (13): OfflineBar(), useOfflineSync(), db, discardFailed(), enqueue(), expire(), flush(), getSnapshot() (+5 more)

### Community 41 - "Item Master React Page"
Cohesion: 0.18
Nodes (8): COLUMNS, EMPTY, GROUPS, ItemPage(), ItemSelect(), SHAPES, TABS, useOptions()

### Community 42 - "Sales Forecast"
Cohesion: 0.11
Nodes (5): npd_deliverable_std, npd_feasibility, npd_phase, npd_project_phase, NpdService

### Community 44 - "Planning & Costing Engine Design"
Cohesion: 0.22
Nodes (11): COGM Calculation & Roll-Up per WO, CRP Loading Calculation, MRP Engine (low-level-code explosion), Queue Jobs & Scheduler (default | heavy), wh_serial / wh_serial_movement / wh_stock_sum Schema, Multi-Level BOM (FG as PM of another FG), COGM / HPP Actual Costing per WO, Domain Glossary (WOS, QAS, GRN, RFG, FCS, UMH, COGM) (+3 more)

### Community 45 - "Sales Return"
Cohesion: 0.16
Nodes (4): MesSyncController, mes_oplog, MesSyncService, syncReq()

### Community 46 - "Machine Master Relations"
Cohesion: 0.08
Nodes (7): JournalController, CostRateController, NpdDocController, NpdProjectController, cst_rate, npd_doc, AuditLogger

### Community 51 - "Dev Dependencies"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision, pestphp/pest (+1 more)

### Community 52 - "MES Dispatch & Traceability Design"
Cohesion: 0.16
Nodes (16): FCS → RFG Receiving Flow, client_uuid Idempotency Key, Item–Customer Mapping Validation (m_item_customer), JournalEngine (post), Fixes vs Reference Application Schema, MesDispatchService.resolve (TR_CUT vs TR_PRO), WIP Pallet Traceability (prd_wip_pallet), Putaway & Visual Warehouse Layout (+8 more)

### Community 53 - "RM Flow & Costing Concepts"
Cohesion: 0.40
Nodes (5): UomConversionService, Dual UoM for Pipe RM (length mm + weight kg), FG Downgrade (NG finished good back to material), Scrap RM Candidate Rule (remaining < min BOM length), Serial Number Traceability (RM 1:pcs, PM 1:lot)

### Community 54 - "React Error Boundary"
Cohesion: 0.24
Nodes (3): ErrorBoundary, queryClient, registerServiceWorker()

### Community 55 - "Sales Order React Page"
Cohesion: 0.11
Nodes (5): NpdCostingController, m_pricelist_det, npd_bom_main, npd_cost_main, NpdCostingService

### Community 60 - "Project Setup Scripts"
Cohesion: 0.25
Nodes (8): post-root-package-install, setup, composer install, npm install --ignore-scripts, npm run build, @php artisan key:generate, @php artisan migrate --force, @php -r \"file_exists('.env') || copy('.env.example', '.env');\

### Community 61 - "System Landscape & UI Design"
Cohesion: 0.25
Nodes (8): API Contract (/api/v1 and /api/mes/v1), React Frontend Design (DataTable, DocForm, ScanBox), Offline Terminal Design (service worker + IndexedDB queue), ERP + MES System Landscape (14 Modules), FTPI Visual Identity & UI/UX Direction, MES Offline Mode (PWA buffer + client_uuid idempotency), 7-Phase Implementation Roadmap, Single MySQL Database `ab-erp` for ERP and MES

### Community 62 - "Contact CRUD Controller"
Cohesion: 0.10
Nodes (4): SalesReturnController, FgIncomingController, tr_inc_fg_det, tr_inc_fg_main

### Community 64 - "Machine CRUD Controller"
Cohesion: 0.05
Nodes (9): CrudController, CategoryController, ContactCategoryController, DefectiveController, InspectionParamController, ProductFamilyController, ProductionLineController, TaxController (+1 more)

### Community 66 - "UoM CRUD Controller"
Cohesion: 0.18
Nodes (3): PoScheduleController, VendorPortalController, prc_po_schedule

### Community 67 - "Rack CRUD Controller"
Cohesion: 0.14
Nodes (13): fmtNum(), fmtPct(), KpiCard(), LowStockChart(), MiniStat(), MrpBreakdownChart(), NAV_MAP, SoFulfillmentChart() (+5 more)

### Community 73 - "Downtime Cutting Model"
Cohesion: 0.10
Nodes (5): NpdQualityController, npd_cp_main, npd_fmea_main, NpdQualityService, fmeaFor()

### Community 74 - "Project Docs & Tech Stack"
Cohesion: 0.29
Nodes (7): graphify-out Artifacts (graph.json, wiki, GRAPH_REPORT), Graphify Knowledge-Graph Workflow, LLD ERP + MES Manufaktur Pipa v2.0, PRD ERP + MES Manufaktur Pipa v3.0, Tech Stack (Laravel 11 / React 18 / MySQL 8 / Redis), Laravel Boost (Agentic Development Tooling), Laravel Framework

### Community 75 - "Composer Config Section"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 76 - "MPP React Page"
Cohesion: 0.07
Nodes (28): 10. Model Data (ERD), 11. Metrik Keberhasilan (KPI), 12. Asumsi dan Batasan, 13. Saran Tahap Implementasi, 1. Ringkasan, 2. Latar Belakang dan Tujuan, 3. Ruang Lingkup, 4. Referensi Standar (+20 more)

### Community 77 - "Category CRUD Controller"
Cohesion: 0.14
Nodes (4): NpdTrialController, npd_trial_main, NpdTrialService, npdTrial()

### Community 78 - "Permission Middleware"
Cohesion: 0.13
Nodes (9): PeriodController, CheckClientUuid, CheckPermission, EnsurePeriodOpen, EnsureVendor, VendorAuth, acc_period, Closure (+1 more)

### Community 79 - "Cutting Detail Model"
Cohesion: 0.07
Nodes (7): GrController, PoController, RejectController, prc_gr_main, prc_gr_reject, prc_po_main, self

### Community 85 - "Warehouse Serial Schema Design"
Cohesion: 0.18
Nodes (14): Illuminate\Foundation\Testing\TestCase, alertProject(), npdBom(), npdCost(), npdProject(), handoverReadyProject(), ppapProject(), qualityProject() (+6 more)

### Community 89 - "PR Detail Model"
Cohesion: 0.20
Nodes (3): prd_scrap_decision, ScrapService, scrapFixture()

### Community 93 - "Stock Check Model"
Cohesion: 0.17
Nodes (8): approvals(), approvedStatus(), isFullyApproved(), onFullyApproved(), onRejected(), pendingApprovals(), rejectedStatus(), Illuminate\Database\Eloquent\Relations\HasMany

### Community 95 - "Processing Pallet Model"
Cohesion: 0.25
Nodes (7): background_color, display, icons, name, short_name, start_url, theme_color

### Community 97 - "PSR-4 Autoload Map"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 98 - "PHP Runtime Requirements"
Cohesion: 0.40
Nodes (5): require, laravel/framework, laravel/sanctum, laravel/tinker, php

### Community 99 - "Pest Test Bootstrap"
Cohesion: 0.08
Nodes (30): EMPTY_CONTROL, EMPTY_RISK, NpdQualityPage(), rpnClass(), today(), CellInput(), STATUS_LABEL, STATUS_STYLE (+22 more)

### Community 101 - "BOM PM Detail Model"
Cohesion: 0.15
Nodes (3): prd_mrp_detail, ErrorCodes, MrpService

### Community 106 - "Process Main Detail Model"
Cohesion: 0.11
Nodes (30): addMonths(), currentPeriod(), formatPeriod(), MonthPicker(), MonthRangePicker(), periodRange(), toInput(), toPeriod() (+22 more)

### Community 112 - "FG Incoming UDF Model"
Cohesion: 0.13
Nodes (3): m_bom_det_pm, m_bom_det_rm, m_bom

### Community 114 - "Approval & Status Workflow"
Cohesion: 0.50
Nodes (4): ApprovalEngine, Standard Status Workflow & State Machine, Paperless Multi-Level Approval & Mobile Approval, Period-Based FG Pricelist

### Community 115 - "Security, RBAC & Audit"
Cohesion: 0.50
Nodes (4): Audit Trail & Structured Logging, Security Design (Sanctum, 2FA, vendor guard, period lock), Granular RBAC, Menu/User Privilege & TOTP 2FA, robots.txt Open Crawl Policy

### Community 122 - "PO Schedule Model"
Cohesion: 0.50
Nodes (4): post-create-project-cmd, @php artisan key:generate --ansi, @php artisan migrate --graceful --ansi, @php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\

### Community 123 - "Incoming QC Model"
Cohesion: 0.27
Nodes (4): m_whs_item, whsIssue(), whsItem(), whsReceive()

### Community 126 - "General Store Out Detail"
Cohesion: 0.12
Nodes (13): TYPE_LABEL, TYPE_STYLE, TypeBadge(), useWhsItems(), WHS_TYPES, WhsItemSelect(), EMPTY, WhsIncomingPage() (+5 more)

### Community 127 - "General Store Request Detail"
Cohesion: 0.03
Nodes (28): acc_ap_pay_det, eng_ecn_det, m_defective, m_fg_downgrade_map, m_inspection_param, m_item_inspection, m_pal_item, m_process_main_det (+20 more)

### Community 161 - "Document Numbering Service"
Cohesion: 0.21
Nodes (4): KanbanController, prd_kanban, MaterialIssueService, NumberingService

### Community 162 - "Artisan Console Entry"
Cohesion: 0.13
Nodes (3): UserController, status_id, user_menu_permissions

### Community 166 - "Cache Config"
Cohesion: 0.08
Nodes (7): QuotationController, m_supplier_item, prc_contract_main, prc_quot_det, prc_quot_main, VendorQuotationService, quotFor()

### Community 189 - "prc_gr_serial"
Cohesion: 0.08
Nodes (7): prc_gr_serial, qc_incoming_det, qc_incoming_main, sls_inv_main, QasService, TaxExportService, Illuminate\Support\Collection

### Community 190 - "prd_cut_serial"
Cohesion: 0.33
Nodes (6): Subcont Flow (PO Subcont → DN → Receipt → AP), TaxEngine (calcVat), e-Faktur / Coretax Integration, PPh 23 Subcont & e-Bupot, PPN under PMK 131/2024 (DPP Nilai Lain 11/12), Subcontractor Portal & Virtual Location

### Community 192 - "tr_out_fg_det"
Cohesion: 0.32
Nodes (3): UserFactory, Illuminate\Database\Eloquent\Factories\Factory, static

### Community 199 - "prc_inv_detail"
Cohesion: 0.14
Nodes (8): ContractExpiryJob, GenerateCogmJob, MinStockAlertJob, NpdAlertJob, QuotaAlertJob, RunCrpJob, Illuminate\Contracts\Queue\ShouldQueue, Illuminate\Foundation\Queue\Queueable

### Community 234 - "prd_wo_serial_pm"
Cohesion: 0.08
Nodes (27): api, DataTable(), DynamicForm(), ApPaymentPage(), today(), ArReceiptPage(), today(), EMPTY_PERMS (+19 more)

### Community 244 - "m_machine"
Cohesion: 0.67
Nodes (3): keywords, framework, laravel

### Community 266 - "keywords"
Cohesion: 0.06
Nodes (3): Carbon\Carbon, Illuminate\Support\Carbon, mrpFixture()

## Ambiguous Edges - Review These
- `graphify-out Artifacts (graph.json, wiki, GRAPH_REPORT)` → `PRD ERP + MES Manufaktur Pipa v3.0`  [AMBIGUOUS]
  CLAUDE.md · relation: references
- `robots.txt Open Crawl Policy` → `Security Design (Sanctum, 2FA, vendor guard, period lock)`  [AMBIGUOUS]
  public/robots.txt · relation: conceptually_related_to

## Knowledge Gaps
- **246 isolated node(s):** `$schema`, `name`, `type`, `description`, `laravel` (+241 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **112 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `graphify-out Artifacts (graph.json, wiki, GRAPH_REPORT)` and `PRD ERP + MES Manufaktur Pipa v3.0`?**
  _Edge tagged AMBIGUOUS (relation: references) - confidence is low._
- **What is the exact relationship between `robots.txt Open Crawl Policy` and `Security Design (Sanctum, 2FA, vendor guard, period lock)`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **Why does `ApiResponse` connect `WMS Incoming/Outgoing API` to `Eloquent Factory Models`, `Post Autoload Dump Script`, `prc_cost_alloc`, `Sales DO & FG Outgoing`, `FG Stock & MRP Planning`, `Purchase Order & Goods Receipt`, `API Core Services & Routing`, `Item Master API`, `Purchase Invoice & Landed Cost`, `Generic CRUD Controller`, `MES Cutting & Serial Trace`, `Frontend API Client & CRUD Pages`, `Asset Category & Cost Rate`, `Import Quota Management`, `Subcontract DN & GR`, `MES Terminal Pages`, `Demo Seeder Data Models`, `Demo Data Seeder Logic`, `Purchase Requisition`, `MPS Master Production Schedule`, `Route Time Master`, `Contacts Master Relations`, `Artisan Console Entry`, `Document Numbering Service`, `Process Main Routing Master`, `Cache Config`, `Queue Config`, `Sanctum Config`, `Sales Return`, `Machine Master Relations`, `Sales Order React Page`, `sum_stock_pm`, `COGM Costing Service`, `MesReportController.php`, `prc_gr_serial`, `Contact CRUD Controller`, `Currency CRUD Controller`, `Machine CRUD Controller`, `Process CRUD Controller`, `UoM CRUD Controller`, `autoload-dev`, `RM Stock Query API`, `Rack Master Relations`, `GR Detail Model`, `prc_inv_detail`, `ProcurementSeeder`, `Downtime Cutting Model`, `Category CRUD Controller`, `Permission Middleware`, `Cutting Detail Model`, `Warehouse Remnant Model`, `User Factory`, `Abnormal Decision API`, `BOM Process Model`, `WorkCalendarController`, `WO Detail RM Model`, `BOM Process Detail Model`, `Pallet Item Model`, `Pallet Master Model`, `Quota Item Model`, `.store`, `Cost Allocation Model`, `Downtime Category Model`, `sls_inv_detail`, `MRP React Page`, `AR Receipt Model`, `Item PM Mapping Model`, `Item Customer Mapping`, `Cutting Pallet Process Model`?**
  _High betweenness centrality (0.061) - this node is a cross-community bridge._
- **Why does `User` connect `User Model & Permissions` to `Eloquent Model Base Layer`, `Artisan Console Entry`, `prc_cost_alloc`, `Document Numbering Service`, `Purchase Order & Goods Receipt`, `GR Detail Model`, `API Core Services & Routing`, `Pallet Item Detail Model`, `AlertPage.jsx`, `Cost Detail Model`, `Warehouse Serial Schema Design`, `AR Receipt Model`, `PR Detail Model`, `Sales Invoice Model`, `Downtime Cutting Detail`?**
  _High betweenness centrality (0.027) - this node is a cross-community bridge._
- **Why does `npd_project` connect `Sales DO & FG Outgoing` to `Remnant Detail Model`, `Contacts Master Relations`, `m_item_customer.php`, `API Core Services & Routing`, `Downtime Cutting Model`, `Sales Forecast`, `Category CRUD Controller`, `Machine Master Relations`, `Warehouse Serial Schema Design`, `Demo Data Seeder Logic`, `Sales Order React Page`, `General Store Request Detail`?**
  _High betweenness centrality (0.026) - this node is a cross-community bridge._
- **Are the 512 inferred relationships involving `ApiResponse` (e.g. with `.index()` and `.openInvoices()`) actually correct?**
  _`ApiResponse` has 512 INFERRED edges - model-reasoned connections that need verification._
- **Are the 280 inferred relationships involving `AuditLogger` (e.g. with `.store()` and `.store()`) actually correct?**
  _`AuditLogger` has 280 INFERRED edges - model-reasoned connections that need verification._