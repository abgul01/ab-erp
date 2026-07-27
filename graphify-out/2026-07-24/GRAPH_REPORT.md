# Graph Report - .  (2026-07-24)

## Corpus Check
- 325 files · ~135,588 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 1804 nodes · 4718 edges · 185 communities (100 shown, 85 thin omitted)
- Extraction: 87% EXTRACTED · 12% INFERRED · 0% AMBIGUOUS · INFERRED: 589 edges (avg confidence: 0.8)
- Token cost: 109,848 input · 0 output

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
- Composer Test Script
- FG Flow & Kanban Concepts
- Document Numbering Service
- Deployment & NFR
- Modular Monolith Conventions
- FG Transfer Spec Group
- Multi-Vendor Sourcing

## God Nodes (most connected - your core abstractions)
1. `ApiResponse` - 223 edges
2. `AuditLogger` - 131 edges
3. `BizException` - 106 edges
4. `Controller` - 75 edges
5. `useAuth` - 73 edges
6. `apiError()` - 67 edges
7. `money()` - 53 edges
8. `api` - 43 edges
9. `Icon()` - 43 edges
10. `m_item` - 40 edges

## Surprising Connections (you probably didn't know these)
- `Graphify Knowledge-Graph Workflow` --semantically_similar_to--> `Laravel Boost (Agentic Development Tooling)`  [INFERRED] [semantically similar]
  CLAUDE.md → README.md
- `graphify-out Artifacts (graph.json, wiki, GRAPH_REPORT)` --references--> `PRD ERP + MES Manufaktur Pipa v3.0`  [AMBIGUOUS]
  CLAUDE.md → docs/PRD-ERP-Manufaktur-Pipa.md
- `robots.txt Open Crawl Policy` --conceptually_related_to--> `Security Design (Sanctum, 2FA, vendor guard, period lock)`  [AMBIGUOUS]
  public/robots.txt → docs/LLD-ERP-Manufaktur-Pipa.md
- `Tech Stack (Laravel 11 / React 18 / MySQL 8 / Redis)` --references--> `Laravel Framework`  [INFERRED]
  docs/PRD-ERP-Manufaktur-Pipa.md → README.md
- `WIP Pallet Traceability (prd_wip_pallet)` --semantically_similar_to--> `Serial Number Traceability (RM 1:pcs, PM 1:lot)`  [INFERRED] [semantically similar]
  docs/LLD-ERP-Manufaktur-Pipa.md → docs/PRD-ERP-Manufaktur-Pipa.md

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Core Shared Engines (Numbering, Approval, Tax, Journal, UoM, Serial)** — docs_lld_erp_manufaktur_pipa_numberingservice, docs_lld_erp_manufaktur_pipa_approvalengine, docs_lld_erp_manufaktur_pipa_taxengine, docs_lld_erp_manufaktur_pipa_journalengine, docs_lld_erp_manufaktur_pipa_uomconversionservice, docs_lld_erp_manufaktur_pipa_serialservice [EXTRACTED 1.00]
- **RM Serial Lifecycle: GRN generate → putaway → WO booking → cutting consume → scrap decision** — docs_lld_erp_manufaktur_pipa_grn_serial_flow, docs_lld_erp_manufaktur_pipa_putaway_layout, docs_lld_erp_manufaktur_pipa_woallocationservice, docs_lld_erp_manufaktur_pipa_trcutservice, docs_lld_erp_manufaktur_pipa_scrapservice, docs_lld_erp_manufaktur_pipa_wh_serial_table [EXTRACTED 1.00]
- **Indonesian Tax Compliance Cluster (PPN PMK 131/2024, Coretax e-Faktur, PPh 22/23 e-Bupot, landed cost)** — docs_prd_erp_manufaktur_pipa_ppn_pmk131, docs_prd_erp_manufaktur_pipa_coretax_efaktur, docs_prd_erp_manufaktur_pipa_pph23_ebupot, docs_prd_erp_manufaktur_pipa_landed_cost_sheet, docs_lld_erp_manufaktur_pipa_taxengine [EXTRACTED 1.00]

## Communities (185 total, 85 thin omitted)

### Community 0 - "Eloquent Factory Models"
Cohesion: 0.04
Nodes (24): acc_ap_pay_det, acc_ar_rec_det, acc_journal_det, acc_journal_main, cst_cogm, m_currency, m_i_category, m_maker_m (+16 more)

### Community 1 - "Eloquent Model Base Layer"
Cohesion: 0.04
Nodes (26): acc_coa, acc_period, m_bom_det_rm, m_cont_categ, m_defective, m_function_m, m_p_type, m_rate (+18 more)

### Community 2 - "Purchase Order & Goods Receipt"
Cohesion: 0.07
Nodes (7): GrController, PoController, RejectController, prc_gr_main, prc_gr_reject, prc_po_main, self

### Community 3 - "Sales DO & FG Outgoing"
Cohesion: 0.07
Nodes (7): DoController, SoController, FgOutgoingController, sls_do_detail, sls_do_main, sls_so_main, tr_out_fg_main

### Community 4 - "FG Stock & MRP Planning"
Cohesion: 0.06
Nodes (8): MrpController, FgIncomingController, prd_mrp_detail, prd_mrp_main, tr_inc_fg_det, tr_inc_fg_main, FgStockService, PlanningService

### Community 5 - "React App Shell & Auth"
Cohesion: 0.10
Nodes (47): apiError(), App(), ProtectedRoute(), CrudPage(), ItemPage(), ProcessMainPage(), RouteTimePage(), useOptions() (+39 more)

### Community 6 - "Shared React UI Components"
Cohesion: 0.16
Nodes (25): DataTable(), Icon(), MAP, Modal(), PickerModal(), EMPTY, CellInput(), ItemSelect() (+17 more)

### Community 8 - "WMS Incoming/Outgoing API"
Cohesion: 0.07
Nodes (8): SalesInvoiceController, IncomingController, OutgoingController, RemainingController, wh_out_detail, ApiResponse, Illuminate\Contracts\Pagination\LengthAwarePaginator, Illuminate\Http\JsonResponse

### Community 10 - "Purchase Invoice & Landed Cost"
Cohesion: 0.11
Nodes (4): CostController, InvoiceController, prc_cost_main, prc_inv_main

### Community 12 - "NPM Package Manifest"
Cohesion: 0.06
Nodes (34): axios, concurrently, laravel-vite-plugin, lucide-react, dependencies, axios, lucide-react, react (+26 more)

### Community 13 - "Generic CRUD Controller"
Cohesion: 0.10
Nodes (4): CrudController, ContactCategoryController, MakerController, TaxController

### Community 14 - "MES Cutting & Serial Trace"
Cohesion: 0.19
Nodes (5): BizException, CuttingController, prd_wo_serial_rm, tr_cut_serial, RuntimeException

### Community 15 - "Frontend API Client & CRUD Pages"
Cohesion: 0.12
Nodes (15): api, DynamicForm(), initialValues(), NOTE: `items` is handled by its own tabbed screen (features/engineering/ItemPage, RESOURCES, EMPTY, STATUSES, STATUSES (+7 more)

### Community 16 - "Asset Category & Cost Rate"
Cohesion: 0.16
Nodes (7): AssetCategoryController, CogmController, CostRateController, MesReportController, cst_rate, m_asset_categ, Illuminate\Http\Request

### Community 17 - "Import Quota Management"
Cohesion: 0.14
Nodes (4): QuotaController, m_quota, prc_quota_txn, QuotaService

### Community 18 - "Subcontract DN & GR"
Cohesion: 0.15
Nodes (3): SubcontController, sub_dn_main, sub_gr_main

### Community 19 - "MES Terminal Pages"
Cohesion: 0.17
Nodes (16): ChooseGrModal(), money(), CuttingPage(), today(), MesReportPage(), thisPeriod(), KplModal(), Stepper() (+8 more)

### Community 21 - "Demo Seeder Data Models"
Cohesion: 0.11
Nodes (3): m_bom, LineTax, PricelistService

### Community 25 - "Fixed Asset & Depreciation"
Cohesion: 0.19
Nodes (3): AssetController, ast_depre, ast_main

### Community 26 - "MPP Production Plan"
Cohesion: 0.27
Nodes (3): MppController, prd_mpp, AuditLogger

### Community 27 - "Auth, Menus & Permissions"
Cohesion: 0.18
Nodes (3): AuthController, menus, MenuService

### Community 31 - "Composer Manifest Metadata"
Cohesion: 0.14
Nodes (13): autoload-dev, psr-4, description, keywords, license, minimum-stability, name, prefer-stable (+5 more)

### Community 34 - "Composer Lifecycle Scripts"
Cohesion: 0.15
Nodes (13): scripts, dev, post-create-project-cmd, post-update-cmd, pre-package-uninstall, Composer\\Config::disableProcessTimeout, Illuminate\\Foundation\\ComposerScripts::prePackageUninstall, npx concurrently -c \"#93c5fd,#c4b5fd,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1\" \"npm run dev\" --names='server,queue,vite (+5 more)

### Community 35 - "Core Engine Design Concepts"
Cohesion: 0.21
Nodes (13): GRN Serial Flow (generate → print → actualize → confirm), NumberingService (race-safe document numbers), Optimistic Locking & Explicit Row Locks, QuotaService (reserve / actualize), ScrapService (evaluate / decide), SerialService (generateForGrLine, actualize, consume), TaxEngine (calcVat), Testing Strategy (Pest unit → Playwright E2E) (+5 more)

### Community 36 - "User Model & Permissions"
Cohesion: 0.24
Nodes (5): User, Illuminate\Database\Eloquent\Relations\HasMany, Illuminate\Foundation\Auth\User, Illuminate\Notifications\Notifiable, Laravel\Sanctum\HasApiTokens

### Community 37 - "Work Order React Page"
Cohesion: 0.21
Nodes (8): blank(), SerialModal(), num(), SerialRmModal(), STATUS, today(), uid(), WoPage()

### Community 40 - "Database Seeder Entrypoint"
Cohesion: 0.33
Nodes (3): status_id, DatabaseSeeder, Illuminate\Database\Seeder

### Community 41 - "Item Master React Page"
Cohesion: 0.20
Nodes (6): COLUMNS, EMPTY, ItemSelect(), TABS, TYPES, useOptions()

### Community 44 - "Planning & Costing Engine Design"
Cohesion: 0.20
Nodes (10): COGM Calculation & Roll-Up per WO, CRP Loading Calculation, MRP Engine (low-level-code explosion), Queue Jobs & Scheduler (default | heavy), Subcont Flow (PO Subcont → DN → Receipt → AP), Domain Glossary (WOS, QAS, GRN, RFG, FCS, UMH, COGM), Planning Chain Forecast→MPS→MPP→MRP→CRP→WO/Kanban, PPh 23 Subcont & e-Bupot (+2 more)

### Community 51 - "Dev Dependencies"
Cohesion: 0.22
Nodes (9): require-dev, fakerphp/faker, laravel/pail, laravel/pao, laravel/pint, mockery/mockery, nunomaduro/collision, pestphp/pest (+1 more)

### Community 52 - "MES Dispatch & Traceability Design"
Cohesion: 0.28
Nodes (9): FCS → RFG Receiving Flow, client_uuid Idempotency Key, JournalEngine (post), MesDispatchService.resolve (TR_CUT vs TR_PRO), WIP Pallet Traceability (prd_wip_pallet), API Response Envelope & Business Error Codes, TrCutService.record, TrProService.record (+1 more)

### Community 53 - "RM Flow & Costing Concepts"
Cohesion: 0.25
Nodes (9): UomConversionService, Multi-Level BOM (FG as PM of another FG), COGM / HPP Actual Costing per WO, Dual UoM for Pipe RM (length mm + weight kg), FG Downgrade (NG finished good back to material), Import Cost Sheet / Landed Cost per Actual Weight, Raw Material Flow (MRP→PR→PO→GRN→QAS→Invoice→Cost Sheet), Scrap RM Candidate Rule (remaining < min BOM length) (+1 more)

### Community 55 - "Sales Order React Page"
Cohesion: 0.25
Nodes (5): calcLine(), EMPTY, NEW_LINE, r2(), Row()

### Community 60 - "Project Setup Scripts"
Cohesion: 0.25
Nodes (8): post-root-package-install, setup, composer install, npm install --ignore-scripts, npm run build, @php artisan key:generate, @php artisan migrate --force, @php -r \"file_exists('.env') || copy('.env.example', '.env');\

### Community 61 - "System Landscape & UI Design"
Cohesion: 0.25
Nodes (8): API Contract (/api/v1 and /api/mes/v1), React Frontend Design (DataTable, DocForm, ScanBox), Offline Terminal Design (service worker + IndexedDB queue), ERP + MES System Landscape (14 Modules), FTPI Visual Identity & UI/UX Direction, MES Offline Mode (PWA buffer + client_uuid idempotency), 7-Phase Implementation Roadmap, Single MySQL Database `ab-erp` for ERP and MES

### Community 74 - "Project Docs & Tech Stack"
Cohesion: 0.29
Nodes (7): graphify-out Artifacts (graph.json, wiki, GRAPH_REPORT), Graphify Knowledge-Graph Workflow, LLD ERP + MES Manufaktur Pipa v2.0, PRD ERP + MES Manufaktur Pipa v3.0, Tech Stack (Laravel 11 / React 18 / MySQL 8 / Redis), Laravel Boost (Agentic Development Tooling), Laravel Framework

### Community 75 - "Composer Config Section"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 76 - "MPP React Page"
Cohesion: 0.43
Nodes (6): addMonths(), fmtPeriod(), MppPage(), now, STCLR, toPeriod()

### Community 78 - "Permission Middleware"
Cohesion: 0.47
Nodes (3): CheckPermission, Closure, Symfony\Component\HttpFoundation\Response

### Community 84 - "User Factory"
Cohesion: 0.47
Nodes (3): UserFactory, Illuminate\Database\Eloquent\Factories\Factory, static

### Community 85 - "Warehouse Serial Schema Design"
Cohesion: 0.47
Nodes (6): Item–Customer Mapping Validation (m_item_customer), Fixes vs Reference Application Schema, Putaway & Visual Warehouse Layout, wh_serial / wh_serial_movement / wh_stock_sum Schema, Existing Database Schema Baseline (m_/prc_/wh_/prd_/tr_ prefixes), General Store (non-material sparepart & consumable)

### Community 97 - "PSR-4 Autoload Map"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 98 - "PHP Runtime Requirements"
Cohesion: 0.40
Nodes (5): require, laravel/framework, laravel/sanctum, laravel/tinker, php

### Community 100 - "FG Incoming React Page"
Cohesion: 0.70
Nodes (4): CreateView(), FgIncomingPage(), fmtDate(), today()

### Community 114 - "Approval & Status Workflow"
Cohesion: 0.50
Nodes (4): ApprovalEngine, Standard Status Workflow & State Machine, Paperless Multi-Level Approval & Mobile Approval, Period-Based FG Pricelist

### Community 115 - "Security, RBAC & Audit"
Cohesion: 0.50
Nodes (4): Audit Trail & Structured Logging, Security Design (Sanctum, 2FA, vendor guard, period lock), Granular RBAC, Menu/User Privilege & TOTP 2FA, robots.txt Open Crawl Policy

### Community 116 - "MRP React Page"
Cohesion: 0.83
Nodes (3): MrpPage(), nextPeriods(), ym()

### Community 128 - "Laravel Discovery Config"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 129 - "Post Autoload Dump Script"
Cohesion: 0.67
Nodes (3): post-autoload-dump, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump, @php artisan package:discover --ansi

### Community 130 - "Composer Test Script"
Cohesion: 0.67
Nodes (3): test, @php artisan config:clear --ansi @no_additional_args, @php artisan test

### Community 160 - "FG Flow & Kanban Concepts"
Cohesion: 0.67
Nodes (3): Finished Good Flow (MPS→MPP→WO→Kanban→MES→FCS→RFG→DN→Invoice), Final Check Sheet (FCS / OQC traceability gate), Kanban Material Issue & Return

## Ambiguous Edges - Review These
- `graphify-out Artifacts (graph.json, wiki, GRAPH_REPORT)` → `PRD ERP + MES Manufaktur Pipa v3.0`  [AMBIGUOUS]
  CLAUDE.md · relation: references
- `robots.txt Open Crawl Policy` → `Security Design (Sanctum, 2FA, vendor guard, period lock)`  [AMBIGUOUS]
  public/robots.txt · relation: conceptually_related_to

## Knowledge Gaps
- **117 isolated node(s):** `$schema`, `name`, `type`, `description`, `laravel` (+112 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **85 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `graphify-out Artifacts (graph.json, wiki, GRAPH_REPORT)` and `PRD ERP + MES Manufaktur Pipa v3.0`?**
  _Edge tagged AMBIGUOUS (relation: references) - confidence is low._
- **What is the exact relationship between `robots.txt Open Crawl Policy` and `Security Design (Sanctum, 2FA, vendor guard, period lock)`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **Why does `ApiResponse` connect `WMS Incoming/Outgoing API` to `Purchase Order & Goods Receipt`, `Sales DO & FG Outgoing`, `FG Stock & MRP Planning`, `API Core Services & Routing`, `Item Master API`, `Purchase Invoice & Landed Cost`, `Work Order API`, `Generic CRUD Controller`, `MES Cutting & Serial Trace`, `Asset Category & Cost Rate`, `Import Quota Management`, `Subcontract DN & GR`, `WIP Processing & Pallets`, `Demo Seeder Data Models`, `Purchase Requisition`, `MPS Master Production Schedule`, `Fixed Asset & Depreciation`, `MPP Production Plan`, `Auth, Menus & Permissions`, `Route Time Master`, `MPS Reschedule Workflow`, `FG Pricelist Master`, `Process Main Routing Master`, `Sales Forecast`, `Sales Return`, `RM Stock Query API`, `Abnormal Decision API`?**
  _High betweenness centrality (0.043) - this node is a cross-community bridge._
- **Why does `User` connect `User Model & Permissions` to `Eloquent Factory Models`, `API Core Services & Routing`, `Database Seeder Entrypoint`, `Demo Seeder Data Models`, `Demo Data Seeder Logic`, `Auth, Menus & Permissions`?**
  _High betweenness centrality (0.032) - this node is a cross-community bridge._
- **Why does `prc_gr_main` connect `Purchase Order & Goods Receipt` to `Eloquent Factory Models`, `Eloquent Model Base Layer`, `API Core Services & Routing`?**
  _High betweenness centrality (0.023) - this node is a cross-community bridge._
- **Are the 218 inferred relationships involving `ApiResponse` (e.g. with `.login()` and `.logout()`) actually correct?**
  _`ApiResponse` has 218 INFERRED edges - model-reasoned connections that need verification._
- **Are the 129 inferred relationships involving `AuditLogger` (e.g. with `.login()` and `.logout()`) actually correct?**
  _`AuditLogger` has 129 INFERRED edges - model-reasoned connections that need verification._