# Gap Analysis — ab-erp ERP + MES Manufaktur Pipa

**Tanggal:** 28 Juli 2026 · **Direvisi:** 29 Juli 2026 (diverifikasi ulang terhadap kode aktual)
**Dasar:** PRD v3.0 · LLD v2.0 · Source code aktual (app/, resources/, routes/, database/)
**Tujuan:** Mengidentifikasi kesenjangan antara desain terdokumentasi dan implementasi aktual

---

## Ringkasan

**Proyek: ≈ 92% complete** terhadap scope PRD v3.0 *(direvisi 29 Juli 2026)*.

- **Sudah jadi:** 78 controller, 36 service, 155 model, 446 endpoint, 49 migrasi, 61 halaman React, 3 queue job. Fase 1–3 roadmap tuntas.
- **Terverifikasi:** 126 test hijau (772 assertion), build bersih, seluruh menu sidebar punya halaman (nol menu buntu), neraca seimbang & jurnal balance per dokumen.
- **Sisa pekerjaan:** ECN, Vendor Quotation/Contract & Supplier Item-Price, Delivery Schedule template, Product Family/Production Line, layout gudang visual, Inventory Valuation & margin, i18n, 2FA, OpenAPI, Events/Policies/Form Request, E2E Playwright.

> **Pembaruan 3 Agustus 2026 — prioritas 1–3 selesai.** Kalender kerja + hari libur
> menggantikan `isWeekend()` di MPS/CRP; lead time dipakai saat generate PR; Supplier
> Item & Price (multi-vendor) menjadi sumber syarat beli dan mengalir ke PR → PO; alert
> kuota & stok minimum berjalan harian dan tampil di dashboard; **ECN**, **Valuasi
> Persediaan RM/WIP/FG + analisis margin**, serta **Packing List, Shipping Order, dan
> template Delivery Schedule** sudah ada beserta halaman dan tesnya. Sisa: prioritas 4
> (kualitas kode — Policies/Form Request/state machine/OpenAPI/i18n/2FA/E2E), Vendor
> Quotation & Contract, Product Family/Production Line, layout gudang visual, mobile EIS.
> Status terverifikasi: **160 test hijau (975 assertion)**.

---

## 1. Core Engines (LLD Bab 4) — ✅ LENGKAP

Keenam shared service sudah ada dan terpakai.

| Engine | LLD § | Status | Catatan |
|--------|-------|--------|---------|
| **NumberingService** | §4.1 | ✅ | dipakai 27 berkas |
| **ApprovalEngine** | §4.2 | ✅ | 16 tipe dokumen lewat `registry()`; endpoint approve/submit generik |
| **TaxEngine** | §4.3 | ✅ | dipanggil `LineTax`; DPP Nilai Lain 11/12, efektif tanggal, mewah/non-mewah |
| **JournalEngine** | §4.4 | ✅ | period lock + balanced + idempotent |
| **UomConversionService** | §4.5 | ✅ | dipakai 6 berkas (kg↔mm proporsional per serial) |
| **SerialService** | §4.6 | ✅ | `generateForGrLine()`, `actualize()`, `consume()` + optimistic lock kolom `version` |

Yang belum: state machine per dokumen (`app/States/{Doc}State.php`). Status masih
kolom biasa yang dikawal ApprovalEngine — cukup untuk saat ini, tapi bukan
bentuk yang diminta LLD.

---

## 2. Fitur PRD

| No | Fitur | PRD § | Status | Catatan |
|----|-------|-------|--------|---------|
| 1 | **QAS / Quality Inspection** | §4.6 | ✅ | Controller + halaman; hasil ukur per parameter di `qc_incoming_det` + Master Inspection |
| 2 | **Kanban System** | §4.9 | ✅ | Buat dari baris RM Work Order, issue per serial, return remnant |
| 3 | **Final Check Sheet (FCS)** | §4.9, §5.6 | ✅ | Gate sebelum RFG; approve membuat lot FG dan membekukan jejak telusur |
| 4 | **FG Transfer** | §4.10 | ✅ | Validasi `spec_group` di server |
| 5 | **FG Downgrade** | §4.10 | ✅ | Mapping FG→material; jurnal reklasifikasi |
| 6 | **CRP (Capacity Planning)** | §4.5 | ✅ | Per proses × mesin, bottleneck ditandai; jalan di queue, bisa multi-bulan |
| 7 | **General Store (Non-Material)** | §4.8 | ✅ | Permintaan ber-approval → pengeluaran dengan cost center & tautan aset |
| 8 | **Supplier Delivery Confirmation** | §4.7 | ✅ | Portal vendor: lihat & konfirmasi jadwal, usul tanggal lain |
| 9 | **Putaway + Booking Serial WO** | §5.3b | ✅ | Scan serial→rak; booking/unbook serial ke WO |
| 10 | **ECN (Engineering Change Notice)** | §4.3 | ✅ | *(3 Agu 2026)* Notice berjenjang DRAFT→SUBMITTED→APPROVED→APPLIED; whitelist kolom, cek data berubah sejak disetujui, revisi item/BOM/routing dinaikkan saat diterapkan |
| 11 | **BOM Compare/Copy/WhereUsed** | §4.3 | ✅ | Halaman Alat Bantu BOM |
| 12 | **Forecast Analysis (MAPE/BIAS)** | §4.4 | ✅ | Query-nya sempat salah kolom; sudah diperbaiki |
| 13 | **Supplier Delivery Schedule** | §4.7 | ✅ | *(3 Agu 2026)* Template CSV berisi baris PO yang benar-benar terbuka; impor balik menerima file CSV, baris tanpa tanggal dilewati |

---

## 3. MES & Offline (PRD §2.1, LLD §6.2) — ✅ LENGKAP

| Persyaratan | Status | Implementasi |
|-------------|--------|--------------|
| **PWA / Service Worker** | ✅ | `vite-plugin-pwa` + `resources/js/pwa.js`; `/sw.js` disajikan dari root agar scope-nya mencakup halaman MES |
| **IndexedDB offline queue** | ✅ | Dexie di `stores/offlineQueue.js`; interceptor axios mengantre tulisan MES saat jaringan mati |
| **client_uuid idempotency** | ✅ | Middleware `client-uuid` membungkus seluruh blok route MES dan mencatat `mes_oplog` setelah sukses — dedup berlaku online maupun saat flush |
| **Sync offline→online** | ✅ | `POST mes/sync` me-*replay* panggilan API asli lewat router (bukan duplikasi logika) |
| **24-hour offline limit** | ✅ | `MesSyncService::MAX_OFFLINE_HOURS`; operasi lewat batas ditolak dengan alasan jelas |
| **Snapshot WO/BOM/WOS di terminal** | ✅ | `GET mes/snapshot` — WO released, routing, mesin, shift, kategori downtime |

---

## 4. Infrastruktur & Non-Functional

| Aspek | PRD/LLD Target | Status Aktual |
|-------|---------------|---------------|
| **Background Jobs (Queue)** | LLD §2.2: `RunMrpJob`, `RunCrpJob`, `GenerateCogmJob`, `QuotaAlertJob`, `MinStockAlertJob` | ⚠️ MRP, CRP, COGM sudah di queue. **QuotaAlertJob & MinStockAlertJob belum.** |
| **Events & Listeners** | LLD: `GrnPosted`, `ScanResultRecorded`, `JournalPosted` sebagai event | ❌ **Belum.** Audit trail masih inline lewat `AuditLogger`. |
| **Optimistic Locking** | LLD §5.4: kolom `version` | ✅ Kolom `version` di `prd_wo_serial_rm` + write bersyarat di `SerialService::consume`; bentrok balas 409 |
| **Policies (Authorization)** | LLD: Policy per model | ❌ **Belum.** AuthZ lewat middleware `CheckPermission` + guard `vendor`. |
| **Period Lock Middleware** | LLD: `EnsurePeriodOpen` pada endpoint posting | ✅ Terpasang di **20 endpoint**; periode diturunkan dari tanggal dokumen; balas 423 |
| **Form Request Validation** | Praktik standar Laravel | ❌ **Belum.** Validasi masih inline di controller. |
| **OpenAPI / API Docs** | LLD §7: `storage/api-docs` | ❌ **Belum.** |
| **Error Code Registry** | LLD §10: `ErrorCodes.php` | ✅ Ada; status HTTP mengikuti sufiks kode (`*_LOCKED`→423, `*_DUP`→409, `*_SCOPE`→403) |
| **Modular Monolith** | LLD §2.1: `app/Modules/{Modul}/` | ⚠️ Tetap flat: `app/Http/Controllers/Api/{Modul}/`. Memadai untuk skala saat ini. |
| **i18n ID/EN** | PRD §7 | ❌ **Belum.** UI Bahasa Indonesia campur Inggris, tanpa mekanisme translation. |
| **TOTP 2FA** | PRD §2.3, LLD §9 | ❌ **Belum.** Sanctum token sudah ada. |
| **Vendor Guard Terpisah** | LLD §9: guard `vendor` | ✅ `users.ven_id` + middleware `vendor`; `perm:` menolak akun vendor sehingga isolasinya dua arah |

---

## 5. Testing

| Level | Target LLD | Aktual | Status |
|-------|-----------|--------|--------|
| **Unit/Feature core** | TaxEngine, ScrapService, SerialService.consume, aturan MES | LineTax, Scrap (6), Serial optimistic lock, ErrorCodes | ✅ |
| **Feature/API** | Workflow dokumen, approval berjenjang, 3-way match, period lock 423, idempotensi client_uuid | ApprovalEngine (8), PeriodLock (5), MesSync (5), Vendor portal (4) | ✅ |
| **Integrasi** | MRP end-to-end, COGM roll-up, offline queue dedup | MrpNetting (17), StockAndReport (9), MultiPeriodRun (7) | ✅ |
| **E2E (Playwright)** | Alur RM dan FG penuh | Tidak ada | ❌ |

**Total:** 22 berkas test, **126 test hijau / 772 assertion**. Dijalankan di DB
MySQL dev dalam transaksi yang di-rollback, bukan sqlite — skema legacy tidak
bisa direplikasi migrasi penuh.

Yang belum: E2E browser (Playwright).

---

## 6. Tax Compliance (PRD §6) — ✅ LENGKAP

| Fitur | Status | Catatan |
|-------|--------|---------|
| **PPN PMK 131/2024** (DPP Nilai Lain 11/12) | ✅ | `TaxEngine` terpusat, tarif efektif per tanggal, mewah/non-mewah |
| **e-Faktur / Coretax Export** | ✅ | `TaxExportService::efakturXml()` — XML bulk Coretax; nilai di-escape (nama ber-`&` sempat memecah XML) |
| **PPh 22 Impor** | ✅ | `ImportTaxService` — DPP = CIF + bea masuk, tarif 2,5% (API) / 7,5%; **tidak ikut alokasi landed cost** karena kredit pajak |
| **PPh 23 Subcont & e-Bupot** | ✅ | Dipotong di invoice jasa; `ebupot23Csv()` untuk e-Bupot Unifikasi |
| **Kurs KMK** | ✅ | `m_rate` + `KursService` — kurs terakhir yang terbit pada/sebelum tanggal dokumen |
| **Period Lock Accounting** | ✅ | Middleware `period.open` di **20 endpoint posting**, balas 423; periode diturunkan dari tanggal dokumen sehingga backdating tertangkap |

Yang belum: rekap SPT Masa PPN, dan rekonsiliasi faktur masukan untuk pengkreditan.

---

## 7. Arsitektur & Code Quality

### Kelebihan Arsitektur Saat Ini
- Response envelope standar (`ApiResponse`) — dipakai seluruh controller
- Permission middleware per route (`perm:menu,action`)
- Audit trail append-only (`AuditLogger`)
- Single database (ERP+MES) — sesuai LLD, tanpa kompleksitas sinkronisasi
- Base CRUD controller — reusable pattern untuk master data
- Migrasi terstruktur — evolusi jelas per hari

### Sudah Diperbaiki
- **Job/Queue** — MRP, CRP, COGM berjalan di queue; job gagal menandai run `FAILED`, tidak menggantung `PROCESSING`
- **Service Layer** — 36 service; logika bisnis keluar dari controller
- **Error Code Registry** — status HTTP mengikuti sufiks kode

### Yang Masih Bisa Diperbaiki
- **Events:** `GrnPosted`, `ScanResultRecorded`, `JournalPosted` → loose coupling antar modul
- **Form Request:** validasi masih inline di controller
- **Policies:** otorisasi masih murni middleware
- **Repository Pattern:** query kompleks (MRP, COGM) masih menempel di service

### Pelajaran: waspadai "kode yang terlihat jadi"

Beberapa service ditulis lengkap tapi menunjuk tabel/kolom yang tidak pernah ada,
jadi tidak akan pernah jalan. Ditemukan dan diperbaiki:

| Berkas | Masalah |
|---|---|
| `ScrapService` | tabel `prd_bom`, `prd_bom_line`, `prd_scrap_decisions` tidak ada |
| `CrpService` | `prd_mps.period`, `plan_qty`, `process_main_id` tidak ada |
| `ForecastAnalysisService` | `sls_so_detail.so_id`, `sls_so_main.period` tidak ada |
| `MrpService::openPo()` | filter status pakai angka, padahal PO memakai string → pasokan berjalan selalu 0 |
| `MrpService::run()` | tidak mengisi `user_id` NOT NULL → MRP lewat queue selalu gagal |
| `EnsurePeriodOpen` | terdaftar sebagai alias tapi dipakai di **nol** route |
| `ApprovalController` | tidak punya endpoint `approve` sama sekali |
| `GeneralStoreService` | join ke tabel `uoms`/`makers` dan kolom `asset_name` yang tidak ada |
| `TaxExportService::esc()` | placeholder tanpa escape → nama ber-`&` memecah XML e-Faktur |

**Cara cek cepat sebelum mempercayai sebuah service:** pastikan namanya muncul di
`routes/api.php` (kalau tidak, fiturnya tak terjangkau dari UI), dan cocokkan
kolomnya dengan `Schema::getColumnListing('<tabel>')`.

---

## 8. Prioritas Perbaikan — Rekomendasi

### Fase 1 — Core Engines + Tax — ✅ SELESAI
| No | Item | Status |
|----|------|--------|
| 1 | **SerialService** `generateForGrLine()`, `actualize()`, `consume()` | ✅ + optimistic lock |
| 2 | **TaxEngine** `calcVat()`, efektif tanggal, mewah/non-mewah | ✅ |
| 3 | **ApprovalEngine** multi-level + trait `HasApproval` | ✅ 16 tipe dokumen |
| 4 | **UomConversionService** dual UoM (kg↔mm per serial) | ✅ |
| 5 | MRP ke background queue (`RunMrpJob`) | ✅ + CRP & COGM |
| 6 | Period Lock middleware | ✅ 20 endpoint |

### Fase 2 — Alur Produksi — ✅ SELESAI
| No | Item | Status |
|----|------|--------|
| 7 | Kanban Controller + routes + frontend | ✅ |
| 8 | Final Check Sheet (FCS) → RFG flow | ✅ |
| 9 | FG Transfer + FG Downgrade endpoints | ✅ |
| 10 | QAS Controller (inspection workflow) | ✅ + parameter & hasil ukur |
| 11 | ScrapService `evaluate()` + `decide()` | ✅ ditulis ulang — versi lama memakai tabel yang tidak ada |
| 12 | General Store Controller | ✅ |
| 13 | CRP Controller | ✅ |

> Catatan: Fase 2 sempat "selesai" hanya di sisi backend — sepuluh modul punya
> controller & route tapi tanpa halaman sama sekali, tujuh di antaranya sudah
> terdaftar di sidebar sehingga berujung "Halaman tidak ditemukan". Semua sudah
> punya halaman sekarang.

### Fase 3 (Minggu 5-6) — MES Offline + Portal — ✅ SELESAI
| No | Item | Status | Implementasi |
|----|------|--------|--------------|
| 14 | Service Worker + PWA manifest | ✅ | `vite.config.js` (VitePWA), `resources/js/pwa.js`, `/sw.js` + `/manifest.webmanifest` disajikan dari root agar scope-nya mencakup halaman MES |
| 15 | IndexedDB offline queue (Dexie) | ✅ | `stores/offlineQueue.js` — antre, kedaluwarsa 24 jam, flush, snapshot master |
| 16 | `client_uuid` idempotency di semua MES scan endpoint | ✅ | Middleware `client-uuid` membungkus seluruh blok route MES; mencatat `mes_oplog` setelah sukses sehingga dedup juga berlaku saat online |
| 17 | Sync endpoint + flush queue logic | ✅ | `MesSyncService` me-*replay* panggilan API asli lewat router (`POST mes/sync`), plus `GET mes/snapshot` untuk data master offline |
| 18 | Vendor portal guard + isolated routes | ✅ | `users.ven_id` + middleware `vendor`, `/api/v1/vendor/*`; `perm:` menolak akun vendor sehingga kedua populasi terpisah |
| 19 | E-Faktur / Coretax XML export | ✅ | `TaxExportService::efakturXml()` — XML bulk Coretax, PPN dipecah per baris proporsional |
| 20 | PPh 23 e-Bupot export | ✅ | `TaxExportService::ebupot23Csv()` — CSV e-Bupot Unifikasi dari `prc_inv_main.wht23` |

Catatan: scan MES yang gagal terkirim otomatis masuk antrean lewat interceptor axios
(`api/client.js`) dan dikirim ulang saat koneksi kembali; status ditampilkan oleh
`features/production/OfflineBar.jsx` di halaman Cutting dan Processing.

### Fase 4 (Bulan 2) — Testing + Optimalisasi
| No | Item | Effort | Impact |
|----|------|--------|--------|
| 21 | Unit/feature test core: scrap, approval, period lock, pajak impor, opname | ✅ | 90 test hijau |
| 22 | Feature test: workflow dokumen, approval berjenjang, period lock 423 | ✅ | Selesai |
| 23 | Events: `GrnPosted`, `ScanResultRecorded`, `JournalPosted` | ❌ | Belum |
| 24 | Optimistic Lock di consume serial | ✅ | Kolom `version` + write bersyarat di `SerialService::consume` |
| 25 | i18n ID/EN | ❌ | Belum |
| 26 | BOM Compare/Copy/WhereUsed | ✅ | Halaman Alat Bantu BOM (ECN masih belum) |
| 27 | Forecast Analysis (MAPE/BIAS) | ✅ | Halaman + perbaikan query yang sebelumnya salah kolom |

---

## 8b. Pekerjaan 28 Juli 2026 (lanjutan setelah Fase 3)

**Sepuluh modul yang backend-nya sudah jadi tapi tanpa layar** kini punya halaman:
QAS, CRP, Kanban, FCS, FG Transfer, FG Downgrade, General Store (permintaan &
pengeluaran), Putaway, Analisis Forecast, dan Alat Bantu BOM. Tujuh di antaranya
sudah terdaftar di sidebar sehingga sebelumnya berujung "Halaman tidak ditemukan".

**Bug yang ditemukan saat pengerjaan** (semuanya kode mati yang tidak akan pernah jalan):

| Berkas | Masalah | Perbaikan |
|---|---|---|
| `ScrapService` | Ditulis untuk tabel `prd_bom`, `prd_bom_line`, `prd_scrap_decisions` yang tidak ada | Ditulis ulang ke `m_bom`/`m_bom_det_rm`; tabel keputusan dibuat |
| `CrpService` | Query `prd_mps.period`, `plan_qty`, `process_main_id` — tidak ada | Periode diturunkan dari `plan_date`, qty dari `qty` |
| `ForecastAnalysisService` | Join `sls_so_detail.so_id` dan `sls_so_main.period` — tidak ada | `main_id` + periode dari `date` |
| `EnsurePeriodOpen` | Terdaftar tapi dipakai di **0 route**; hanya membaca field `period` | Dipasang di 20 endpoint posting; periode diturunkan dari tanggal dokumen; balas 423 |
| `ApprovalController` | Tidak punya endpoint `approve` sama sekali | `POST approvals/{id}/approve` + `submit` generik |
| `ApprovalEngine` | `flows()` dan `resolveDoc()` terpisah sehingga dokumen bisa hilang dari inbox | Digabung jadi satu `registry()`, 16 tipe dokumen |
| `MesSyncService` | Duplikasi logika MES dengan nama kolom salah | Replay panggilan API asli lewat router |

**Yang ditambahkan:** Scrap RM (kandidat + keputusan manual dua arah berikut
alasannya), Kurs KMK berperiode + PPh 22 impor sebagai kredit pajak (tidak masuk
landed cost), Master Inspeksi + hasil ukur per parameter QAS, Master Defect,
progres vendor di portal, registry error code (status HTTP mengikuti sufiks kode),
optimistic lock pada konsumsi serial, CRP & COGM ke background queue, Opname &
Adjustment stok dengan jurnal selisih, serta Neraca, Laba Rugi, dan Aging AP/AR.

---

## 8c. Prioritas 1 — Kebenaran Penjadwalan (29 Juli 2026)

Dikerjakan karena ketiganya membuat **fitur yang sudah dipakai** memberi jawaban
yang salah, bukan sekadar fitur yang belum ada.

| Masalah | Sebelum | Sesudah |
|---|---|---|
| **Hari kerja** | `if (! $d->isWeekend())` di-hardcode di MpsController — libur nasional, cuti bersama, dan shutdown dianggap hari kerja | Tabel `m_work_calendar` + `WorkCalendarService`; MPS menjadwalkan hanya ke hari kerja nyata |
| **Kapasitas mesin** | `$machine->available_hours ?? 24) * 24` — **kolom itu tidak ada**, jadi selalu 576 jam/bulan dan semua mesin tampak lowong | Kapasitas = jam mesin × hari kerja dari kalender; ada `m_machine.daily_hours` untuk mesin 1 shift |
| **Lead time** | Kolom `lead_time_days` ada tapi **tidak dibaca kode mana pun**; PR selalu bertanggal awal periode kebutuhan | `need_date` mundur sebesar lead time; kalau sudah lewat, diberi tanggal hari ini + peringatan `TERLAMBAT` |

**Dampak nyata di data demo:** kapasitas turun dari 576 → 352 jam (22 hari kerja
× 16 jam), dan dua mesin single-shift langsung terlihat **overload 139% & 161%**
— bottleneck yang selama ini tersembunyi di balik angka palsu.

Bulan tanpa kalender jatuh ke asumsi lama (Senin–Jumat, 16 jam) supaya instalasi
baru tetap jalan, tapi **asumsi itu ditandai terang-terangan** di layar Kalender
Kerja — fallback diam-diam justru cara libur lolos ke jadwal.

### Master Hari Libur

Generate kalender semula mengandalkan planner mengetik tanggal libur tiap bulan;
sekali lupa, produksi terjadwal di hari raya tanpa peringatan apa pun. Sekarang
ada master `m_holiday` yang ditarik otomatis saat generate.

| Tipe | Perlakuan |
|---|---|
| `NASIONAL` | pabrik tutup |
| `CUTI_BERSAMA` | boleh tetap jalan dengan jam lebih pendek (kru minimal) |
| `PERUSAHAAN` | shutdown maintenance, opname, HUT — tidak ada di kalender pemerintah |

Ketiganya dipisah karena diperlakukan berbeda: menyamakan cuti bersama dengan
libur nasional membuat kapasitas salah di salah satu arah.

**Tanggal libur tidak di-hardcode.** Libur nasional Indonesia ditetapkan SKB 3
Menteri tiap tahun, tanggal hari raya Islam bergeser, dan cuti bersama adalah
keputusan pemerintah — tidak ada rumusnya. Seeder hanya mengisi contoh yang
diberi nama `CONTOH — … (ganti dengan SKB)`; tanggal palsu yang terlihat masuk
akal lebih berbahaya daripada tabel kosong karena orang akan mempercayainya.

Catatan implementasi: `WorkCalendarService` sengaja **tidak memoisasi** hasil
query. Versi pertama menyimpan cache per-instance dan langsung terbukti basi —
DB sudah berisi 20 hari kerja, service masih menjawab 21.

---

## 9. Ringkasan Final

### Kategori "Sudah Siap"
- ✅ **Master Data** — Items, Contacts, UoM, Currency, Tax, Machine, Process, Categories
- ✅ **Procurement** — PR→PO→GR→Invoice→Cost Sheet (full lifecycle + import quota)
- ✅ **WMS RM** — Incoming, Outgoing, Remaining, Stock
- ✅ **WMS FG** — Incoming, Outgoing, Stock
- ✅ **Sales** — Forecast, Pricelist, SO→DO→Invoice→Return
- ✅ **Production Planning** — MPP generate/approve, MPS with finite capacity scheduling, MPS reschedule workflow, MRP run
- ✅ **MES Online** — Cutting, Processing (with pallet traceability), Abnormal, Downtime, NG, Repair
- ✅ **Work Orders** — CRUD, release/close/cancel, BOM explosion, serial tracking
- ✅ **Costing** — COGM (material+labor+FOH+subcont+scrap recovery), standard cost rates, assets & depreciation
- ✅ **Accounting** — COA, Periods, Journals (with reversal & trial balance), AR/AP
- ✅ **Auth & RBAC** — Sanctum, dynamic menus, permission per action, audit trail
- ✅ **Dashboard** — KPI aggregator multi-modul
- ✅ **Core Engines** — Serial, Approval, Tax, UomConversion (Fase 1)
- ✅ **QAS, Kanban, FCS, FG Transfer/Downgrade, CRP, General Store** (Fase 2)
- ✅ **MES Offline** — PWA + antrean IndexedDB + idempotensi `client_uuid` + sync replay (Fase 3)
- ✅ **Portal Vendor** — Route terisolasi untuk PO, jadwal kirim, dan DN subcont (Fase 3)
- ✅ **Ekspor Pajak** — e-Faktur Coretax XML dan e-Bupot PPh 23 CSV (Fase 3)
- ✅ **Layar lengkap** — seluruh menu sidebar punya halaman; tidak ada lagi menu buntu
- ✅ **Period Lock** — 20 endpoint posting menolak periode tertutup dengan 423
- ✅ **Approval** — 16 tipe dokumen termasuk master item/BOM/WOS/vendor/forecast/kuota/aset
- ✅ **Scrap RM** — kandidat otomatis + keputusan manual dua arah dengan alasan tercatat
- ✅ **Pajak Impor** — Kurs KMK berperiode dan PPh 22 sebagai kredit pajak
- ✅ **Opname & Adjustment** — selisih hitung fisik masuk jurnal selisih persediaan
- ✅ **Laporan Keuangan** — Neraca, Laba Rugi, Aging AP/AR
- ✅ **Background Jobs** — MRP, CRP, dan COGM berjalan di queue
- ✅ **MRP bernetting benar** — permintaan = tertinggi dari MPP/forecast/SO; dikurangi stok, WO berjalan, PO, PR terbuka; dibulatkan ke MOQ & kelipatan supplier; menghasilkan PR draft
- ✅ **Seeder demo terhubung** — 7.400+ baris di 62 tabel, satu bulan operasi penuh, nol baris yatim
- ✅ **Pemilih bulan** — semua modul memakai kontrol bulan; CRP/MRP/COGM/Depresiasi bisa multi-bulan

### Kategori "Belum Siap / Perlu Ditambahkan"
- ✅ **ECN** — kontrol revisi item/BOM/routing, berlaku per tanggal efektif *(3 Agu 2026)*
- ⚠️ **Vendor Quotation & Contract** belum ada; **Supplier Item & Price** (multi-vendor berharga) sudah *(31 Jul 2026)*
- ✅ **Delivery Schedule, Packing List, Shipping Order** *(3 Agu 2026)*
- ✅ **Working Calendar + Master Hari Libur** — libur nasional / cuti bersama / libur perusahaan; dipakai MPS dan kapasitas CRP
- ❌ **Product Family, Production Line**
- ❌ **Layout Gudang Visual** — tabel `warehouse_layout_positions` masih kosong
- ✅ **Inventory Valuation RM/WIP/FG & analisis margin** — RM rata-rata landed cost/kg, WIP biaya material saja, FG biaya aktual per lot; margin per produk diambil dari lot yang benar-benar dikirim *(3 Agu 2026)*
- ❌ **Mobile Approval/EIS** — tidak ada frontend mobile
- ❌ **TOTP 2FA, i18n ID/EN, OpenAPI**
- ❌ **Events/Listeners, Policies, Form Request** — validasi masih inline di controller
- ⚠️ **Testing** — 126 test hijau (772 assertion); **E2E Playwright belum ada**

---

*Digenerate dari analisis terhadap PRD v3.0, LLD v2.0, dan source code aktual per 28 Juli 2026;
diverifikasi ulang baris-per-baris terhadap kode pada 29 Juli 2026 setelah Fase 1–3 dikerjakan.*

**Cara memverifikasi ulang dokumen ini:**

```bash
php artisan test                      # 126 hijau
php artisan route:list | tail -1      # 446 route
npm run build                         # bersih
```
