# LLD — Sistem ERP + MES Manufaktur Pipa Besi

**Low-Level Design Document**
Versi 2.0 — 16 Juli 2026
Referensi: PRD v3.0 (skema v3: basis aplikasi existing, field & tabel dipertahankan)
Stack: Laravel 11 (PHP 8.3) · React 18 · MySQL 8.0 · Redis

---

## Daftar Isi

1. [Konvensi & Standar](#1-konvensi--standar)
2. [Struktur Project](#2-struktur-project)
3. [Database Design (DDL Detail)](#3-database-design-ddl-detail)
4. [Core Engines (Shared Services)](#4-core-engines-shared-services)
5. [Desain Detail per Alur Transaksi](#5-desain-detail-per-alur-transaksi)
6. [Modul MES dalam Satu Database & Offline Mode](#6-modul-mes-dalam-satu-database--offline-mode)
7. [API Contract Detail](#7-api-contract-detail)
8. [Frontend Design (React)](#8-frontend-design-react)
9. [Security Design](#9-security-design)
10. [Error Handling, Logging & Audit](#10-error-handling-logging--audit)
11. [Deployment & Infrastruktur](#11-deployment--infrastruktur)
12. [Strategi Testing](#12-strategi-testing)

---

## 1. Konvensi & Standar

### 1.1 Naming Convention

| Objek | Konvensi | Contoh |
|---|---|---|
| Tabel | snake_case + prefix modul | `wh_serial` |
| Prefix modul | `m_` master, `prc_` procurement, `wh_` warehouse (+`sum_` summary), `prd_` planning/WO, `tr_` produksi, `sls_` sales, `sub_` subcont, `qc_`, `acc_`, `cst_`, `ast_` — nama tabel/field aplikasi existing TIDAK diubah | `m_item`, `prc_gr_main`, `wh_out_main`, `tr_cut_main` |
| Tabel dokumen header–detail | `{doc}_main` / `{doc}_detail` | `prc_po_main`, `prc_po_detail`, `prc_gr_main`, `prc_gr_detail` |
| Kolom FK | `{singular}_id` | `vendor_id` |
| Model | PascalCase singular | `MaterialSerial` |
| Controller | `{Entity}Controller` per modul | `Procurement\PurchaseOrderController` |
| Service | `{Domain}Service` / `{Domain}Engine` | `QuotaService`, `MrpEngine` |
| Event | Past tense | `GrnPosted`, `ScanResultRecorded` |
| Route API | kebab-case plural | `/api/v1/purchase-orders` |
| Permission key | `{modul}.{menu}.{aksi}` | `procurement.po.approve` |

### 1.2 Tipe Data Standar

| Kebutuhan | Tipe |
|---|---|
| PK / FK | `BIGINT UNSIGNED AUTO_INCREMENT` |
| Qty pcs | `DECIMAL(15,3)` |
| Panjang (mm) | `DECIMAL(12,2)` |
| Berat (kg) | `DECIMAL(12,3)` |
| Ton (kuota) | `DECIMAL(12,3)` |
| Uang (IDR & valas) | `DECIMAL(18,2)` + `currency_id` + `rate DECIMAL(15,6)` |
| Persentase / faktor pajak | `DECIMAL(8,6)` |
| Status | `VARCHAR(30)` + konstanta PHP Enum (bukan MySQL ENUM, agar mudah tambah status) |
| UUID idempoten | `CHAR(36)` |

### 1.3 Kolom Baku Semua Tabel Transaksi

```sql
id BIGINT UNSIGNED PK, doc_no VARCHAR(30) UNIQUE, doc_date DATE,
status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
created_by BIGINT, updated_by BIGINT, approved_by BIGINT NULL, approved_at DATETIME NULL,
posted_at DATETIME NULL, created_at, updated_at
```

Master: + `deleted_at` (soft delete). Semua perubahan tercatat ke `audit_logs` via Model Observer.

### 1.4 Status Workflow Baku

```
DRAFT → SUBMITTED → APPROVED → (RELEASED/OPEN) → PARTIAL → COMPLETED → CLOSED
                  ↘ REJECTED                              ↘ CANCELLED
```

Dokumen ber-posting jurnal menambah: `POSTED`. Transisi divalidasi state machine per dokumen (`app/States/{Doc}State.php`); transisi ilegal → HTTP 409.

### 1.5 Response Envelope & Error Code

```json
// sukses
{ "data": {...}, "meta": { "page": 1, "per_page": 20, "total": 154 } }
// gagal
{ "errors": [ { "code": "QUOTA_INSUFFICIENT", "message": "Saldo kuota IMP-2026-01 tinggal 2.150 ton...", "field": null } ] }
```

| HTTP | Pemakaian |
|---|---|
| 422 | Validasi field / aturan bisnis (mis. `MAX_2_USERS`, `SPEC_GROUP_MISMATCH`, `ITEM_CUSTOMER_NOT_ALLOWED`) |
| 409 | Konflik state (dokumen sudah posted, period locked, optimistic lock) |
| 403 | Permission / privilege |
| 423 | Period lock accounting |

---

## 2. Struktur Project

### 2.1 Backend (Laravel — modular monolith)

```
app/
├── Modules/
│   ├── Admin/          (user, role, menu, approval, numbering, audit)
│   ├── MasterData/     (general data master, maintenance/machine)
│   ├── Engineering/    (item, maker, bom, wos, umh, ecn)
│   ├── OrderMgmt/      (customer, pricelist, forecast, so)
│   ├── Planning/       (mps, mpp, mrp, crp, kanban)
│   ├── Qas/            (inspection, defective)
│   ├── Procurement/    (vendor, quotation, pr, po, quota, grn, costsheet)
│   ├── WmsRm/          (serial, lot, issue, scrap, opname)
│   ├── Mes/            (menuloading, scan, ng, breakdown, fcs, repairfg)
│   ├── Subcont/        (po subcont, delivery, receipt, portal)
│   ├── WmsFg/          (fglot, rfg, transfer, downgrade)
│   ├── Shipping/       (schedule, do, shipping, packinglist)
│   ├── Asset/
│   ├── Costing/        (valuation, cogm)
│   └── Accounting/     (coa, journal, ap, ar, tax, closing)
│   └── setiap modul:  Http/Controllers, Http/Requests, Models, Services,
│                      Events, Listeners, Policies, routes.php, database/migrations
├── Core/               (shared engines — Bab 4)
│   ├── Numbering/  ├── Approval/  ├── Tax/  ├── Journal/
│   ├── Uom/        ├── Serial/    └── Notification/
└── Support/            (helpers, traits: HasDocNo, HasApproval, Auditable)
```

- Registrasi modul via `ModuleServiceProvider` (auto-discover routes, migrations, policies per modul).
- Komunikasi antar modul **hanya** lewat Service interface / Event — controller tidak boleh memanggil Model modul lain langsung.

### 2.2 Job & Scheduler (Queue Redis)

| Job | Trigger | Queue |
|---|---|---|
| `RunMrpJob`, `RunCrpJob` | manual / cron malam | `heavy` |
| `GenerateCogmJob` | closing / manual | `heavy` |
| `ExportEfakturJob`, `ReportJob` | manual | `default` |
| `QuotaAlertJob`, `MinStockAlertJob` | cron per jam | `default` |

---

## 3. Database Design (DDL Detail)

> DDL lengkap ada di file migrasi; bagian ini menetapkan struktur final tabel kunci + index. FK selalu ber-index. Engine InnoDB, charset `utf8mb4_unicode_ci`.

### 3.1 Engineering — Item, BOM, WOS

```sql
CREATE TABLE m_item (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_code VARCHAR(30) NOT NULL UNIQUE,
  item_name VARCHAR(150) NOT NULL,
  item_type VARCHAR(15) NOT NULL,              -- RM|PM|FG|WIP|CONSUMABLE|SERVICE
  category_id BIGINT UNSIGNED NULL, product_family_id BIGINT UNSIGNED NULL,
  maker_id BIGINT UNSIGNED NULL,
  serial_mode VARCHAR(10) NOT NULL DEFAULT 'NONE',   -- PER_PCS|PER_LOT|NONE
  uom_primary_id BIGINT UNSIGNED NOT NULL,
  uom_secondary_id BIGINT UNSIGNED NULL,        -- RM pipa: kg
  out_diameter DECIMAL(10,3) NULL, thickness DECIMAL(10,3) NULL,
  width DECIMAL(10,3) NULL, height DECIMAL(10,3) NULL,
  spec_group VARCHAR(30) NULL,                  -- aturan FG transfer
  hs_code VARCHAR(20) NULL, material_grade VARCHAR(30) NULL,
  weight_actual_based TINYINT(1) NOT NULL DEFAULT 0,
  std_length_mm DECIMAL(12,2) NULL,             -- panjang standar batang (estimasi MRP)
  std_weight_kg DECIMAL(12,3) NULL,
  min_stock DECIMAL(15,3) DEFAULT 0, lead_time_days SMALLINT DEFAULT 0,
  std_cost DECIMAL(18,2) DEFAULT 0, is_active TINYINT(1) DEFAULT 1,
  status VARCHAR(20) DEFAULT 'DRAFT',           -- approval master
  created_by BIGINT, approved_by BIGINT NULL, deleted_at DATETIME NULL,
  created_at DATETIME, updated_at DATETIME,
  INDEX idx_items_type (item_type), INDEX idx_items_specgroup (spec_group)
);

CREATE TABLE m_bom_detail (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bom_id BIGINT UNSIGNED NOT NULL, component_item_id BIGINT UNSIGNED NOT NULL,
  qty_per DECIMAL(15,6) NOT NULL, uom_id BIGINT UNSIGNED NOT NULL,
  length_per_pcs_mm DECIMAL(12,2) NULL,         -- komponen RM pipa; basis scrap & MRP
  scrap_pct DECIMAL(5,2) DEFAULT 0,
  UNIQUE KEY uq_bom_component (bom_id, component_item_id),
  INDEX idx_boml_component (component_item_id)  -- query min_bom_length & where-used
);

CREATE TABLE m_wos_detail (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  wos_id BIGINT UNSIGNED NOT NULL, sequence SMALLINT NOT NULL,
  process_id BIGINT UNSIGNED NOT NULL,
  cycle_time_sec DECIMAL(10,2) NOT NULL, setup_time_min DECIMAL(8,2) DEFAULT 0,
  umh_pp DECIMAL(10,4) NULL, umh_hav DECIMAL(10,4) NULL,
  is_subcont TINYINT(1) DEFAULT 0, subcont_vendor_id BIGINT UNSIGNED NULL,
  subcont_price DECIMAL(18,2) NULL, qc_point TINYINT(1) DEFAULT 0,
  UNIQUE KEY uq_wos_seq (wos_id, sequence)
);
-- Constraint aplikasi (WosService::validate): sequence=1 harus process.is_cutting=1
-- bila BOM memuat komponen RM ber-serial_mode PER_PCS.
```

### 3.2 Kuota Impor

```sql
CREATE TABLE prc_quota_txn (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quota_id BIGINT UNSIGNED NOT NULL,
  ref_type VARCHAR(15) NOT NULL,     -- PO_RESERVE | GR_ACTUAL | RELEASE | ADJUSTMENT
  ref_id BIGINT UNSIGNED NOT NULL,
  ton DECIMAL(12,3) NOT NULL,        -- selalu positif
  sign TINYINT NOT NULL,             -- -1 mengurangi saldo, +1 mengembalikan
  note VARCHAR(200) NULL, created_by BIGINT, created_at DATETIME,
  INDEX idx_iqt_quota (quota_id), INDEX idx_iqt_ref (ref_type, ref_id)
);
-- Saldo = total_quota_ton + SUM(ton * sign). Dihitung dgn SELECT ... FOR UPDATE
-- pada baris m_quota saat reserve/actualize (serialisasi akses).
```

### 3.3 Serial & Stok

```sql
CREATE TABLE wh_serial (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  serial_no VARCHAR(40) NOT NULL UNIQUE,
  item_id BIGINT UNSIGNED NOT NULL, gr_serial_id BIGINT UNSIGNED NULL,
  origin VARCHAR(15) NOT NULL DEFAULT 'GRN',   -- GRN | DOWNGRADE | OPNAME
  heat_no VARCHAR(40) NULL,
  initial_length_mm DECIMAL(12,2) NOT NULL, initial_weight_kg DECIMAL(12,3) NOT NULL,
  remaining_length_mm DECIMAL(12,2) NOT NULL, remaining_weight_kg DECIMAL(12,3) NOT NULL,
  unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0,  -- landed cost per kg
  warehouse_id BIGINT UNSIGNED NOT NULL, rack_id BIGINT UNSIGNED NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'AVAILABLE',
  -- AVAILABLE | ISSUED | SCRAP_CANDIDATE | SCRAP | CONSUMED
  version INT UNSIGNED NOT NULL DEFAULT 0,     -- optimistic lock
  created_at DATETIME, updated_at DATETIME,
  INDEX idx_ms_item_status (item_id, status), INDEX idx_ms_wh (warehouse_id)
);

CREATE TABLE wh_serial_movement (                 -- kartu mutasi per serial (traceability)
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  serial_id BIGINT UNSIGNED NOT NULL,
  ref_type VARCHAR(20) NOT NULL,               -- GRN|ISSUE|RETURN|CUTTING|SCRAP|DOWNGRADE|OPNAME|SUBCONT_OUT|SUBCONT_IN
  ref_id BIGINT UNSIGNED NOT NULL,
  delta_length_mm DECIMAL(12,2) NOT NULL DEFAULT 0,
  delta_weight_kg DECIMAL(12,3) NOT NULL DEFAULT 0,
  balance_length_mm DECIMAL(12,2) NOT NULL, balance_weight_kg DECIMAL(12,3) NOT NULL,
  created_by BIGINT, created_at DATETIME,
  INDEX idx_sm_serial (serial_id), INDEX idx_sm_ref (ref_type, ref_id)
);

CREATE TABLE wh_stock_sum (                   -- agregat cepat utk list & MRP (di-maintain event)
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT UNSIGNED NOT NULL, warehouse_id BIGINT UNSIGNED NOT NULL,
  qty_pcs DECIMAL(15,3) DEFAULT 0, qty_length_mm DECIMAL(15,2) DEFAULT 0,
  qty_weight_kg DECIMAL(15,3) DEFAULT 0, stock_value DECIMAL(18,2) DEFAULT 0,
  UNIQUE KEY uq_sb (item_id, warehouse_id)
);
```

### 3.4 MES (modul dalam database `ab-erp`, prefix `mes_`)

```sql
CREATE TABLE tr_scan_main (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_uuid CHAR(36) NOT NULL UNIQUE,        -- idempotency (offline buffer terminal)
  wo_id BIGINT UNSIGNED NOT NULL,              -- FK langsung ke prd_wo_main (satu database)
  wo_detail_id BIGINT UNSIGNED NOT NULL,       -- FK prd_wo_detail (langkah proses)
  process_is_cutting TINYINT(1) NOT NULL,
  txn_date DATE NOT NULL, start_time DATETIME, end_time DATETIME,
  qty_ok DECIMAL(15,3) NOT NULL DEFAULT 0, qty_ng DECIMAL(15,3) NOT NULL DEFAULT 0,
  created_at DATETIME,
  INDEX idx_msr_wo (wo_id)
);
CREATE TABLE tr_scan_detail_user    (id PK, scan_result_id FK, user_id FK, share_pct);
CREATE TABLE tr_scan_detail_machine (id PK, scan_result_id FK, machine_id FK, qty_ok);
-- CHECK aplikasi: is_cutting → users=1, m_machine>=1 ; else users<=2, m_machine<=2
-- Tabel lain: tr_menu_loading, tr_issue_scan, tr_ng,
-- tr_downtime, tr_fcs_main, tr_repair_fg — semua ber-FK langsung ke tabel ERP.
```

### 3.5 Approval & Numbering (Core)

```sql
CREATE TABLE doc_numberings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  doc_type VARCHAR(30) NOT NULL, prefix VARCHAR(10) NOT NULL,
  period CHAR(6) NOT NULL,                      -- YYYYMM (reset bulanan)
  last_number INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_dn (doc_type, period)
);
CREATE TABLE approvals (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  doc_type VARCHAR(30) NOT NULL, doc_id BIGINT UNSIGNED NOT NULL,
  level TINYINT NOT NULL, required_role_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(15) NOT NULL DEFAULT 'PENDING', -- PENDING|APPROVED|REJECTED|SKIPPED
  acted_by BIGINT NULL, acted_at DATETIME NULL, note VARCHAR(300) NULL,
  INDEX idx_appr_doc (doc_type, doc_id), INDEX idx_appr_pending (status, required_role_id)
);
```

> Tabel domain lain (PO, GRN, WO, SO, DO, jurnal, asset, dst.) mengikuti struktur PRD Bab 8 + kolom baku 1.3; migrasi per modul.

---

## 4. Core Engines (Shared Services)

### 4.1 NumberingService

```php
// atomic, race-safe
public function next(string $docType): string {
    return DB::transaction(function () use ($docType) {
        $row = DocNumbering::where(...)->lockForUpdate()->firstOrCreate([...]);
        $row->increment('last_number');
        return sprintf('%s/%s/%s/%05d', $row->prefix, now()->format('Y'), now()->format('m'), $row->last_number);
    });
}
```

### 4.2 ApprovalEngine

- `submit(doc)`: baca `approval_flows` (doc_type + range nilai) → generate baris `approvals` per level → notifikasi (in-app, email, Mobile Approval).
- `approve(doc, user)`: validasi role user = `required_role_id` level aktif → set APPROVED → bila level terakhir, panggil `$doc->onFullyApproved()` (state machine). `reject` → dokumen REJECTED, sisa level SKIPPED.
- Trait `HasApproval` di model dokumen; endpoint seragam `POST /{doc}/{id}/approve|reject`.

### 4.3 TaxEngine

```php
public function calcVat(Money $sellingPrice, TaxCode $tc, Carbon $date): VatResult {
    $tc = $tc->effectiveAt($date);                    // tarif per tanggal efektif
    $dpp = $tc->is_luxury ? $sellingPrice : $sellingPrice->times($tc->dpp_factor); // 11/12
    return new VatResult(dpp: $dpp, dppNilaiLain: !$tc->is_luxury,
                         vat: $dpp->times($tc->rate_pct / 100));                   // 12%
}
// Non-mewah: PPN = harga × 11/12 × 12%  (efektif 11%) — PMK 131/2024
// Pembulatan: round half-up ke rupiah penuh di level dokumen (bukan per baris), selisih ke baris terakhir.
```

### 4.4 JournalEngine

- `post(refType, refId, lines[])`: baca `acc_journal_setup` (mapping Dr/Cr per refType) → validasi period lock (`acc_period.status='OPEN'`, else 423) → insert `acc_journal_main` + `acc_journal_detail` balanced (assert ΣD=ΣK) → event `JournalPosted`.
- Semua posting idempotent: unique key `(ref_type, ref_id, journal_type)`.

### 4.5 UomConversionService

- Konteks `PO` dan `ISSUE_PROD` (tabel `m_uom_conversion`). Untuk RM pipa konversi **aktual per serial**: `kg_per_mm = remaining_weight_kg / remaining_length_mm` (bukan faktor master). Faktor master (std_length/std_weight) hanya untuk estimasi PO & MRP.

### 4.6 SerialService

```php
public function generateForGrLine(GrnDetail $line): Collection {
    // dipanggil SAAT GR DIBUAT: generate qty_received serial (status=GENERATED)
    // RM PER_PCS: 1 serial/pcs tanpa ukuran; PM PER_LOT: 1 serial lot — ukuran diisi saat pengecekan
    // serial_no: {ITEMCODE}-{YYMM}-{SEQ5}; dokumen GR + daftar serial + label ZPL ke PrintService
}
public function actualize(GrnDetailSerial $s, ?float $mm, ?float $kg, string $status, ?string $reason): void {
    // saat pengecekan: isi length/weight aktual, set OK|NG|VOID (+alasan); rekap ke prc_gr_detail
}
public function consume(MaterialSerial $s, float $usedMm): SerialMovement {
    $kgPerMm  = $s->remaining_weight_kg / $s->remaining_length_mm;
    $usedKg   = round($usedMm * $kgPerMm, 3);
    $s->remaining_length_mm -= $usedMm;  $s->remaining_weight_kg -= $usedKg;
    $this->scrapService->evaluate($s);   // Bab 5.4
    // update dgn optimistic lock: WHERE id=? AND version=? ; version++
}
```

---

## 5. Desain Detail per Alur Transaksi

### 5.1 PO Impor + Reservasi Kuota

```
User → POController.store ─► POService.create (DRAFT)
User → POController.release
  ├─ ApprovalEngine cek fully approved
  ├─ QuotaService.reserve(po):
  │    DB::transaction:
  │      quota = ImportQuota::lockForUpdate()
  │      saldo = quota.total + SUM(txns.ton*sign)
  │      estTon = Σ(prc_po_detail.est_weight_kg)/1000
  │      saldo < estTon → abort 422 QUOTA_INSUFFICIENT (override: permission quota.override + approval khusus)
  │      insert txn(PO_RESERVE, -estTon)
  └─ po.status = RELEASED → notifikasi vendor (email/portal)
```

### 5.2 GRN: Generate Serial di Depan → Print → Aktualisasi → Invoice

```
1. GrnController.store (ref PO / surat jalan vendor)
   └─ GrnService.generateSerials LANGSUNG saat GR dibuat:
      1 baris prc_gr_serial per serial (status=GENERATED;
      RM: length/weight boleh kosong/estimasi dulu; PM: qty_lot) → prc_gr_main.status = SERIAL_GENERATED
2. GrnController.print
   └─ cetak dokumen GR + LAMPIRAN DAFTAR SERIAL + label QR per serial (ZPL) → status = PRINTED
3. Pengecekan fisik (QAS) — SEMUA PERUBAHAN DILAKUKAN DI GR (status = CHECKING):
   ├─ input/koreksi length_mm & weight_kg AKTUAL per serial (scan label → isi ukur/timbang)
   ├─ serial tidak sesuai/NG → status=NG (+ng_reason) → MaterialRejection/RTV + vendor rating
   ├─ salah generate / barang tidak datang → status=VOID
   └─ qty_accept / qty_reject + total_weight_kg + total_length_mm prc_gr_detail dihitung
      otomatis (rekap) dari status & ukuran aktual serial
4. GrnService.confirm → post (hanya serial status=OK):
   ├─ buat wh_serial / wh_lot (stok berjalan) ber-FK gr_serial_id
   ├─ IMPORT: QuotaService.actualize berdasar Σ weight_kg aktual serial OK
   ├─ JournalEngine.post (Dr Persediaan @PO price, Cr GR/IR) → status = CONFIRMED/POSTED
   └─ event GrnPosted → notifikasi
5. ApInvoiceService — invoice DIINPUT SETELAH GR confirmed, sesuai kedatangan aktual:
   3-way match PO ↔ GRN (qty, berat & panjang aktual) ↔ Invoice vendor → prc_gr_main.status = INVOICED
6. CostSheetService.finalize(po): alokasi biaya per berat aktual → update unit_cost per serial
   + jurnal penyesuaian nilai persediaan
```

### 5.3 Kanban Issue & Konsumsi Serial (dual UoM)

```
MES CreateKanban(wo) → WMS scan-out serial:
  MaterialIssueService.issue(kanban, serialNo, usedMm?):
    ├─ serial.status == AVAILABLE || (ISSUED utk WO sama)  else 422
    ├─ full-issue (batang utuh dibawa ke mesin): status=ISSUED, tanpa potong remaining
    └─ konsumsi dicatat saat hasil cutting masuk (Bab 5.5) atau saat return:
       consume() memotong remaining_length & remaining_weight (kg proporsional aktual serial)
  Return: MaterialReturnService — serial kembali AVAILABLE dengan remaining tercatat,
          movement RETURN (delta 0 bila tidak terpotong).
```

### 5.3b Putaway, Booking Serial WO & Pallet WIP (adopsi aplikasi referensi, diperbaiki)

```
1. PUTAWAY (setelah GRN confirm — perbaikan wh_inc):
   wh_inc_main (ref GRN) → scan serial/lot → wh_inc_detail (rack_id rak)
   → wh_serial.rack_id ter-update; stok per rak tampil di layout gudang visual
   (wh_layout: posisi rak & mesin drag-drop)
2. BOOKING SERIAL KE WO (perbaikan prd_wo_serial_rm):
   WoAllocationService.book(wo): pilih serial (FIFO/manual) →
     qty_per_serial = floor(remaining_length / length_per_pcs_bom)
     length_rem_est = remaining_length − (qty_alloc × length_per_pcs)
     scrap_candidate_est = length_rem_est < min_bom_length
   → prd_wo_serial (BOOKED); issue memvalidasi scan sesuai booking (status → ISSUED)
   → serial ter-booking tidak bisa dialokasikan WO lain (kecuali RELEASED)
3. PALLET WIP (perbaikan tr_cut_pal_pr/tr_pro_pal_pr):
   hasil scan proses dimuat ke prd_wip_pallet (pallet_label unik, qty sesuai m_pallet_item)
   → proses berikutnya scan pallet asal (from_pallet_label = jejak) → status MOVED
   → receiving FG per pallet (status RECEIVED_FG) — traceability pallet penuh
4. RETURN SISA: dokumen wh_rem_main/detail (ref wh_out asal) → rak is_remnant_rack
5. GENERAL STORE (non-material): wh_gen_req (permintaan dept, approval) → wh_gen_out
   (unit_cost moving average → jurnal beban per cost_center; link machine/asset =
   riwayat sparepart per asset); stok masuk via prc_gr biasa tanpa serial
   (perbaikan wh_rem: sisa selalu jelas raknya, tampil di layout)
```

Perbaikan vs aplikasi referensi: FK integer ke `wh_serial.id` (bukan string serial ke mana-mana),
dual UoM (length **dan** kg — referensi hanya length), status workflow eksplisit (bukan flag `finish`),
DECIMAL utk ukuran (bukan int/double), idempotency scan, dan audit kolom baku.

### 5.3c Validasi Item–Customer (perbaikan m_item.cus_id referensi)

```
SoService.addLine(so, fgItem):
  m_item_customer harus punya baris (item_id, so.customer_id, is_active=1)
  → tidak terdaftar: 422 ITEM_CUSTOMER_NOT_ALLOWED (bisa didaftarkan dulu, ber-approval)
  → DO & label packing memakai customer_part_no / qty_per_box dari mapping tsb
```

### 5.4 ScrapService (kandidat otomatis + keputusan manual)

```php
public function evaluate(MaterialSerial $s): void {
    $minLen = Cache::remember("min_bom_len:{$s->item_id}", 3600, fn() =>
        BomLine::where('component_item_id', $s->item_id)
               ->whereHas('bom', fn($q) => $q->where('status','APPROVED'))
               ->min('length_per_pcs_mm'));            // invalidate saat BOM berubah
    if ($minLen !== null && $s->remaining_length_mm < $minLen && $s->remaining_length_mm > 0)
        $s->status = 'SCRAP_CANDIDATE';
}
public function decide(MaterialSerial $s, string $to /*SCRAP|USABLE*/, string $reason, User $u): void {
    // permission wmsrm.scrap.decide ; boleh dua arah, termasuk serial non-kandidat → SCRAP
    ScrapDecision::create([... 'auto_flag'=>($s->status==='SCRAP_CANDIDATE'), 'min_bom_length_mm'=>$minLen ...]);
    // SCRAP → keluarkan dari stok usable, nilai pindah ke akun Scrap Loss;
    // penjualan scrap (kg) → dokumen ScrapDisposal + jurnal pendapatan lain (recovery)
}
```

### 5.5 Dispatch Transaksi Produksi: TR_CUT vs TR_PRO

```
MesDispatchService.resolve(wo, wosDetail):
  if wosDetail.sequence == 1 AND wosDetail.process.is_cutting:
      -> TR_CUT   (scan SERIAL RM)
  else:
      -> TR_PRO   (scan KODE PALLET WIP dari proses sebelumnya)
      -- catatan: cutting yang BUKAN proses pertama tetap TR_PRO

TrCutService.record(payload):                     # idempotent by client_uuid
  ├─ user TEPAT 1 (kolom user_id di main — enforced by design)
  ├─ mesin >= 1: tr_cut_detail per mesin (start/end per mesin)
  ├─ scan serial: tr_cut_serial per serial per mesin →
  │    wh_serial.remaining_length/weight terpotong (kg proporsional aktual)
  │    → evaluasi kandidat scrap (remaining < min length BOM)
  └─ hasil OK → prd_wip_pallet baru (qty sesuai m_pallet_item), label pallet dicetak

TrProService.record(payload):
  ├─ scan pallet asal: in_wip_pallet_id (status pallet asal → MOVED)
  ├─ users max 2 (tr_pro_user), machines max 2 (tr_pro_machine) → 422 jika lebih
  ├─ qty_ok/ng per mesin; NG → tr_ng (cut_id/pro_id)
  └─ output → prd_wip_pallet baru dengan from_pallet_label = pallet asal (jejak penuh)

TR_AB (abnormal): rework di lantai produksi terikat cut_id/pro_id, detail per serial (cut)
  atau per pallet (pro). TR_DT (downtime): kategori Dandori/Change Tools/Trial/NG/Machine
  Problem/PM, terikat mesin + cut/pro, plus tr_dt_tool utk tools yang diganti.
```

### 5.6 Final Check Sheet → RFG

```
FcsService.create(wo): validasi semua operation COMPLETED & NG ter-disposisi
  traceability JSON = { serials:[{serial_no, used_mm, used_kg}], operations:[{seq, process, users, m_machine, qty}] }
QC approve FCS → RfgService.receive:
  ├─ wh_fg_lot insert (lot_no = WO no + suffix; unit_cost dari CostingService.currentStd atau actual WO)
  ├─ StockService.increase WH FG ; JournalEngine (Dr Persediaan FG, Cr WIP)
  └─ serial RM berstatus habis → CONSUMED
```

### 5.7 FG Transfer & FG Downgrade

```
FgTransferService.transfer(fromItem, toItem, lot, qty):
  ├─ fromItem.spec_group === toItem.spec_group  else 422 SPEC_GROUP_MISMATCH
  ├─ ApprovalEngine → approved
  ├─ lot asal qty_remaining -= qty ; lot baru utk toItem (link parent_lot_id — jejak traceability)
  └─ JournalEngine reklasifikasi persediaan (nilai ikut unit_cost lot asal)

FgDowngradeService.downgrade(lot, qty, toMaterialItem, measured[]):
  ├─ mapping diperbolehkan? (tabel m_fg_downgrade_map: fg_item_id → material_item_id)
  ├─ SerialService.generate origin=DOWNGRADE (length & weight hasil ukur ulang per pcs / lot)
  ├─ value_diff = (unit_cost_fg × qty) − (unit_cost_material_baru × qty) → Dr Scrap/Varians
  └─ JournalEngine reklasifikasi FG → Material + varians
```

### 5.8 MRP Engine (pseudocode)

```
run(params):
  demands  = MPS qty per periode (+ forecast net utk item non-MPS)
  levelkan item via low-level code (BOM multi-level; FG-sebagai-PM diproses setelah induknya)
  for level in 0..N:
    for item in level:
      gross[t]  = Σ demand induk × qty_per × (1+scrap_pct) + independent demand
      onhand    = wh_stock_sum (usable; RM: pcs ekuiv = Σ floor(remaining_len / len_terkecil_pemakaian item ybs? -> gunakan len BOM item induk terkait))
                  -- penyederhanaan v1: RM dihitung dalam kg & pcs-batang-ekuiv memakai std_length_mm
      net[t]    = max(0, gross[t] − onhand − openPO[t] − openWO[t] − SR[t])
      lot-size  : MOQ vendor prioritas-1, multiple, lead time offset
      output    : RM/PM → saran PR ; FG-sebagai-PM/WIP → saran WO ; existing order mismatch → RESCHED
  RM impor: konversi net kg → ton, bandingkan saldo kuota → warning shortage kuota per quota_code
  simpan prd_mrp_detail (approval sebelum convert → PRService/WoService)
```

### 5.9 CRP

```
load[process][machine][period] = Σ (mps_qty × cycle_time_sec)/3600, alokasi mesin priority-1 dulu;
overload → cascade ke priority berikut; sisa overload → flag (saran: OT / subcont / geser MPS).
capacity = jam kerja kalender mesin − downtime terjadwal.
Output: prd_crp_result + heatmap loading %.
```

### 5.10 COGM / Costing per WO

```
material  = Σ wh_serial_movement(WO) used_kg × serial.unit_cost(kg)
labor     = Σ scan_result_main duration × Σuser share × tarif_umh(process)
foh       = Σ machine-hours × tarif_foh(process/mesin)   -- tarif dari master period rates
subcont   = Σ sub_gr_main qty_ok × po_subcont price
recovery  = Σ scrap disposal alokasi WO (opsional, default alokasi periode)
COGM      = material + labor + foh + subcont − recovery
unit_cost FG lot = COGM / qty_ok ; roll-up: lot FG-sebagai-PM dibawa sbg 'material' WO induk
varians   = (std_cost − actual) per elemen → jurnal varians saat closing
```

### 5.11 Subcont Flow

```
PO Subcont (per wos_operation is_subcont) → SubcontDelivery (DN):
  serial/lot pindah location virtual SUBCONT-{vendor} (stok tetap milik kita, tidak keluar nilai)
Portal: vendor confirm PO → update progress → buat ASN
SubcontReceipt (ref DN vendor) → QC → OK: WIP lanjut sequence berikutnya; NG: RTV/claim
AP Invoice jasa: DPP jasa, PPN (PKP), PPh23 2% → e-Bupot; JournalEngine.
```

---

## 6. Modul MES dalam Satu Database & Offline Mode

### 6.1 Satu Database

ERP dan MES memakai **satu database `ab-erp`** (tabel MES ber-prefix `mes_`, FK langsung ke tabel ERP:
`prd_wo_main`, `prd_wo_detail`, `m_machine`, `users`, `prd_kanban`, `wh_serial`, `wh_fg_lot`). Tidak ada
sinkronisasi antar-database, tabel cache, maupun outbox — hasil scan langsung meng-update WO/WIP/stok
dalam satu transaksi DB. Route MES tetap terpisah (`/api/mes/v1`, guard terminal) namun satu aplikasi Laravel.

### 6.2 Offline Terminal (PWA)

- Service worker + IndexedDB queue: payload scan disimpan lokal dengan `client_uuid`, ditandai `queued`.
- Validasi kritis dilakukan lokal dari cache aplikasi (aturan cutting/non-cutting, WO aktif, qty sisa) —
  snapshot WO released + BOM + WOS diunduh terminal saat menu loading.
- Saat online: flush queue berurutan per WO; server idempotent by `client_uuid`
  (duplikat → 200 dengan `meta.duplicate=true`, tidak dobel posting).
- Batas offline 24 jam (konfigurable); lewat itu terminal wajib re-sync snapshot sebelum transaksi baru.
- ERP/API down = MES ikut down (konsekuensi satu sistem); mitigasi: HA MySQL + health monitoring,
  dan antrean offline terminal menahan data selama outage singkat.

## 7. API Contract Detail

> Semua endpoint: header `Authorization: Bearer`, `X-Client-Uuid` untuk transaksi MES. Contoh kontrak kunci:

### 7.1 `POST /api/v1/purchase-orders` (impor)

```json
// request
{ "po_type":"RM", "source":"IMPORT", "vendor_id":12, "currency_id":2, "rate":16250.0,
  "incoterm":"CIF", "quota_id":3,
  "lines":[{ "item_id":101, "qty":20, "uom_id":7, "price":580.00,
             "tax_code_id":1, "est_weight_kg":19850.5, "due_date":"2026-08-30" }],
  "attachments":[{ "type":"QUOTATION", "quotation_id":55 }] }
// response 201: { "data": { "id":881, "doc_no":"PO/2026/07/00021", "status":"DRAFT",
//   "quota_check": { "quota_code":"IMP-2026-01", "balance_ton":132.400, "est_ton":19.851 } } }
// error 422: { "errors":[{ "code":"QUOTA_INSUFFICIENT", "message":"..." }] }
```

### 7.2 GRN — serial di-generate saat create, diaktualisasi saat pengecekan

```json
// POST /api/v1/goods-receipts  (create → serial auto-generate, status GENERATED)
{ "po_id": 881, "vendor_dn_no": "SJ-0451",
  "lines": [ { "po_detail_id": 4021, "qty_received": 20 } ] }
// response 201: { "data": { "doc_no":"GRN/2026/07/00105", "status":"SERIAL_GENERATED",
//   "serials": [ { "serial_no":"STK25-2607-00001" }, "...20 baris" ],
//   "print_url": "/print/grn/105" } }   // dokumen GR + daftar serial + label ZPL

// PATCH /api/v1/goods-receipts/{id}/serials/actualize  (pengecekan — perubahan di GR)
{ "serials": [
    { "serial_no":"STK25-2607-00001", "length_mm":6005.0, "weight_kg":44.120,
      "heat_no":"H8842", "status":"OK" },
    { "serial_no":"STK25-2607-00002", "status":"NG", "ng_reason":"Bengkok, OD out of spec" },
    { "serial_no":"STK25-2607-00020", "status":"VOID" } ] }
// prc_gr_detail.qty_accept/reject + total_weight_kg + total_length_mm ter-rekap otomatis

// POST /api/v1/goods-receipts/{id}/confirm → stok masuk (serial OK saja), kuota aktual, jurnal;
// setelah ini AP Invoice baru boleh diinput (3-way match qty/berat/panjang aktual)
```

### 7.3 `POST /api/mes/v1/scan-results`

```json
// request
{ "client_uuid":"018f...c7", "wo_no":"WO/2026/07/00105", "sequence":2,
  "users":[31,44], "m_machine":[{ "machine_id":9 }],
  "qty_ok":480, "qty_ng":3, "ng":[{ "defective_code_id":12, "qty":3 }],
  "start_time":"2026-07-14T08:00:00+07:00", "end_time":"2026-07-14T11:30:00+07:00" }
// 201 → { "data": { "id": 90112, "wip_next_sequence": 3 } }
// 422 → { "errors":[{ "code":"MAX_2_USERS" }] }   // atau CUTTING_SINGLE_USER
// duplicate client_uuid → 200 { "data": {...existing}, "meta": { "duplicate": true } }
```

### 7.4 `POST /api/v1/fg-transfers`

```json
{ "from_item_id": 501, "to_item_id": 502, "fg_lot_id": 7788, "qty": 120, "reason": "Spec sama, order mendesak" }
// 422 SPEC_GROUP_MISMATCH bila m_item.spec_group berbeda; sukses → dokumen DRAFT menunggu approval
```

### 7.5 `POST /api/v1/ar-invoices`

```json
{ "customer_id": 21, "do_ids":[3301,3302], "tax_code_id":1 }
// response — server menghitung pajak (TaxEngine):
// { "data": { "doc_no":"INV/2026/07/00077", "dpp": 150000000, "dpp_nilai_lain": 137500000,
//             "vat_amount": 16500000, "total": 166500000 } }   // 12% × 11/12 × DPP
```

### 7.6 `POST /api/v1/mrp/run`  → `202 { "data": { "run_id": 45, "status":"QUEUED" } }`; hasil via `GET /mrp/results?run_id=45` (paginated; kolom sesuai 5.8) dan `POST /mrp/results/convert { "result_ids":[...], "to":"PR" }`.

Kontrak endpoint lain mengikuti pola sama (RESTful resource + action endpoint `/{id}/{verb}`); detail penuh digenerate ke OpenAPI 3 (`storage/api-docs`) via anotasi.

---

## 8. Frontend Design (React)

### 8.1 Struktur

```
src/
├── app/            (router, providers, guards)
├── api/            (axios instance, TanStack Query hooks per modul — auto-gen dari OpenAPI)
├── components/     (DataTable server-side, DocHeaderForm, ApprovalBar, StatusChip,
│                    SerialScanner (kamera/HID scanner), FilePreview, PeriodPicker)
├── features/{modul}/  (pages, forms, hooks — mengikuti 14 modul)
├── layouts/        (ERP shell: sidebar dinamis dari /auth/me; MES shell: fullscreen scan)
├── stores/         (zustand: auth, ui, offlineQueue [MES])
└── theme/          (design tokens FTPI: --color-primary biru korporat, dsb.)
```

### 8.2 Pola Kunci

- **Menu & guard**: `/auth/me` mengembalikan tree menu + permission set → sidebar dirender dinamis; `<Can permission="procurement.po.approve">` membungkus tombol aksi.
- **List**: satu komponen `DataTable` server-side (sort/filter/saved-filter/export) dipakai semua modul.
- **Form dokumen**: `DocForm` header–detail dengan dirty-check, optimistic lock (kirim `version`), tombol workflow kontekstual sesuai status + permission.
- **MES PWA**: route terpisah `/mes`, target tablet; komponen `ScanBox` (autofocus, debounce, suara beep), tombol besar; indikator online/offline + jumlah antrean; IndexedDB (Dexie) untuk queue & cache master.
- **Mobile Approval/EIS**: route responsive `/m/approvals`, `/m/eis` (chart ringan, TanStack Query polling).

---

## 9. Security Design

| Area | Desain |
|---|---|
| AuthN | Sanctum token; login → (opsional per role) tantangan **TOTP 2FA**; token MES terminal berumur panjang terikat `terminal_id` + IP allowlist |
| AuthZ | Permission middleware `can:{key}` per route; Policy per model; privilege dicek ulang di service layer (defense in depth) |
| Portal vendor | Guard `vendor` terpisah; setiap query di-scope `vendor_id` token (global scope model); tidak ada shared endpoint dengan internal |
| Data | Password argon2id; kolom NPWP terenkripsi at-rest (Laravel encrypted cast); TLS wajib |
| Period lock | Middleware `EnsurePeriodOpen` pada semua endpoint posting |
| Rate limit | 60 rpm user; endpoint auth 5 rpm/IP |
| Audit | Observer global → `audit_logs` (before/after JSON); log immutable (append-only, tanpa endpoint update/delete) |

---

## 10. Error Handling, Logging & Audit

- Exception hierarchy: `BizException(code, httpStatus=422)` → envelope error; kode bisnis terdaftar di satu registry (`ErrorCodes.php`) dan dipakai FE untuk pesan i18n.
- Logging terstruktur JSON (Monolog) → stdout → agregator; korelasi `X-Request-Id` end-to-end (FE → API → job).
- Job gagal: retry 3× exponential backoff → `failed_jobs` + notifikasi admin; antrean offline terminal MES punya dashboard monitoring & tombol re-drive.
- Semua posting dibungkus `DB::transaction` dengan lock eksplisit pada resource kontensi tinggi (kuota, numbering, serial, wh_stock_sum).

---

## 11. Deployment & Infrastruktur

```
[Nginx] → [php-fpm api (ERP + MES route group)] ─ Redis ─ [queue workers: default|heavy]
                                                    [scheduler cron]
[MySQL primary (database ab-erp)] → replica (report/EIS read)
[Object storage] lampiran & label   [Backup: mysqldump harian + binlog, retensi 30 hari]
```

- Environment: `dev` → `staging` (UAT, data anonim) → `production`; migrasi via CI/CD (zero-downtime: `php artisan migrate` backward-compatible, deploy blue-green).
- Observability: healthcheck `/up`, metrik queue depth & antrean offline terminal.

---

## 12. Strategi Testing

| Level | Cakupan minimal |
|---|---|
| Unit (Pest) | TaxEngine (kasus mewah/non-mewah, pembulatan), QuotaService (reserve/actualize/partial/cancel), ScrapService (batas min_bom_length), SerialService.consume (proporsi kg), aturan scan cutting/non-cutting |
| Feature/API | Workflow status setiap dokumen (transisi ilegal 409), approval berjenjang, 3-way match, period lock 423, idempotensi `client_uuid` |
| Integrasi | MRP end-to-end (BOM multi-level, FG-sebagai-PM), COGM roll-up, offline queue terminal (dedup client_uuid) |
| E2E (Playwright) | Alur RM: PR→PO impor→GRN serial→invoice→cost sheet; Alur FG: WO→scan cutting multi-mesin→FCS→RFG→DO→AR invoice+e-Faktur |
| UAT | Skenario per user story PRD Bab 10 (16 skenario) |

---

*Dokumen LLD ini menjadi acuan implemen