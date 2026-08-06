# Graph Report - ab-erp  (2026-08-04)

## Corpus Check
- 585 files · ~272,382 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 3334 nodes · 9389 edges · 281 communities (164 shown, 117 thin omitted)
- Extraction: 85% EXTRACTED · 15% INFERRED · 0% AMBIGUOUS · INFERRED: 1379 edges (avg confidence: 0.8)
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
- Cost Detail Model
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
- tr_dt_cut_detail
- tr_out_fg_det
- wh_gen_out_main
- FgIncomingPage.jsx
- FgOutgoingPage.jsx
- prc_cost_detail
- prc_inv_detail
- prd_crp.php
- tr_dt_pro_detail
- tr_out_fg_det
- acc_period
- .terms
- m_bom_pro_det
- m_item_customer.php
- m_pal_item
- tr_cut_pal_pr.php
- wh_inc_detail
- AlertPage.jsx
- prd_wo_serial_pm
- tr_dt_pro_detail
- wh_gen_out_det
- keywords
- sls_inv_detail
- sub_dn_detail
- Finished Good Flow (MPS→MPP→WO→Kanban→MES→FCS→RFG→DN→Invoice)
- WorkCalendarService.php
- tr_dt_category
- cst_cogm
- qc_incoming_det
- tr_dt_cut_detail
- extra
- MppPage.jsx
- ScrapController
- m_pal_item_det
- tr_ab_cut_det
- WhsStockController
- m_pal_item
- m_quota_item
- sls_inv_detail
- sub_dn_detail
- tr_cut_detail
- ValuationPage.jsx
- keywords
- SubcontDnPage.jsx
- m_bom_pro
- ApprovalPage.jsx
- UsersPage.jsx
- FgIncomingPage.jsx
- FgOutgoingPage.jsx
- m_bom_det_rm
- m_bom_pro_det
- m_process_main_det
- prc_cost_alloc
- prd_fcs_main
- sum_stock_pm
- tr_inc_fg_det_udf

## God Nodes (most connected - your core abstractions)
1. `ApiResponse` - 480 edges
2. `AuditLogger` - 256 edges
3. `BizException` - 203 edges
4. `Controller` - 161 edges
5. `apiError()` - 156 edges
6. `useAuth` - 151 edges
7. `money()` - 121 edges
8. `api` - 88 edges
9. `Icon()` - 87 edges
10. `useOptions()` - 64 edges

## Surprising Connections (you probably didn't know these)
- `kmk()` --calls--> `m_rate`  [INFERRED]
  tests/Feature/ImportTaxTest.php → app/Models/m_rate.php
- `menuId()` --calls--> `menus`  [INFERRED]
  tests/Feature/UserManagementTest.php → app/Models/menus.php
- `Graphify Knowledge-Graph Workflow` --semantically_similar_to--> `Laravel Boost (Agentic Development Tooling)`  [INFERRED] [semantically similar]
  CLAUDE.md → README.md
- `graphify-out Artifacts (graph.json, wiki, GRAPH_REPORT)` --references--> `PRD ERP + MES Manufaktur Pipa v3.0`  [AMBIGUOUS]
  CLAUDE.md → docs/PRD-ERP-Manufaktur-Pipa.md
- `robots.txt Open Crawl Policy` --conceptually_related_to--> `Security Design (Sanctum, 2FA, vendor guard, period lock)`  [AMBIGUOUS]
  public/robots.txt → docs/LLD-ERP-Manufaktur-Pipa.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Core Shared Engines (Numbering, Approval, Tax, Journal, UoM, Serial)** — docs_lld_erp_manufaktur_pipa_numberingservice, docs_lld_erp_manufaktur_pipa_approvalengine, docs_lld_erp_manufaktur_pipa_taxengine, docs_lld_erp_manufaktur_pipa_journalengine, docs_lld_erp_manufaktur_pipa_uomconversionservice, docs_lld_erp_manufaktur_pipa_serialservice [EXTRACTED 1.00]
- **RM Serial Lifecycle: GRN generate → putaway → WO booking → cutting consume → scrap decision** — docs_lld_erp_manufaktur_pipa_grn_serial_flow, docs_lld_erp_manufaktur_pipa_putaway_layout, docs_lld_erp_manufaktur_pipa_woallocationservice, docs_lld_erp_manufaktur_pipa_trcutservice, docs_lld_erp_manufaktur_pipa_scrapservice, docs_lld_erp_manufaktur_pipa_wh_serial_table [EXTRACTED 1.00]
- **Indonesian Tax Compliance Cluster (PPN PMK 131/2024, Coretax e-Faktur, PPh 22/23 e-Bupot, landed cost)** — docs_prd_erp_manufaktur_pipa_ppn_pmk131, docs_prd_erp_manufaktur_pipa_coretax_efaktur, docs_prd_erp_manufaktur_pipa_pph23_ebupot, docs_prd_erp_manufaktur_pipa_landed_cost_sheet, docs_lld_erp_manufaktur_pipa_taxengine [EXTRACTED 1.00]

## Communities (281 total, 117 thin omitted)

### Community 0 - "Eloquent Factory Models"
Cohesion: 0.15
Nodes (3): EcnController, eng_ecn_main, ecnFor()

### Community 1 - "Eloquent Model Base Layer"
Cohesion: 0.03
Nodes (28): acc_ap_pay_det, acc_ap_pay_main, acc_ar_rec_det, log_prc, m_cont_categ, m_defective, m_function_m, m_i_category (+20 more)

### Community 2 - "Purchase Order & Goods Receipt"
Cohesion: 0.14
Nodes (4): acc_journal_det, acc_journal_main, JournalEngine, self

### Community 3 - "Sales DO & FG Outgoing"
Cohesion: 0.11
Nodes (5): npd_deliverable_std, npd_feasibility, npd_phase, npd_project_phase, NpdService

### Community 4 - "FG Stock & MRP Planning"
Cohesion: 0.25
Nodes (3): MrpController, prd_mrp_main, Throwable

### Community 5 - "React App Shell & Auth"
Cohesion: 0.05
Nodes (105): apiError(), App(), ProtectedRoute(), ApPaymentPage(), today(), ArReceiptPage(), today(), CoaPage() (+97 more)

### Community 8 - "WMS Incoming/Outgoing API"
Cohesion: 0.05
Nodes (14): ApPaymentController, ArReceiptController, JournalController, ApprovalController, DashboardController, BomToolController, QasController, CrpController (+6 more)

### Community 9 - "Item Master API"
Cohesion: 0.07
Nodes (3): ItemController, m_item, newItem()

### Community 12 - "NPM Package Manifest"
Cohesion: 0.05
Nodes (42): axios, chart.js, concurrently, dexie, laravel-vite-plugin, lucide-react, dependencies, axios (+34 more)

### Community 13 - "Generic CRUD Controller"
Cohesion: 0.05
Nodes (9): ReportController, TaxExportController, CurrencyController, MakerController, TaxController, UomController, MesReportController, RackController (+1 more)

### Community 14 - "MES Cutting & Serial Trace"
Cohesion: 0.17
Nodes (3): CuttingController, prd_wo_serial_rm, tr_cut_serial

### Community 15 - "Frontend API Client & CRUD Pages"
Cohesion: 0.19
Nodes (3): AlertController, sys_alert, AlertService

### Community 18 - "Subcontract DN & GR"
Cohesion: 0.15
Nodes (3): SubcontController, sub_dn_main, sub_gr_main

### Community 19 - "MES Terminal Pages"
Cohesion: 0.15
Nodes (3): FgOutgoingController, sls_do_main, tr_out_fg_main

### Community 20 - "WIP Processing & Pallets"
Cohesion: 0.27
Nodes (8): today(), KplModal(), Stepper(), Timer(), ViewCuttingModal(), ViewPalletModal(), ViewProcessingModal(), today()

### Community 22 - "Demo Data Seeder Logic"
Cohesion: 0.18
Nodes (3): NpdTaskController, npd_milestone, npd_task

### Community 24 - "MPS Master Production Schedule"
Cohesion: 0.15
Nodes (4): MppController, MpsController, prd_mpp, prd_mps

### Community 27 - "Auth, Menus & Permissions"
Cohesion: 0.07
Nodes (28): 0. Apa yang berubah dari versi 1, dan mengapa, 10. Kebutuhan non-fungsional (disesuaikan), 11. Metrik keberhasilan, 12. Tahapan implementasi (disesuaikan), 13. Asumsi dan batasan, 14. Keputusan yang sudah diambil (4 Agustus 2026), 1. Ringkasan, 2. Peta penyesuaian: modul lama → AB-ERP (+20 more)

### Community 29 - "MPS Reschedule Workflow"
Cohesion: 0.09
Nodes (50): api, ChooseGrModal(), DataTable(), Icon(), MAP, Modal(), PickerModal(), EMPTY (+42 more)

### Community 31 - "Composer Manifest Metadata"
Cohesion: 0.14
Nodes (13): autoload-dev, psr-4, description, extra, laravel, dont-discover, license, minimum-stability (+5 more)

### Community 33 - "Contacts Master Relations"
Cohesion: 0.19
Nodes (8): approvals(), approvedStatus(), isFullyApproved(), onFullyApproved(), onRejected(), pendingApprovals(), rejectedStatus(), Illuminate\Database\Eloquent\Relations\HasMany

### Community 34 - "Composer Lifecycle Scripts"
Cohesion: 0.13
Nodes (15): scripts, dev, post-autoload-dump, post-update-cmd, pre-package-uninstall, test, Composer\\Config::disableProcessTimeout, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump (+7 more)

### Community 35 - "Core Engine Design Concepts"
Cohesion: 0.26
Nodes (12): GRN Serial Flow (generate → print → actualize → confirm), NumberingService (race-safe document numbers), Optimistic Locking & Explicit Row Locks, QuotaService (reserve / actualize), ScrapService (evaluate / decide), SerialService (generateForGrLine, actualize, consume), Testing Strategy (Pest unit → Playwright E2E), WoAllocationService.book (serial booking to WO) (+4 more)

### Community 36 - "User Model & Permissions"
Cohesion: 0.17
Nodes (5): User, Illuminate\Foundation\Auth\User, Illuminate\Notifications\Notifiable, Laravel\Sanctum\HasApiTokens, vendorUser()

### Community 37 - "Work Order React Page"
Cohesion: 0.21
Nodes (8): blank(), SerialModal(), num(), SerialRmModal(), STATUS, today(), uid(), WoPage()

### Community 39 - "MES & Planning Source Files"
Cohesion: 0.08
Nodes (24): 1. Core Engines (LLD Bab 4) — ✅ LENGKAP, 2. Fitur PRD, 3. MES & Offline (PRD §2.1, LLD §6.2) — ✅ LENGKAP, 4. Infrastruktur & Non-Functional, 5. Testing, 6. Tax Compliance (PRD §6) — ✅ LENGKAP, 7. Arsitektur & Code Quality, 8. Prioritas Perbaikan — Rekomendasi (+16 more)

### Community 40 - "Database Seeder Entrypoint"
Cohesion: 0.25
Nodes (13): OfflineBar(), useOfflineSync(), db, discardFailed(), enqueue(), expire(), flush(), getSnapshot() (+5 more)

### Community 41 - "Item Master React Page"
Cohesion: 0.20
Nodes (6): COLUMNS, EMPTY, ItemSelect(), TABS, TYPES, useOptions()

### Community 42 - "Sales Forecast"
Cohesion: 0.27
Nodes (4): m_whs_item, whsIssue(), whsItem(), whsReceive()

### Community 44 - "Planning & Costing Engine Design"
Cohesion: 0.22
Nodes (11): COGM Calculation & Roll-Up per WO, CRP Loading Calculation, MRP Engine (low-level-code explosion), Queue Jobs & Scheduler (default | heavy), wh_serial / wh_serial_movement / wh_stock_sum Schema, Multi-Level BOM (FG as PM of another FG), COGM / HPP Actual Costing per WO, Domain Glossary (WOS, QAS, GRN, RFG, FCS, UMH, COGM) (+3 more)

### Community 45 - "Sales Return"
Cohesion: 0.22
Nodes (3): mes_oplog, MesSyncService, syncReq()

### Community 46 - "Machine Master Relations"
Cohesion: 0.20
Nodes (3): BizException, EcnService, RuntimeException

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
Cohesion: 0.10
Nodes (5): NpdCostingController, m_pricelist_det, npd_bom_main, npd_cost_main, NpdCostingService

### Community 60 - "Project Setup Scripts"
Cohesion: 0.25
Nodes (8): post-root-package-install, setup, composer install, npm install --ignore-scripts, npm run build, @php artisan key:generate, @php artisan migrate --force, @php -r \"file_exists('.env') || copy('.env.example', '.env');\

### Community 61 - "System Landscape & UI Design"
Cohesion: 0.25
Nodes (8): API Contract (/api/v1 and /api/mes/v1), React Frontend Design (DataTable, DocForm, ScanBox), Offline Terminal Design (service worker + IndexedDB queue), ERP + MES System Landscape (14 Modules), FTPI Visual Identity & UI/UX Direction, MES Offline Mode (PWA buffer + client_uuid idempotency), 7-Phase Implementation Roadmap, Single MySQL Database `ab-erp` for ERP and MES

### Community 62 - "Contact CRUD Controller"
Cohesion: 0.15
Nodes (3): FgIncomingController, tr_inc_fg_det, tr_inc_fg_main

### Community 64 - "Machine CRUD Controller"
Cohesion: 0.06
Nodes (7): CrudController, CategoryController, ContactCategoryController, DefectiveController, InspectionParamController, ProcessController, SupplierItemController

### Community 66 - "UoM CRUD Controller"
Cohesion: 0.18
Nodes (3): PoScheduleController, VendorPortalController, prc_po_schedule

### Community 67 - "Rack CRUD Controller"
Cohesion: 0.14
Nodes (13): fmtNum(), fmtPct(), KpiCard(), LowStockChart(), MiniStat(), MrpBreakdownChart(), NAV_MAP, SoFulfillmentChart() (+5 more)

### Community 70 - "GR Detail Model"
Cohesion: 0.17
Nodes (3): prc_gr_detail, SerialService, Illuminate\Database\Eloquent\Collection

### Community 73 - "Downtime Cutting Model"
Cohesion: 0.20
Nodes (3): ExchangeRateController, m_rate, KursService

### Community 74 - "Project Docs & Tech Stack"
Cohesion: 0.29
Nodes (7): graphify-out Artifacts (graph.json, wiki, GRAPH_REPORT), Graphify Knowledge-Graph Workflow, LLD ERP + MES Manufaktur Pipa v2.0, PRD ERP + MES Manufaktur Pipa v3.0, Tech Stack (Laravel 11 / React 18 / MySQL 8 / Redis), Laravel Boost (Agentic Development Tooling), Laravel Framework

### Community 75 - "Composer Config Section"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 76 - "MPP React Page"
Cohesion: 0.07
Nodes (28): 10. Model Data (ERD), 11. Metrik Keberhasilan (KPI), 12. Asumsi dan Batasan, 13. Saran Tahap Implementasi, 1. Ringkasan, 2. Latar Belakang dan Tujuan, 3. Ruang Lingkup, 4. Referensi Standar (+20 more)

### Community 78 - "Permission Middleware"
Cohesion: 0.13
Nodes (9): PeriodController, CheckClientUuid, CheckPermission, EnsurePeriodOpen, EnsureVendor, VendorAuth, acc_period, Closure (+1 more)

### Community 85 - "Warehouse Serial Schema Design"
Cohesion: 0.19
Nodes (9): Illuminate\Foundation\Testing\TestCase, npdBom(), npdCost(), npdProject(), feasFor(), rfqFor(), admin(), req() (+1 more)

### Community 90 - "WO Detail PM Model"
Cohesion: 0.23
Nodes (3): SubcontPoController, sub_po_detail, sub_po_main

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
Cohesion: 0.30
Nodes (9): pendingMandatory(), PHASE_STYLE, PhaseBadge(), PhaseRail(), PHASES, PROJECT_STATUS, DELIVERABLE_STATUS, NpdProjectPage() (+1 more)

### Community 106 - "Process Main Detail Model"
Cohesion: 0.09
Nodes (35): addMonths(), currentPeriod(), formatPeriod(), MonthPicker(), MonthRangePicker(), periodRange(), toInput(), toPeriod() (+27 more)

### Community 109 - "Cost Detail Model"
Cohesion: 0.09
Nodes (7): CoaController, AuthController, MesSyncController, SalesInvoiceController, PutawayController, acc_coa, AuditLogger

### Community 112 - "FG Incoming UDF Model"
Cohesion: 0.14
Nodes (3): m_bom_det_pm, m_bom, BomToolService

### Community 114 - "Approval & Status Workflow"
Cohesion: 0.50
Nodes (4): ApprovalEngine, Standard Status Workflow & State Machine, Paperless Multi-Level Approval & Mobile Approval, Period-Based FG Pricelist

### Community 115 - "Security, RBAC & Audit"
Cohesion: 0.50
Nodes (4): Audit Trail & Structured Logging, Security Design (Sanctum, 2FA, vendor guard, period lock), Granular RBAC, Menu/User Privilege & TOTP 2FA, robots.txt Open Crawl Policy

### Community 117 - "AP Payment Model"
Cohesion: 0.15
Nodes (3): MenuController, menus, MenuService

### Community 118 - "AR Receipt Model"
Cohesion: 0.15
Nodes (3): prc_gr_serial, wh_out_detail, wh_rem_detail

### Community 122 - "PO Schedule Model"
Cohesion: 0.50
Nodes (4): post-create-project-cmd, @php artisan key:generate --ansi, @php artisan migrate --graceful --ansi, @php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\

### Community 124 - "Cutting Pallet Process Model"
Cohesion: 0.18
Nodes (3): AdjustmentController, wh_adj_main, StockAdjustmentService

### Community 125 - "Downtime Cutting Detail"
Cohesion: 0.05
Nodes (17): UserFactory, AccountingSeeder, AdminUserManagementSeeder, DatabaseSeeder, DemandSeeder, DemoCalendar, FulfilmentSeeder, MasterDataSeeder (+9 more)

### Community 126 - "General Store Out Detail"
Cohesion: 0.31
Nodes (6): TYPE_LABEL, TYPE_STYLE, TypeBadge(), useWhsItems(), WHS_TYPES, WhsItemSelect()

### Community 127 - "General Store Request Detail"
Cohesion: 0.03
Nodes (25): eng_ecn_det, m_fg_downgrade_map, m_inspection_param, m_item_inspection, m_team, m_uom, npd_bom_det, npd_cost_det (+17 more)

### Community 171 - "Queue Config"
Cohesion: 0.21
Nodes (3): AssetController, ast_depre, ast_main

### Community 172 - "Sanctum Config"
Cohesion: 0.33
Nodes (6): Subcont Flow (PO Subcont → DN → Receipt → AP), TaxEngine (calcVat), e-Faktur / Coretax Integration, PPh 23 Subcont & e-Bupot, PPN under PMK 131/2024 (DPP Nilai Lain 11/12), Subcontractor Portal & Virtual Location

### Community 186 - "sum_stock_pm"
Cohesion: 0.20
Nodes (3): prd_scrap_decision, ScrapService, scrapFixture()

### Community 189 - "prc_gr_serial"
Cohesion: 0.22
Nodes (4): qc_incoming_det, qc_incoming_main, QasService, Illuminate\Support\Collection

### Community 191 - "tr_dt_cut_detail"
Cohesion: 0.19
Nodes (3): MaterialIssueService, NumberingService, UomConversionService

### Community 199 - "prc_inv_detail"
Cohesion: 0.14
Nodes (8): GenerateCogmJob, MinStockAlertJob, QuotaAlertJob, RunCrpJob, RunMrpJob, cst_cogm, Illuminate\Contracts\Queue\ShouldQueue, Illuminate\Foundation\Queue\Queueable

### Community 233 - "AlertPage.jsx"
Cohesion: 0.20
Nodes (4): status_id, FoundationSeeder, menuId(), staffUser()

### Community 234 - "prd_wo_serial_pm"
Cohesion: 0.27
Nodes (5): DynamicForm(), CrudPage(), initialValues(), NOTE: `items` is handled by its own tabbed screen (features/engineering/ItemPage, RESOURCES

### Community 237 - "keywords"
Cohesion: 0.12
Nodes (4): NpdDocController, AbnormalController, OutgoingController, npd_doc

### Community 241 - "sub_dn_detail"
Cohesion: 0.25
Nodes (5): calcLine(), EMPTY, NEW_LINE, r2(), Row()

### Community 249 - "qc_incoming_det"
Cohesion: 0.29
Nodes (3): ImportTaxService, costSheet(), kmk()

### Community 253 - "MppPage.jsx"
Cohesion: 0.43
Nodes (6): addMonths(), fmtPeriod(), MppPage(), now, STCLR, toPeriod()

### Community 265 - "ValuationPage.jsx"
Cohesion: 0.40
Nodes (4): BASIS_LABEL, Card(), period(), ValuationPage()

### Community 266 - "keywords"
Cohesion: 0.67
Nodes (3): keywords, framework, laravel

### Community 268 - "SubcontDnPage.jsx"
Cohesion: 0.53
Nodes (4): fmtNum(), PoInfoCard(), SubcontDnPage(), today()

### Community 270 - "ApprovalPage.jsx"
Cohesion: 0.50
Nodes (3): ApprovalBadge(), LEVEL_LABEL, DOC_COLOR

### Community 272 - "FgIncomingPage.jsx"
Cohesion: 0.70
Nodes (4): CreateView(), FgIncomingPage(), fmtDate(), today()

### Community 273 - "FgOutgoingPage.jsx"
Cohesion: 0.60
Nodes (4): CreateView(), FgOutgoingPage(), fmtDate(), today()

## Ambiguous Edges - Review These
- `graphify-out Artifacts (graph.json, wiki, GRAPH_REPORT)` → `PRD ERP + MES Manufaktur Pipa v3.0`  [AMBIGUOUS]
  CLAUDE.md · relation: references
- `robots.txt Open Crawl Policy` → `Security Design (Sanctum, 2FA, vendor guard, period lock)`  [AMBIGUOUS]
  public/robots.txt · relation: conceptually_related_to

## Knowledge Gaps
- **239 isolated node(s):** `$schema`, `name`, `type`, `description`, `laravel` (+234 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **117 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `graphify-out Artifacts (graph.json, wiki, GRAPH_REPORT)` and `PRD ERP + MES Manufaktur Pipa v3.0`?**
  _Edge tagged AMBIGUOUS (relation: references) - confidence is low._
- **What is the exact relationship between `robots.txt Open Crawl Policy` and `Security Design (Sanctum, 2FA, vendor guard, period lock)`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **Why does `ApiResponse` connect `WMS Incoming/Outgoing API` to `Eloquent Factory Models`, `Laravel Discovery Config`, `Purchase Order & Goods Receipt`, `prc_cost_alloc`, `FG Stock & MRP Planning`, `WhsStockController`, `Shared React UI Components`, `API Core Services & Routing`, `Item Master API`, `Purchase Invoice & Landed Cost`, `Work Order API`, `Generic CRUD Controller`, `MES Cutting & Serial Trace`, `Frontend API Client & CRUD Pages`, `Asset Category & Cost Rate`, `Import Quota Management`, `Subcontract DN & GR`, `MES Terminal Pages`, `Demo Seeder Data Models`, `Demo Data Seeder Logic`, `Purchase Requisition`, `MPS Master Production Schedule`, `MPP Production Plan`, `Route Time Master`, `sls_inv_detail`, `Document Numbering Service`, `Artisan Console Entry`, `FG Pricelist Master`, `Process Main Routing Master`, `Cache Config`, `Queue Config`, `Machine Master Relations`, `Sales Order React Page`, `sub_po_detail`, `MesReportController.php`, `prd_cut_serial`, `Currency CRUD Controller`, `Machine CRUD Controller`, `Process CRUD Controller`, `UoM CRUD Controller`, `Contact CRUD Controller`, `FgIncomingPage.jsx`, `RM Stock Query API`, `prc_cost_detail`, `prc_inv_detail`, `Downtime Cutting Model`, `Category CRUD Controller`, `Permission Middleware`, `Cutting Detail Model`, `Warehouse Remnant Model`, `User Factory`, `BOM Process Model`, `Pricelist Detail Model`, `PR Detail Model`, `WO Detail PM Model`, `WO Detail RM Model`, `Sales Invoice Model`, `BOM Process Detail Model`, `Pallet Item Model`, `AlertPage.jsx`, `Pallet Master Model`, `Quota Item Model`, `Cost Allocation Model`, `Cost Detail Model`, `keywords`, `PM Stock Summary Model`, `Downtime Category Model`, `AP Payment Model`, `AR Receipt Model`, `Item PM Mapping Model`, `Item Customer Mapping`, `extra`, `Cutting Pallet Process Model`?**
  _High betweenness centrality (0.046) - this node is a cross-community bridge._
- **Why does `Controller` connect `API Core Services & Routing` to `UserController.php`, `Eloquent Factory Models`, `prc_cost_alloc`, `WhsStockController`, `FG Stock & MRP Planning`, `Shared React UI Components`, `WMS Incoming/Outgoing API`, `Purchase Invoice & Landed Cost`, `Work Order API`, `Generic CRUD Controller`, `MES Cutting & Serial Trace`, `Frontend API Client & CRUD Pages`, `Asset Category & Cost Rate`, `Import Quota Management`, `Subcontract DN & GR`, `MES Terminal Pages`, `Demo Seeder Data Models`, `Demo Data Seeder Logic`, `Purchase Requisition`, `MPS Master Production Schedule`, `MPP Production Plan`, `Route Time Master`, `sls_inv_detail`, `Document Numbering Service`, `FG Pricelist Master`, `Process Main Routing Master`, `Queue Config`, `Sales Order React Page`, `sub_po_detail`, `prd_cut_serial`, `Currency CRUD Controller`, `Machine CRUD Controller`, `Process CRUD Controller`, `UoM CRUD Controller`, `Contact CRUD Controller`, `RM Stock Query API`, `prc_cost_detail`, `Downtime Cutting Model`, `Category CRUD Controller`, `Permission Middleware`, `Cutting Detail Model`, `Warehouse Remnant Model`, `User Factory`, `BOM Process Model`, `Pricelist Detail Model`, `PR Detail Model`, `WO Detail PM Model`, `WO Detail RM Model`, `Sales Invoice Model`, `Remnant Detail Model`, `BOM Process Detail Model`, `Pallet Item Model`, `Pallet Master Model`, `tr_dt_pro_detail`, `Quota Item Model`, `Cost Detail Model`, `keywords`, `wh_gen_out_det`, `PM Stock Summary Model`, `Cost Allocation Model`, `Downtime Category Model`, `Item PM Mapping Model`, `Item Customer Mapping`, `Cutting Pallet Process Model`?**
  _High betweenness centrality (0.025) - this node is a cross-community bridge._
- **Why does `BizException` connect `Machine Master Relations` to `Eloquent Factory Models`, `Purchase Order & Goods Receipt`, `Sales DO & FG Outgoing`, `Shared React UI Components`, `API Core Services & Routing`, `WMS Incoming/Outgoing API`, `Purchase Invoice & Landed Cost`, `Work Order API`, `MES Cutting & Serial Trace`, `Asset Category & Cost Rate`, `Subcontract DN & GR`, `MES Terminal Pages`, `Demo Data Seeder Logic`, `Purchase Requisition`, `MPS Master Production Schedule`, `MPP Production Plan`, `Route Time Master`, `Artisan Console Entry`, `Process Main Routing Master`, `Queue Config`, `Sales Order React Page`, `sub_po_detail`, `sum_stock_pm`, `MesReportController.php`, `prc_gr_serial`, `prd_cut_serial`, `Currency CRUD Controller`, `Contact CRUD Controller`, `Process CRUD Controller`, `UoM CRUD Controller`, `tr_dt_cut_detail`, `prc_cost_detail`, `GR Detail Model`, `Downtime Cutting Model`, `Category CRUD Controller`, `Permission Middleware`, `Cutting Detail Model`, `Warehouse Remnant Model`, `BOM Process Model`, `Pricelist Detail Model`, `PR Detail Model`, `WO Detail PM Model`, `WO Detail RM Model`, `Sales Invoice Model`, `tr_out_fg_det`, `Pallet Master Model`, `Quota Item Model`, `Cost Allocation Model`, `Cost Detail Model`, `keywords`, `PM Stock Summary Model`, `FG Incoming UDF Model`, `Downtime Category Model`, `AP Payment Model`, `AR Receipt Model`, `Item Customer Mapping`, `tr_dt_cut_detail`, `Cutting Pallet Process Model`?**
  _High betweenness centrality (0.023) - this node is a cross-community bridge._
- **Are the 471 inferred relationships involving `ApiResponse` (e.g. with `.index()` and `.openInvoices()`) actually correct?**
  _`ApiResponse` has 471 INFERRED edges - model-reasoned connections that need verification._
- **Are the 254 inferred relationships involving `AuditLogger` (e.g. with `.store()` and `.store()`) actually correct?**
  _`AuditLogger` has 254 INFERRED edges - model-reasoned connections that need verification._