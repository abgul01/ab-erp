# PRD — Sistem ERP + MES Manufaktur Pipa Besi

**Product Requirement Document**
Perusahaan manufaktur pipa besi — part alat berat & otomotif
Versi 3.0 — 16 Juli 2026 *(BASIS: struktur database aplikasi existing — tabel & field dipertahankan verbatim; ALTER hanya utk kriteria ERP; modul lain dikembangkan dengan gaya yang sama)*

---

## Daftar Isi

1. [Pendahuluan](#1-pendahuluan)
2. [Arsitektur Sistem](#2-arsitektur-sistem)
3. [Alur Dokumen & Serial Tracking](#3-alur-dokumen--serial-tracking)
4. [Struktur Modul & Menu](#4-struktur-modul--menu)
5. [Ketentuan Domain Spesifik](#5-ketentuan-domain-spesifik)
6. [Perpajakan, HPP & Costing](#6-perpajakan-hpp--costing)
7. [UI/UX & Identitas Visual](#7-uiux--identitas-visual)
8. [Skema Database Utama](#8-skema-database-utama)
9. [API Outline](#9-api-outline)
10. [User Stories & Acceptance Criteria Kunci](#10-user-stories--acceptance-criteria-kunci)
11. [Non-Functional Requirements](#11-non-functional-requirements)
12. [Roadmap Implementasi](#12-roadmap-implementasi)
13. [Glossary](#13-glossary)

---

## 1. Pendahuluan

### 1.1 Latar Belakang

Perusahaan bergerak di bidang manufaktur part berbahan pipa besi (steel tube parts) untuk industri alat berat dan otomotif — contoh produk: boss, bush, steering axle, cylinder tube. Bahan baku diperoleh dari vendor lokal maupun impor; pembelian impor dibatasi **kuota impor** per kode/spesifikasi dengan total tonase (ton).

Proses produksi selalu **dimulai dari Cutting**, dilanjutkan proses lanjutan (machining, chamfering, drilling, welding, plating, dsb.), termasuk proses di **subcontractor**. Sistem terdiri dari **ERP (back office)** dan **MES (Manufacture Execution System)** di lantai produksi, terintegrasi penuh, dengan kepatuhan regulasi perpajakan Indonesia terbaru.

### 1.2 Tujuan

1. Satu sumber data tunggal untuk seluruh transaksi perusahaan (ERP) + eksekusi real-time lantai produksi (MES).
2. Traceability penuh material → FG melalui serial number (RM 1 serial : 1 pcs, PM 1 serial : 1 batch/lot/box) dan Final Check Sheet.
3. Kontrol kuota impor real-time (kode, spesifikasi, saldo ton).
4. HPP & costing akurat berbasis pemakaian aktual (berat & panjang aktual kedatangan).
5. Perencanaan terstruktur: Forecast → MPS → MPP → MRP → CRP → WO/Kanban.
6. Kepatuhan pajak: PPN (PMK 131/2024, DPP Nilai Lain 11/12), e-Faktur/Coretax, PPh 22/23, e-Bupot.
7. Portal subcontractor untuk kolaborasi vendor proses luar.

### 1.3 Ruang Lingkup

**In-scope (core):** Administrator, General Data Master, Engineering, Order Management, Manufacturing Supporting (MPS/MPP/MRP/CRP), Incoming Quality Inspection (QAS), Procurement (+kuota impor), WMS Raw Material, MES (+Portal Subcont), WMS Finished Good, Shipping Order, Asset Management, Costing, Accounting & Finance.

**Optional modules (fase lanjut):** NPD, Budgeting, CRM, Plant Maintenance, VMS, IT Inventory.

### 1.4 Referensi

- Identitas visual: situs PT. Fusoh Tube Parts Indonesia — `https://www.fusoh-kokan.co.jp/FTPI/`
- Regulasi pajak: UU HPP, PMK 131/2024, sistem Coretax DJP.
- Diagram arsitektur modul & serial tracking flow (lampiran user).

---

## 2. Arsitektur Sistem

### 2.1 Landscape (sesuai diagram referensi)

```
┌──────────────────────────┐   ┌──────────────────────────────────┐   ┌─────────────────────┐
│ 1. ERP / CORE MODULES    │   │ 3. MES (Shop Floor)              │   │ 4. DOWNSTREAM       │
│  Administrator           │   │  Generate Menu Loading           │   │  6. WMS FG          │
│  1 Engineering           │   │  → Print Job Order (WOS)         │   │  7. Shipping Order  │
│  2 Order Management      │◄─►│  → Release Job Order             │   │  8. Costing         │
│  3 Manufacture (MPS/MPP/ │ab-│  → Process 1..N (+Sub Process)   │◄─►│  9. Asset Mgmt      │
│    MRP/CRP)              │erp│  Kanban → Issued RM              │   │ 10. ACC & FIN       │
│  4 Procurement           │   │  NG Process | Scrap RM | Dashbrd │   └─────────────────────┘
│  5 WMS RM                │   │  Portal Subcont ◄─► Subcont      │
│  Optional: NPD/Budgeting/│   │  Relation Data: BOM, Master Item,│
│  CRM/PlantMaint/VMS/IT   │   │  Master WOS, MPP  │  ab-erp      │
└──────────────────────────┘   └──────────────────────────────────┘
        ┌────────────────────────────────────────────────────┐
        │ 5. SUPPORT FEATURES: Integrasi Automasi (opsional),│
        │ 2FA, Online & Offline Support, Mobile EIS          │
        │ (opsional), Mobile Approval                        │
        └────────────────────────────────────────────────────┘
```

- Implementasi memakai **SATU database MySQL `ab-erp`** untuk seluruh modul ERP & MES (tabel transaksi produksi/MES ber-prefix `tr_`). Pemisahan "ERP DB / MES DB" pada diagram referensi disederhanakan — BOM, Master Item, WOS, dan WO diakses langsung tanpa mekanisme sinkronisasi antar-database.
- MES tetap mendukung **mode offline** di sisi klien: buffer lokal di terminal/tablet (PWA/IndexedDB) dengan `client_uuid` idempotent; auto-sync ke server saat koneksi pulih.

### 2.2 Tech Stack

| Layer | Teknologi |
|---|---|
| Backend | **Laravel 11+** (PHP 8.3), REST API, Sanctum, Laravel Queue (Redis) untuk MRP/CRP/report |
| Frontend | **React 18+** (Vite), React Router, TanStack Query; MES client = PWA React (offline-capable, service worker) untuk terminal scan/tablet |
| Database | **MySQL 8.0** — satu database `ab-erp` (modul ERP & MES; tabel transaksi produksi/MES ber-prefix `tr_`) |
| Cache/Queue | Redis |
| Storage | Local/S3-compatible (quotation, mill cert, gambar, faktur) |
| Report/Print | PDF server-side, Excel export, label barcode/QR (ZPL) |
| Mobile | Mobile Approval & Mobile EIS (dashboard eksekutif) — PWA/responsive |
| Keamanan | **2FA** (TOTP) opsional per role, RBAC granular |
| Automasi (opsional) | API/OPC-UA gateway untuk integrasi mesin (counter otomatis, andon) |

### 2.3 Fitur Administrator (lintas modul)

**Paperless Approval** (semua dokumen approval berjenjang di sistem + **Mobile Approval**), **Unlimited User**, **Log Historical** (audit trail semua aksi), **Menu Privilege** & **User Privilege** (RBAC granular per aksi: view/create/edit/delete/approve/post/print/export). Menu dikelola dinamis (hirarki, icon, urutan); user portal subcont terisolasi penuh dari menu internal.

---

## 3. Alur Dokumen & Serial Tracking

### 3.1 Raw Material Flow (RM — hijau)

```
1.MRP → 2.PR → 3.PO → 4.(Supplier) Delivery Confirmation → 5.GRN (Generate Serial + Print GR & Label)
     → 6.Pengecekan/QAS (aktualisasi NG & ukuran — perubahan di GR) → 7.Purchase Invoice (sesuai aktual) → Cost Sheet
```

1. **MRP** menghasilkan kebutuhan netto → PR otomatis (bisa juga PR manual/additional/non-RM/NPD).
2. **PR** ber-approval berjenjang.
3. **PO** (attach **Quotation**, comparative sheet); PO impor wajib kode **kuota impor** (validasi saldo ton). Jadwal kedatangan via **Schedule Delivery Supplier (Local/Import)**.
4. **Supplier Delivery Confirmation** — vendor/purchasing konfirmasi rencana kirim.
5. **GRN** — dibuat dari surat jalan vendor: **generate serial di depan** (RM = 1 serial/pcs, PM = 1 serial/lot/box; status GENERATED), lalu **print dokumen GR beserta daftar serial** + label QR.
6. **Pengecekan (QAS)** — inspeksi fisik + input **panjang & berat aktual** per serial. Bila ada **NG / tidak sesuai**, perubahan dilakukan **di GR**: serial di-set NG (+alasan) atau VOID, qty, berat (kg) & panjang (mm) dikoreksi; NG → retur vendor/claim. GR impor mengaktualisasi kuota berdasar berat timbang aktual. Posting GR memasukkan stok hanya untuk serial ber-status OK.
7. **Purchase Invoice (AP)** — diinput **setelah GR dikonfirmasi, sesuai kedatangan aktual — qty, berat & panjang** (3-way match PO–GRN aktual–Invoice); faktur pajak masukan; **Cost Sheet** (landed cost impor) dialokasikan per berat aktual → unit landed cost per kg & per serial.

### 3.2 Finished Good Flow (FG — biru)

```
1.MPS → 2.MPP → 3.Production Plant → 4.Work Order Release → 5.Production Issue (Kanban)
     → 6.Shop Floor (MES scan Process 1..N) → 7.Final Check Sheet → 8.RFG (Receiving FG)
     → 9.Delivery Note → 10.Sales Invoice
```

- **Kanban / Material Issue & Receiving** adalah checkpoint bersama RM↔FG flow: kanban dibuat dari WO (PP-HACV), warehouse scan-out serial RM (**Issued RM**), sisa kembali dengan remaining length & kg tercatat.
- **Final Check Sheet** (OQC) wajib sebelum RFG — merangkum traceability: serial RM → WO → proses → operator/mesin → lot FG.
- **RFG** men-generate lot/serial FG masuk WMS FG; **Delivery Note** mengurangi stok; **Sales Invoice** menerbitkan faktur pajak keluaran (Coretax).

---

## 4. Struktur Modul & Menu

> Struktur mengikuti diagram referensi (14 modul). Menu detail = baseline; final scope dituangkan di SRS per modul.

### 4.1 Administrator Module

| Sub Module | Menu Detail |
|---|---|
| Module Administrator | CRUD Approval (flow per dokumen & nilai), CRUD User, Setting User (role, 2FA), CRUD Menu, Setting Menu (privilege per role), Log Historical (audit trail) |

### 4.2 General Data Master Module

| Sub Module | Menu Detail |
|---|---|
| General Data Master | CRUD Categories, Product Family, UoM, Production Line, Working Calendar, Unit Conversion Purchase Order, Unit Conversion Issue Production *(mis. kg ↔ length ↔ pcs untuk RM pipa)*, Currency & Kurs Pajak (KMK), Tax Code |
| Maintenance | CRUD Machine Type, Machine (kapasitas, kalender), Equipment, Master Downtime |

### 4.3 Engineering Module

| Sub Module | Menu Detail |
|---|---|
| Master Item | CRUD & Approval Item RM, Item PM, Item FG, Consumable/Service — **master item variatif**: `serial_mode` (PER_PCS/PER_LOT/NONE), dual UoM (length mm + kg untuk RM), spec `out_diameter`/`thickness`/`width`/`height` (opsional per item), `spec_group` (untuk FG Transfer), HS Code, `weight_actual_based` |
| Master Makers | CRUD & Approval Makers (manufacturer material) |
| Bill of Material | CRUD & Approval BOM (multi-level; **FG bisa jadi PM bagi FG lain**; `length_per_pcs` untuk komponen RM pipa; scrap %), Compare BOM, Copy BOM, Item Where Used, BOM Historical Revision |
| Master WOS / Process | CRUD & Approval Master WOS (routing: urutan proses — **proses pertama RM pipa = Cutting**, cycle time, QC point, flag subcont), CRUD Main Process / Process / Sub Process, **mesin per proses dengan skala prioritas** |
| Unit Man Hour (UMH) | CRUD & Approval UMH (PP), UMH (HAV) — standar jam orang per proses untuk costing & CRP |
| ECN | Engineering Change Request/Notice — kontrol revisi item/BOM/WOS |

### 4.4 Order Management Module

| Sub Module | Menu Detail |
|---|---|
| Dashboard | Forecast by Month (Qty & Amount), Sales Order by Month, FC vs SO by Month |
| Master Data | CRUD & Approval Data Customer (NPWP, alamat pajak, ToP, plafon kredit), Customer Contract, **Master Pricelist FG per periode** (valid from–to, currency, approval; SO menarik harga otomatis sesuai tanggal) |
| Forecast | CRUD & Approval Forecast Customer, Download/Upload Template Forecast, Print PDF, Export Excel; versi forecast (N-3/N-2/N-1/Final) |
| Sales Order | CRUD & Approval SO, **Convert Forecast to Sales Order**, Download/Upload Template |
| Report | Potential Sales, Outstanding SO, Forecast vs SO, **Forecast Analysis** (akurasi MAPE/BIAS per item & customer) |

### 4.5 Manufacturing Supporting Module

| Sub Module | Menu Detail |
|---|---|
| APS – MPS | Generate Confirmation, Generate MPS, Approval MPS, Report MPS — penjadwalan memakai **prioritas mesin** routing |
| APS – MPP | Setting Working Calendar, Generate Monthly Production Planning (MPP), Production Schedule, **Create & Print Work Order**, **Generate Kanban Preparation**, Generate Supply Sheet HAV, Report MPP, Report Production Schedule Realization |
| MRP | Generate MRP (BOM explosion multi-level − stok − open PO − open WO; kebutuhan RM dalam pcs batang & kg; proyeksi kebutuhan **kuota impor** ton), MRP Result Approval → convert ke PR/WO |
| CRP | Generate Loading Capacity based MPS, based MPP by Process, Generate Machine Capacity — deteksi bottleneck, dasar keputusan overtime/subcont |

### 4.6 Incoming Quality Inspection Module (QAS)

| Sub Module | Menu Detail |
|---|---|
| Quality Inspection | CRUD Master Inspection (parameter & standar), CRUD Item Inspection (mapping parameter per item), Incoming Inspection — dilakukan SETELAH serial ter-generate & GR tercetak: scan serial → input hasil ukur/timbang aktual & judge; hasil dicatat langsung di GR (serial OK/NG/VOID); reject → Material Rejection/retur vendor + vendor rating |
| Process Inspection | CRUD Master Defective (kode defect), CRUD Process Defective, Print QC Pass Label |

### 4.7 Procurement Module

| Sub Module | Menu Detail |
|---|---|
| Master Vendor | CRUD & Approval Vendor (lokal/impor/subcont; NPWP, PKP, ToP, currency); **1 material multi-vendor** (Supplier Item & Price, prioritas, MOQ, lead time) |
| Vendor Contract | CRUD & Approval Vendor Contract, Vendor Historical Price |
| Vendor Quotation | CRUD Quotation, Download/Upload Template, Approval Quotation — **quotation dapat di-attach ke PO** |
| Purchase Request | PR Based MRP, PR Additional, PR Non-RM *(satuan bermacam-macam)*, PR NPD, PR Approval |
| Purchase Order | PO Raw Material, PO Non-RM, PO NPD, PO Service, **PO Subcont**, PO Approval, Schedule Delivery Supplier **Local/Import**, Forecast Supplier, Supplier Delivery Confirmation |
| Kuota Impor | CRUD Master Kuota (kode, spesifikasi, **total ton**, periode, mapping item), Kartu Kuota (reservasi PO → realisasi GRN aktual), alert saldo |
| PO Receipt | Supplier Delivery Confirmation, Purchase Order Receipt / GRN — urutan: buat GR (serial auto-generate) → print GR + daftar serial + label → pengecekan/aktualisasi (qty, berat, panjang; NG/VOID di GR) → confirm/posting (serial OK) → baru input Invoice AP sesuai aktual |
| Cost Sheet | Landed cost impor: FOB/CIF, freight, insurance, bea masuk, PPN impor, PPh 22, biaya PIB/EMKL; alokasi per **berat aktual** → unit landed cost per kg/serial; posting jurnal |

### 4.8 Warehouse Management Raw Material (WMS RM)

| Sub Module | Menu Detail |
|---|---|
| Master Warehouse RM | CRUD Master Location/Rak (dimensi, flag rak remnant), **Layout Gudang Visual** (drag-drop posisi rak & mesin pada denah), Master Pallet + kapasitas qty per pallet per item (prioritas), Master Shift |
| Incoming RM | Buat GRN dari surat jalan → **serial auto-generate di awal** (RM = 1 serial/pcs, PM = 1 serial/lot/box), Print Dokumen GR + Daftar Serial + Material Label (QR), Scan pengecekan: input **panjang & berat aktual** per serial, set NG/VOID di GR, Confirm GR (stok masuk untuk serial OK) |
| Putaway | Setelah GR confirm: scan serial/lot → **penempatan ke rak** (dokumen putaway; stok tampil per rak di layout visual) |
| Booking Serial WO | **Alokasi serial ke WO sebelum issue**: sistem menghitung estimasi pcs per serial (dari length_per_pcs BOM), proyeksi sisa & kandidat scrap; operator tinggal scan sesuai daftar booking |
| Issue RM | Scan Out RM (per kanban/WO sesuai booking; potong **remaining length & kg** proporsional), **Barcode Divided** (pecah serial/label untuk sisa potongan), **Return sisa ke rak remnant** (rak khusus material sisa), Return Material to Supplier, Material Rejection, Unplanned Kanban |
| Transaction | Begin Balance Stock, Adjustment (IN/OUT/STO), Item Stock Transfer, Stock Taking Opname |
| Scrap RM | Kandidat scrap otomatis (**remaining < min length BOM aktif**), keputusan manual (scrap ⇄ usable, alasan tercatat), disposal/penjualan scrap (kg) |
| General Store (Non-Material) | Gudang GENERAL utk **sparepart & consumable**: masuk via PR/PO Non-Material → GR (tanpa serial) → stok; **Permintaan Barang departemen** (ber-approval) → **Pengeluaran** dengan pembebanan cost center + link ke **mesin/asset** (riwayat sparepart per asset utk asset control); min-max stock → PR otomatis; opname & laporan pemakaian per departemen/mesin/asset |
| Report | Stock Value (Summary & Detail), **Serial Tracking**, Balance Material Production (RM-WIP), Kanban Transaction, Material Delivery Confirmation, Historical Transaction (Warehouse) |

### 4.9 Manufacture Execution System (MES)

| Sub Module | Menu Detail |
|---|---|
| Menu Loading | Generate & Approve Menu Loading (jadwal WO per mesin/WP), **Print WOS by Machine/WP** (Job Order), Release Job Order |
| Scan Process | **Dispatch otomatis:** bila langkah WOS adalah **proses pertama DAN cutting** → transaksi **TR Cut** (scan **serial RM**; tepat 1 operator, boleh multi mesin, waktu per mesin). Selain itu — termasuk **cutting yang bukan proses pertama** → transaksi **TR Pro** (yang di-scan **kode pallet WIP** hasil proses sebelumnya; max 2 user & 2 mesin, hasil per mesin). Output tiap transaksi dimuat ke pallet WIP baru (jejak pallet asal tercatat). |
| Kanban & Material | Create Kanban (PP-HACV), Issued RM (PP-HACV), Return Material to Warehouse, Return Material Rejection, Material Scrap Transaction |
| Other Process | **Final Check Sheet** (OQC + traceability), **Transaksi Abnormal (TR AB)** — rework/penanganan barang abnormal terikat transaksi cut/pro (per serial atau per pallet), Breakdown/Downtime (TR DT) per kategori (**Dandori/Ganti Model, Change Tools, Trial, NG Problem, Machine Problem, Preventive Maintenance**; terhubung WO & mesin, waiting/repair time), NG Item Transaction (disposisi: rework/downgrade/scrap), Create Request Repair FG, Create Receiving Repair FG |
| Subcont | Terhubung **Portal Subcont**: PO Subcont → Delivery ke Subcont (surat jalan, serial/lot tercatat, stok di lokasi virtual "Subcont–{vendor}") → progres vendor → Receiving dari Subcont (+QC) → lanjut proses berikutnya. Vendor portal: lihat & konfirmasi PO, buat ASN/surat jalan balik, update progres, lihat hasil QC, outstanding, unduh dokumen |
| Dashboard & Report | Dashboard EIS: Production Dashboard, Work Period Monitoring, Internal Defective, Delivery Performance, Shortage Information; Report: Operator Achievement, Breakdown Time Machine, Internal Defective |

### 4.10 Warehouse Management Finished Good (WMS FG)

| Sub Module | Menu Detail |
|---|---|
| Master Warehouse FG | CRUD Master Location FG, CRUD FG Location |
| Receiving FG (RFG) | Scan Receiving FG (dari Final Check Sheet; generate lot/serial FG; traceability ke WO & serial RM), WIP Receipt, Scan In FG |
| Repair FG | Scan Out FG Repair (request/receiving repair — loop dengan MES) |
| FG Transfer | **Transfer FG ke kode FG lain** — hanya bila `spec_group` sama; ber-approval; stok & nilai pindah, jejak lot tetap |
| FG Downgrade | **FG NG → kembali jadi material** (mapping kode RM/PM; serial/lot material baru dengan panjang/berat ukur ulang; jurnal reklasifikasi; selisih ke scrap loss) |
| Lain-lain | Barcode Divided, STO Finished Good |
| Report | Historical Transaction (Summary & Detail), **Serial Tracking FG** |

### 4.11 Shipping Order Module

| Sub Module | Menu Detail |
|---|---|
| Delivery Schedule | CRUD Delivery Schedule, Download/Upload Template |
| Delivery | Create Delivery Order (dari SO; alokasi lot FIFO/pilih lot), Shipping Order, Delivery Notes (surat jalan), Packing List |
| Report | Progress Delivery, Outstanding Delivery |
| Retur | Sales Return (QC ulang, credit note) |

### 4.12 Asset Management Module

| Sub Module | Menu Detail |
|---|---|
| Asset | Asset Category, Master Asset, CRUD & Approval Fixed Asset, Download/Upload Template, Dispose Fixed Asset, Transfer Fixed Asset, Asset Retirement |
| Depresiasi | Fixed Asset Depreciation, Journal Depreciation (otomatis ke GL) |
| Report | Report Fixed Asset |

### 4.13 Costing Module

| Sub Module | Menu Detail |
|---|---|
| Inventory Valuation | Inventory RM Valuation, Inventory WIP Valuation, Inventory FG Valuation (moving average + lapisan actual cost per serial/lot) |
| Costing Product | **Generate Costing Product (COGM/HPP)** per WO/periode: material aktual (proporsi length/kg serial × landed cost) + labor (UMH × tarif) + FOH (jam mesin/tarif proses) + subcont − scrap recovery; **cost roll-up multi-level** (FG-sebagai-PM); varians vs standard; Journal Costing |
| Analisis | Margin per SO/customer/item (pricelist vs HPP), simulasi harga (dampak harga material/kurs/cycle time), break-even |

### 4.14 Accounting & Finance Module

| Sub Module | Menu Detail |
|---|---|
| Master Data | Account Group, Group Detail, Account Bank, Account Statement, Account Cashflow, COA, Balance Supplier, Balance Customer, Exchange Rate (kurs KMK), Journal Type, Setup Journal (mapping jurnal otomatis per transaksi) |
| Account Payable | Purchase Invoicing (3-way match), AP Payment, AP Aging Schedule, Supplier Card |
| Account Receivable | Sales Invoicing (**e-Faktur Coretax**), AR Receipt, AR Aging Schedule, Customer Card |
| Ledger | Posting Journal, General Ledger, Trial Balance, Balance Sheet, Cash Flow, Income Statement |
| Pajak | PPN Keluaran–Masukan (rekap SPT Masa), PPh 22 impor, PPh 23 subcont + **e-Bupot**, ekspor CSV/XML skema Coretax |
| Report | Currency Revaluation, AP/AR Report, Bank Statement, Expenses Report, Purchase Register, Sales Register |
| Closing | Period, Lock Accounting (period lock semua modul) |

### 4.15 Optional Modules (fase lanjut)

**NPD** (New Product Development — PR/PO NPD sudah disiapkan), **Budgeting**, **CRM**, **Plant Maintenance** (terhubung Master Downtime & Breakdown Machine), **VMS** (Visitor Management), **IT Inventory**.

---

## 5. Ketentuan Domain Spesifik

Ketentuan berikut mengikat lintas modul (ringkasan aturan bisnis kunci):

1. **Serialisasi** — RM: 1 serial = 1 pcs, PM: 1 serial = 1 batch/lot/box. Serial **di-generate di awal saat GR dibuat** (status GENERATED) dan dokumen GR dicetak beserta daftar serialnya; panjang (mm) & berat (kg) **aktual** diinput saat pengecekan dan dikoreksi di GR (OK/NG/VOID). Master item tidak mengunci berat/panjang. Serial menyimpan `remaining_length` & `remaining_weight` paralel (dual UoM).
2. **Kuota impor** — `Saldo = total ton − reservasi PO open (estimasi) − realisasi GRN (aktual)`; PO impor hard-block bila saldo kurang (override via approval khusus); selisih estimasi vs timbang aktual otomatis menyesuaikan saldo.
3. **Scrap RM** — kandidat otomatis bila `remaining_length < min(length_per_pcs)` dari seluruh BOM aktif pemakai material tsb; keputusan akhir tetap bisa **manual dua arah** (scrap ⇄ usable) oleh user berwenang, dengan alasan tercatat.
4. **Aturan operator/mesin** — Cutting: 1 user, multi-mesin per WO. Non-cutting: max 2 user & 2 mesin per transaksi (validasi di form & API).
5. **FG Transfer** — antar kode FG hanya bila `spec_group` sama; ber-approval; nilai & traceability ikut pindah.
6. **FG Downgrade** — FG NG kembali menjadi material (serial/lot baru, ukuran hasil ukur ulang, jurnal reklasifikasi).
7. **BOM multi-level** — 1 FG dapat menjadi PM bagi FG lain; MRP & costing melakukan explosion/roll-up multi-level.
8. **Multi-vendor** — 1 material ≥1 vendor (harga, prioritas, MOQ, lead time per vendor).
9. **Pricelist berperiode** — SO menarik harga dari pricelist valid pada tanggal SO; tanpa pricelist aktif → blokir/override ber-approval.
10. **Subcont** — PO Subcont lengkap dengan pengiriman (DN, serial/lot tercatat, stok tetap milik perusahaan di lokasi virtual) dan penerimaan (+QC); PPh 23 dipotong di invoice jasa.

---

## 6. Perpajakan, HPP & Costing

| Aspek | Implementasi |
|---|---|
| **PPN** | **PMK 131/2024**: 12% × **DPP Nilai Lain 11/12** untuk BKP non-mewah (efektif 11%); barang mewah 12% penuh. Tax engine: formula per tax code + tanggal efektif (perubahan tarif = konfigurasi, bukan ubah kode) |
| **e-Faktur / Coretax** | Faktur keluaran via Coretax DJP (ekspor XML/API), validasi NPWP/NIK 16 digit; faktur masukan direkonsiliasi untuk pengkreditan |
| **PPh 22 Impor** | Dihitung di Cost Sheet, dicatat sebagai kredit pajak |
| **PPh 23** | 2% jasa subcont/maklon; bukti potong via **e-Bupot** Coretax |
| **Kurs pajak** | Tabel kurs KMK mingguan untuk DPP valas |
| **HPP** | Actual costing per WO (Bab 4.13) + standard cost untuk perencanaan; persediaan moving average + actual per serial/lot; COGM & varians dilaporkan per periode |

---

## 7. UI/UX & Identitas Visual

- **Referensi identitas: situs FTPI — `https://www.fusoh-kokan.co.jp/FTPI/`**: karakter korporat Jepang, bersih, formal, dominan **biru korporat & putih** (selaras diagram referensi). Design token diekstrak sebagai theme variables.
- Layout ERP: sidebar menu dinamis per privilege + topbar (search, notifikasi, profil), breadcrumb, list server-side pagination + form header–detail.
- **MES client**: layar terminal/tablet dengan tombol besar, scan-first (barcode/QR serial, kanban, WOS), minim ketikan, indikator offline/online.
- Dashboard EIS per role: manajemen (penjualan, margin, OTD, kuota impor), PPIC (loading capacity, WO late, shortage), produksi (achievement, defective, breakdown), warehouse (pending GRN/DO), accounting (aging, pajak). **Mobile EIS** & **Mobile Approval** tersedia.
- i18n ID/EN, zona waktu WIB.

---

## 8. Skema Database Utama

> Konvensi: `id` BIGINT PK, timestamps, soft delete pada master, kolom `*_by` audit. Satu database `ab-erp`; tabel modul MES ditandai prefix `tr_`. Daftar berikut inti, bukan lengkap.

```sql
-- MASTER (existing + tambahan) (34 tabel)
m_bom(id, item_id, active, created_at, updated_at)
m_bom_det_pm(id, id_prim, pm_id, qty, created_at, updated_at)
m_bom_det_rm(id, id_prim, mat_id, length_cut, length_use, priority, created_at, updated_at)
m_bom_pro(id, item_id, created_at, updated_at)
m_bom_pro_det(id, id_prim, proc_id, sequence, created_at, updated_at)
m_contacts(id, u_code, initial, nick_n, company_n, address, name, phone, email, ...)
m_cont_categ(id, name)
m_function_m(id, name, description, created_at, updated_at)
m_item(id, code, part_name, type, descrip, category_id, o_d, i_d, thick, ...)
m_i_category(id, name_c, created_at, updated_at)
m_machine(id, code, name, model, categ, maker_id, min_d, max_d, func_id, ...)
m_maker_m(id, name, address, active, created_at, updated_at)
m_pallet(id, code, name_pa, type_id, size, cap_kg, cap_m3, note, active, ...)
m_pal_item(id, item_id, created_at, updated_at)
m_pal_item_det(id, id_prim, pal_id, qty, priority, created_at, updated_at)
m_pic(id, pic_id, type, active, created_at, updated_at)
m_process(id, code, name_p, descript, active, created_at, updated)
m_p_type(id, name_ty, created_at, updated_at)
m_rack(id, location, descriptions, height, width, area, rem_rack, active, depth)
m_region(id, name, created_at, updated_at)
m_shift(id, code, name)
m_team(id, code, descript)
m_i_pm(id, item_id, active, created_at, updated_at)
m_uom(id, uom_type, created_at)
m_currency(id, is_base)
m_rate(id, rate_type, rate)
m_tax(id, rate_pct, dpp_factor, is_luxury)
m_quota(id, descrip, total_ton, active)
m_quota_item(id)
m_item_customer(id, cus_id, cus_part_no, qty_per_box, active)
m_pricelist_main(id, cus_id, user_id)
m_pricelist_det(id, price, valid_from)
m_defective(id, type)
m_asset_categ(id, useful_life, depr_method)

-- PROCUREMENT (16 tabel)
prc_gr_detail(id, id_prim, item_id, qty, length, weight, w_total, note, created_at, ...)
prc_gr_main(id, code, inv_no, ven_id, date, user_id, created_at, updated_at, po_no)
prc_gr_serial(id, det_id, serial_id, millsheet, qty, length, created_at, updated_at)
prc_pr_main(id, pr_type, user_id, created_at)
prc_pr_detail(id, qty, note)
prc_po_main(id, po_type, source, ven_id, quota_id, currency_id, top_days, status, created_at)
prc_po_detail(id, item_id, price, est_weight, due_date)
prc_po_att(id, file_type, file_name)
prc_po_schedule(id, plan_date)
prc_quota_txn(id, ref_type, ref_id, note)
prc_gr_reject(id, gr_id, qty, status, created_at)
prc_inv_main(id, ven_id, inv_no, dpp, wht23, total, tax_inv_no, tax_inv_date, status, ...)
prc_inv_detail(id, qty)
prc_cost_main(id, po_id, status, user_id)
prc_cost_detail(id, cost_type, descrip, currency_id, amount_idr)
prc_cost_alloc(id, serial_id, amount)

-- WAREHOUSE (11 tabel)
wh_inc_detail(id, id_prim, serial_id, length, qty, item_id, rack_id, created_at, updated_at)
wh_inc_main(id, code, user_id, gr_id, date, shift_id, created_at, updated_at)
wh_layout(id, rack_id, x_position, y_position, width, height, rotation, type, label, ...)
wh_out_detail(id, id_prim, serial_id, qty, pm, length_serial, length_used, length_rem, rem_data, ...)
wh_out_main(id, code, wo_id, item_id, user_id, date, cus_id, shift_id, created_at, ...)
wh_rem_detail(id, id_prim, serial_id, wo_id, length, rem_count, rack_id, created_at, updated_at)
wh_rem_main(id, out_id, code, date, user_id, item_id, shift_id, created_at, updated_at)
wh_gen_req_main(id, dept, status, created_at)
wh_gen_req_det(id, qty, asset_id)
wh_gen_out_main(id, req_id, status, created_at)
wh_gen_out_det(id, item_id, cost_center)

-- STOCK SUMMARY (existing) (3 tabel)
sum_stock_main(id, user_id, date, created_at, updated_at)
sum_stock_pm(id, main_id, item_id, qty, created_at, updated_at)
sum_stock_rm(id, main_id, item_id, qty, length_serial, length_total, rack_id, weight_base, created_at, ...)

-- PLANNING & WO (13 tabel)
prd_cut_main(id, wip_id, code, item_id, wo_id, operator, machine_id, date, shift, ...)
prd_cut_serial(id, main_id, serial_id, qty, length_rem, finish, created_at, updated_at)
prd_wip(id, code, wo_id, item_id, created_at, updated_at)
prd_wo_detail_pm(id, main_id, code_tr, pm_id, note, created_at, updated_at)
prd_wo_detail_rm(id, main_id, rm_id, note, created_at, updated_at)
prd_wo_main(id, code, date, customer_id, so_id, fg_id, user_id, qty, status, ...)
prd_wo_serial_pm(id, detail_id, serial_id, qty, created_at, updated_at)
prd_wo_serial_rm(id, detail_id, serial_id, length_asal, length_book, qty_per_serial, length_rem, qty_serial, scrap, ...)
prd_mpp(id, plan_qty, created_at)
prd_mps(id, qty, status, created_at)
prd_mrp_main(id, status)
prd_mrp_detail(id, period, open_po, net_req_kg, suggestion)
prd_crp(id, process_id, load_hours)

-- TRANSAKSI PRODUKSI (existing) (26 tabel)
tr_ab_cut_det(id, main_id, serial_id, qty, created_at, updated_at)
tr_ab_cut_main(id, code, wip_id, user_id, cut_id, det_cut_id, process_id, date, machine_id, ...)
tr_ab_pro(id, pro_id, wip_id, cut_id, det_pro_id, process_id, pallet_code, code, date, ...)
tr_cut_detail(id, main_id, machine_id, start_time, end_time, finish, created_at, updated_at)
tr_cut_main(id, code, user_id, wip_id, no_dp, item_id, process_id, date, subcont, ...)
tr_cut_pal_pr(id, code, cut_id, qty, process_id, created_at, updated_at)
tr_cut_serial(id, detail_id, serial_id, qty, length_rem, finish, created_at, updated_at)
tr_dt_category(id, name_c_dt, descriptions, created_at, updated_at)
tr_dt_cut_detail(id, main_id, serial_item, tools_id)
tr_dt_cut_main(id, code, user_id, cut_id, det_cut_id, machine_id, no_dp, cat_id, descriptions, ...)
tr_dt_pro_detail(id, main_id, serial_tool, tools_id)
tr_dt_pro_main(id, pro_id, code, user_id, machine_id, det_pro_id, cut_id, cat_id, note, ...)
tr_inc_fg_det(id, code, main_id, pal_pro_code, item_id, cut_id, qty, wip_id, created_at, ...)
tr_inc_fg_main(id, code, date, user_id, created_at, updated_at)
tr_ng_cut(id, main_id, user_id, serial_id, qty, created_at, updated_at)
tr_ng_pro(id, main_id, user_id, qty, created_at, update_at)
tr_out_fg_det(id, main_id, item_id, fg_code, code, qty, created_at, updated_at)
tr_out_fg_main(id, code, code_do, date, user_id, created_at, updated_at)
tr_pro_detail(id, main_id, machine_id, qty_half, qty_full, created_at, updated_at)
tr_pro_main(id, code, wip_id, cut_id, item_id, user_id, process_id, pallet_code, no_dp, ...)
tr_pro_pal_pr(id, code, pro_id, cut_id, qty, pal_code_bf, status, process_id, created_at, ...)
tr_repair_cut(id, main_id, user_id, serial_id, qty, created_at, updated_at)
tr_pro_pallet(id, detail_id, cut_id, pallet_code, qty_half, qty_full, finish, created_at, updated_at)
tr_inc_fg_main_udf(id, code, user_id, date, created_at, updated_at)
tr_inc_fg_det_udf(id, main_id, pal_pro_code, item_id, no_lot, qty, created_at, updated_at)
tr_repair_pro(id, user_id, main_id, pallet_code, qty, created_at, updated_at)

-- SALES (baru) (8 tabel)
sls_so_main(id, cus_id, cus_po_no, status, created_at)
sls_so_detail(id, qty, tax_id)
sls_do_main(id, date, status, created_at)
sls_do_detail(id, item_id)
sls_inv_main(id, cus_id, dpp_nilai_lain, vat, total, tax_inv_no, due_date, status, created_at)
sls_inv_detail(id, item_id, amount)
sls_return(id, do_id, reason, created_at)
sls_forecast(id, period, qty)

-- SUBCONT (baru) (4 tabel)
sub_dn_main(id, po_id, status, created_at)
sub_dn_detail(id, item_id, qty)
sub_gr_main(id, po_id, qty_ok, status, created_at)
sub_progress(id, progress_pct, user_id)

-- QC (baru) (2 tabel)
qc_incoming_main(id, gr_id, inspector_id, created_at)
qc_incoming_det(id, standard)

-- ACCOUNTING (baru) (8 tabel)
acc_coa(id, acc_group, parent_id)
acc_period(id, status)
acc_journal_main(id, period, ref_type, status, user_id)
acc_journal_det(id, debit, memo)
acc_ap_pay_main(id, ven_id, status, created_at)
acc_ap_pay_det(id, amount)
acc_ar_rec_main(id, cus_id, status, created_at)
acc_ar_rec_det(id, amount)

-- COSTING (baru) (2 tabel)
cst_rate(id, rate_type, rate_per_hour)
cst_cogm(id, material_cost, foh_cost, scrap_recovery, unit_cost)

-- ASSET (baru) (2 tabel)
ast_main(id, name, useful_life, gr_detail_id, status, created_at)
ast_depre(id, amount)

-- LAINNYA (8 tabel)
log_prc(id, code_tr, date, user_id, ip_user, hostname, action, created_at, updated_at)
menus(id, name, link, parent_id, icon, created_at, updated_at)
status_id(id, status)
stock_check(id, user_id, date, item_id, rack_id, length, match, qty_data, qty_actual, ...)
users(id, username, name, email, identity, password, status_id, remember_token, created_at, ...)
user_menu_permissions(id, user_id, menu_id, can_view, can_create, can_edit, can_delete, can_download, can_import, ...)
warehouse_layout_positions(id, type, element_id, position_x, position_y, width, height, color, rack_ref_id, ...)
wip(id, code, user_id, no_dp, item_id, date, created_at, updated_at)
```

---

## 9. API Outline

Prefix `/api/v1` (ERP) & `/api/mes/v1` (MES), auth Bearer (Sanctum) + 2FA, response `{data, meta, errors}`:

```
POST   /auth/login /auth/verify-2fa | GET /auth/me (+menus & privileges)
CRUD   /items /makers /bom_main /wos_main /processes /machines /applicators /downtimes /vendors /customers
       /m_pricelist_main /import-quotas /m_uom /uom-conversions /m_warehouse /users /roles /menus /ast_main
GET    /import-quotas/{id}/card
POST   /vendor-quotations | POST /purchase-requests | /purchase-requests/{id}/approve
POST   /purchase-orders (multipart: quotation[]) | /purchase-orders/{id}/release
POST   /supplier-delivery-schedules/{id}/confirm
POST   /goods-receipts (auto-generate serial) | /goods-receipts/{id}/print (GR + daftar serial + label)
PATCH  /goods-receipts/{id}/serials/actualize (ukur/timbang aktual, OK/NG/VOID) | POST /goods-receipts/{id}/confirm
POST   /qas/incoming-inspections
POST   /cost-sheets | /cost-sheets/{id}/post
POST   /ap-invoices | /ar-invoices | /ar-invoices/{id}/efaktur-export
POST   /forecasts/import | GET /forecasts/accuracy | POST /sales-orders/from-forecast
POST   /mps/generate | /mpp/generate | /mrp/run | /crp/generate | /mrp/results/convert
POST   /work-orders | /work-orders/{id}/release | POST /kanbans
MES    POST /mes/menu-loadings | /mes/menu-loadings/{id}/approve | GET /mes/wos_main/print
MES    POST /mes/scan-results        # users[], m_machine[] — validasi aturan cutting/non-cutting
MES    POST /mes/material-issues /mes/material-returns /mes/ng-transactions /mes/breakdowns
MES    POST /mes/final-check-sheets | /mes/repair-fg | POST /mes/sync (offline buffer)
POST   /fg-receivings (RFG) | /fg-transfers | /fg-downgrades | /scrap-decisions
POST   /subcont-deliveries | /subcont-receipts
PORTAL /portal/pos /portal/pos/{id}/confirm /portal/asn /portal/progress
POST   /delivery-orders /shipping-orders | GET /reports/* (cogm, margin, aging, tax, quota, serial-tracking,
       operator-achievement, breakdown, forecast-accuracy)
```

---

## 10. User Stories & Acceptance Criteria Kunci

| # | User Story | Acceptance Criteria |
|---|---|---|
| 1 | Purchasing membuat PO impor dengan kuota terjaga | PO impor tanpa kode kuota tak bisa disimpan; release gagal bila saldo ton < estimasi; kartu kuota mencatat reservasi→realisasi aktual GRN |
| 2 | Warehouse menerima RM ter-serialisasi | GRN men-generate N serial = N pcs di awal & mencetak dokumen GR + daftar serial + label QR; saat pengecekan, panjang/berat aktual diinput dan serial NG/VOID dikoreksi di GR; posting hanya serial OK |
| 3 | Warehouse menerima PM per lot/box | 1 serial per lot/box dengan qty; scan lot berfungsi saat issue |
| 4 | Operator Cutting bekerja multi-mesin | 1 transaksi scan cutting: 1 user + ≥1 mesin; hasil per mesin tercatat; remaining length & kg terpotong otomatis |
| 5 | Operator proses lanjutan berdua | Transaksi non-cutting menolak user ke-3 / mesin ke-3 (error 422, ERP & MES) |
| 6 | QC memutuskan scrap | Remaining < min length BOM → otomatis kandidat scrap; override manual dua arah dengan alasan tercatat |
| 7 | PPIC menjalankan MRP & CRP | Netto benar (− stok − open PO − open WO); convert → PR/WO; CRP menampilkan loading vs kapasitas per proses/mesin |
| 8 | Sales: harga SO otomatis | SO tanggal X menarik pricelist valid pada X; tanpa pricelist aktif → blokir/override ber-approval; Convert Forecast→SO berfungsi |
| 9 | WMS FG transfer FG A → FG B | Hanya bila spec_group sama; stok & nilai pindah; serial tracking tetap utuh |
| 10 | FG NG kembali jadi material | Dokumen downgrade membuat serial/lot material baru (ukuran ukur ulang); jurnal reklasifikasi terbentuk |
| 11 | Vendor subcont via portal | Vendor hanya melihat PO miliknya; konfirmasi & ASN memicu notifikasi internal; penerimaan wajib QC |
| 12 | Accounting: PPN sesuai regulasi | Invoice BKP non-mewah: PPN = 12% × (11/12 × harga jual); ekspor e-Faktur valid Coretax; PPh 23 subcont + e-Bupot |
| 13 | Costing melihat COGM per WO | COGM = material aktual (proporsi length/kg) + labor (UMH) + FOH + subcont − scrap recovery; roll-up multi-level; varians tampil |
| 14 | MES tetap jalan saat offline | Scan tersimpan di buffer lokal; auto-sync saat online; tidak ada data hilang/duplikat |
| 15 | Manajemen approve dari HP | Mobile Approval menampilkan antrian approval semua dokumen; approve/reject tercatat di log historical |
| 16 | Admin mengatur menu & privilege | Menu tampil sesuai privilege; user vendor tidak pernah melihat menu internal |

---

## 11. Non-Functional Requirements

| Aspek | Target |
|---|---|
| Keamanan | RBAC granular, **2FA**, password policy, isolasi portal vendor, audit trail (Log Historical) semua transaksi |
| Performa | 200 user internal + 50 vendor concurrent; scan MES < 1 dtk respon; list 100k baris < 2 dtk; MRP 5.000 item < 10 menit (queue) |
| Offline | MES offline-capable (buffer & sync, deteksi duplikat idempotent) |
| Ketersediaan | 99,5%; backup harian + binlog; restore DR < 4 jam |
| Auditability | Dokumen bernomor, immutable setelah posting, period lock accounting |
| Lokalisasi | i18n ID/EN, format Indonesia, zona waktu WIB |
| Kompatibilitas | Browser modern, tablet/terminal Android (MES), printer barcode ZPL, scanner 1D/2D |
| Integrasi | API terbuka untuk automasi mesin (opsional), Coretax, template Excel up/download di modul-modul utama |

---

## 12. Roadmap Implementasi

| Fase | Durasi (indikatif) | Deliverable |
|---|---|---|
| 1. Fondasi | 6–8 minggu | Administrator (RBAC, menu, approval, 2FA, log), General Data Master, Engineering (item, BOM, WOS, UMH), theme UI FTPI |
| 2. Procurement & WMS RM | 8–10 minggu | PR/PO (semua tipe) + quotation, kuota impor, QAS, GRN + serialisasi dual-UoM, cost sheet, AP, scrap engine |
| 3. MES & QC | 8–10 minggu | Menu loading, WOS print, scan process (aturan cutting/non-cutting), kanban, NG/breakdown, final check sheet, offline mode |
| 4. Planning | 6–8 minggu | Forecast + analisis, MPS, MPP, MRP, CRP |
| 5. Order Mgmt, WMS FG, Shipping & Subcont | 6–8 minggu | SO (convert forecast), pricelist periode, RFG, FG transfer/downgrade, repair FG, DO/shipping/packing list, PO subcont + portal vendor |
| 6. Costing, Asset, ACC & FIN | 8–10 minggu | Inventory valuation, COGM, asset & depresiasi, GL/AP/AR, pajak Coretax/e-Bupot, closing |
| 7. Mobile, EIS & Go-Live | 4–6 minggu | Mobile Approval, Mobile EIS, dashboard, UAT, migrasi data, training, go-live, hypercare |

---

## 13. Glossary

| Istilah | Arti |
|---|---|
| MES | Manufacture Execution System — eksekusi & scan proses lantai produksi (modul dalam database `ab-erp`, prefix `tr_`; offline-capable via buffer terminal) |
| WOS | Work Order Sheet — routing/lembar kerja proses per item (urutan proses, cycle time, mesin prioritas) |
| QAS | Quality Assurance System — inspeksi incoming material |
| GRN | Good Receiving Note — dokumen penerimaan; serial di-generate di awal GR, dicetak bersama GR, lalu diaktualisasi (qty/berat/panjang, OK/NG/VOID) saat pengecekan |
| RFG | Receiving Finished Good — penerimaan FG ke warehouse dari produksi |
| FCS | Final Check Sheet — inspeksi akhir + rangkuman traceability sebelum RFG |
| Kanban | Kartu perintah issue material dari warehouse ke produksi (planned/unplanned) |
| UMH | Unit Man Hour — standar jam orang per proses (PP/HAV) untuk costing & CRP |
| MPS / MPP / MRP / CRP | Master Production Schedule / Monthly Production Planning / Material Requirement Planning / Capacity Requirement Planning |
| RM / PM / FG | Raw Material (serial per pcs) / Part Material (serial per lot/box; bisa berupa FG lain) / Finished Goods |
| PR / PO | Purchase Request / Purchase Order |
| SO / DO / DN | Sales Order / Delivery Order / Delivery Note |
| COGM / HPP | Cost of Goods Manufactured / Harga Pokok Produksi |
| DPP Nilai Lain | Dasar pengenaan pajak 11/12 × harga jual (PMK 131/2024) |
| Spec Group | Pengelompokan FG berspesifikasi sama untuk transfer antar kode FG |
| EIS | Executive Information System — dashboard manajemen (web & mobile) |
| NG | Not Good (reject) |

---

*Catatan: detail scope final per modul dituangkan dalam dokumen SRS (User Requirement Specification) yang ditandatangani user & developer, sebagaimana dinyatakan pada dokumen referensi. Istilah internal seperti HAV dan PP-HACV mengikuti definisi pada SRS.*
