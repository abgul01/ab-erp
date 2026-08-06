# PRD — Modul New Product Development (NPD)

**Perusahaan:** PT Fusoh Tube Part Indonesia (FTPI)
**Sistem:** Modul NPD di dalam AB-ERP (Laravel 13 REST API + React SPA + MySQL)
**Versi Dokumen:** 2.0 — disesuaikan untuk AB-ERP
**Status:** Untuk direview
**Dokumen asli:** `PRD_Modul_NPD_FTPI.v1-asli.md` (dipertahankan untuk pembanding)
**Dokumen induk:** `PRD-ERP-Manufaktur-Pipa.md` — NPD sudah tercantum di sana
sebagai modul opsional fase lanjut, dan jalur PR/PO NPD sudah disiapkan sejak awal
(`pr_type = 'NPD'` sudah ada di `PrController`).

---

## 0. Apa yang berubah dari versi 1, dan mengapa

Versi 1 ditulis sebagai **modul berdiri sendiri**: punya tabel `customers`, `users`
dengan kolom `role`, tabel approval sendiri, tabel change request sendiri, dan
BOM sendiri yang tidak menunjuk master mana pun. Kalau dibangun apa adanya di
AB-ERP, hasilnya adalah sistem kedua di dalam sistem pertama — pelanggan
tercatat dua kali, approval punya dua mesin, dan BOM hasil NPD tidak bisa
dipakai produksi tanpa diketik ulang.

Versi 2 ini mempertahankan seluruh kerangka APQP/PPAP-nya (yang memang benar dan
sesuai IATF 16949), tetapi **menyambungkannya ke yang sudah berjalan**. Tiga
prinsip penyesuaian:

1. **Jangan buat kembar.** Apa pun yang sudah punya mesin di AB-ERP dipakai
   ulang: approval, ECN, master pelanggan, master item, BOM, routing, parameter
   inspeksi, tarif biaya, harga supplier, penomoran dokumen.
2. **Setiap keluaran NPD harus bisa langsung dipakai produksi.** Preliminary BOM,
   process flow, dan control plan bukan lampiran Excel — ketiganya menjadi
   `m_bom`, `m_process_main`, dan `m_item_inspection` yang sesungguhnya saat
   proyek diserahterimakan.
3. **Hak akses mengikuti aturan AB-ERP.** Peran bukan kolom teks di tabel user,
   melainkan menu + hak per aksi. Kunci izin **wajib sama persis** dengan link
   menu, karena `perm:` mencocokkan keduanya — kunci yang tidak punya menu
   membuat layarnya mati untuk semua orang kecuali super admin.

---

## 1. Ringkasan

Modul NPD mengelola siklus hidup pengembangan produk baru dari RFQ pelanggan
sampai part siap mass production, terstruktur dalam 5 fase APQP dengan gate
approval, dan ditutup dengan submission PPAP.

Bagi FTPI, modul ini menjadi satu tempat untuk drawing, hasil trial, FMEA,
control plan, costing, dan approval — menggantikan sebaran Excel dan email. Bagi
AB-ERP, modul ini adalah **hulu dari master data**: part yang hari ini tidak ada
di `m_item` lahir dari sini, lengkap dengan BOM, routing, dan parameter
inspeksinya.

---

## 2. Peta penyesuaian: modul lama → AB-ERP

| Kebutuhan NPD (v1) | Di AB-ERP | Keputusan |
|---|---|---|
| Tabel `customers` | `m_contacts` (`category_id` = 3 Customer) | **Pakai yang ada** |
| `users.role` (teks) | `users` + `menus` + `permissions` per aksi | **Pakai yang ada**, peran jadi menu |
| `npd_approvals` (gate) | `approvals` + `ApprovalEngine` (multi-level, jejak audit, inbox) | **Hapus**, daftarkan gate ke registry |
| `npd_change_requests` (ECR/ECN) | `eng_ecn_main` / `eng_ecn_det` + `EcnService` | **Hapus**, tambah kolom `npd_project_id` |
| `npd_bom_items.item_code` (teks) | `m_item` | **Ganti jadi FK**, plus penanda part baru |
| `npd_bom_items.supplier_id`, `unit_cost` | `m_supplier_item` (harga, MOQ, lead time per vendor) | **Pakai yang ada** sebagai sumber harga |
| `npd_trials.machine` (teks) | `m_machine` | **Ganti jadi FK** |
| `npd_trial_results.characteristic` (teks) | `m_inspection_param` + `m_item_inspection` | **Ganti jadi FK**, spesifikasi dari master |
| Estimasi biaya proses | `cst_rate` (tarif labor/FOH per jam per proses) | **Pakai yang ada** |
| Quotation ke pelanggan | `m_pricelist_main` / `m_pricelist_det` (berperiode, min qty) | **Sambungkan** saat quotation disetujui |
| Process flow | `m_process_main` + `m_process_main_det` + `m_route_time` | **Sambungkan** saat handover |
| Pembelian material trial | PR `pr_type = 'NPD'` (sudah ada di `PrController`) | **Pakai yang ada** |
| Penomoran dokumen | `NumberingService` (`PREFIX/YYYY/MM/00001`) | **Pakai yang ada** |
| Livewire | React SPA + REST `/api/v1/*` + Sanctum | **Ganti teknologi** |
| Login Active Directory | Sanctum username/password | **Di luar lingkup**, masuk backlog |
| i18n ID/EN/JP | Belum ada di AB-ERP (backlog prioritas 4) | **Di luar lingkup**, label Indonesia |
| File di SharePoint | Laravel storage disk, path di DB | **Ganti**, disk bisa diarahkan nanti |

**Dampak:** dari 21 tabel yang diusulkan v1, **2 dihapus** (approval, change
request), **6 berubah menjadi FK ke master yang ada**, dan **13 tabel baru**
tersisa untuk dibangun.

---

## 3. Ruang lingkup

### Termasuk
- Intake RFQ/inquiry dan feasibility study (Go / No-Go / Conditional).
- Proyek NPD dengan 5 fase APQP, task, milestone, dan tim proyek.
- Deliverable per fase dengan status dan penanggung jawab.
- Dokumen dengan kontrol versi dan revisi.
- Preliminary BOM yang menunjuk `m_item`, termasuk part yang belum ada.
- Estimasi biaya memakai `cst_rate` dan `m_supplier_item`, lalu quotation.
- Trial/prototype dengan hasil ukur per parameter inspeksi.
- DFMEA dan PFMEA dengan RPN otomatis.
- Control Plan.
- PPAP submission + checklist 18 elemen.
- Gate approval lewat `ApprovalEngine`.
- **Handover**: membuat `m_item`, `m_bom`, `m_process_main`, `m_route_time`, dan
  `m_item_inspection` dari hasil proyek dalam satu transaksi.
- Dashboard status dan laporan lead time.

### Tidak termasuk (fase awal)
- Eksekusi mass production (hanya handover, bukan penjadwalan).
- Integrasi CAD/PLM otomatis — drawing diunggah manual.
- SPC real-time dari mesin — input manual; MES sudah mencatat aktual produksi
  dan bisa disambungkan menyusul.
- SSO/Active Directory, i18n, dan tanda tangan digital.
- Kapitalisasi biaya tooling ke aset tetap (`ast_main`) — dicatat sebagai biaya
  proyek dulu, akuntansinya menyusul bila diminta.

---

## 4. Arsitektur dan teknologi

Mengikuti AB-ERP apa adanya:

| Lapis | Teknologi | Catatan |
|---|---|---|
| API | Laravel 13, REST di `routes/api.php` prefix `/api/v1` | `crudRoutes()` untuk CRUD standar |
| Auth | Sanctum token | Sama dengan modul lain |
| Otorisasi | Middleware `perm:<link-menu>,<aksi>` | Kunci **wajib** sama dengan link menu |
| UI | React SPA (`resources/js/features/npd/`) | Bukan Livewire |
| DB | MySQL, tabel prefix `npd_`, pola `*_main` / `*_det` | Konsisten dengan `prc_`, `prd_`, `sls_` |
| Approval | `ApprovalEngine` + tabel `approvals` | Registry per `doc_type` |
| Penomoran | `NumberingService` | `NPD/2026/08/00001` |
| Error | `BizException` + `ErrorCodes` | Status HTTP mengikuti sufiks kode |
| Audit | `AuditLogger` | Sudah dipakai seluruh modul |
| File | `storage/app/npd/{project}/...`, path di `npd_doc` | Pola mengikuti `prc_po_att` |
| Tes | Pest, DB dev dalam transaksi rollback | Seperti 186 tes yang ada |

---

## 5. Peran pengguna dan hak akses

Peran **tidak** disimpan sebagai kolom di tabel user. Peran = kumpulan hak atas
menu. Menu baru yang perlu dibuat di `FoundationSeeder`:

| Menu | Link (= kunci izin) | Dipakai peran |
|---|---|---|
| Proyek NPD | `npd-projects` | PM, semua anggota (view) |
| RFQ & Feasibility | `npd-rfq` | Sales, PM, Engineering |
| Task & Milestone | `npd-tasks` | Semua anggota |
| BOM & Costing NPD | `npd-costing` | Design Eng., Procurement, Costing |
| Trial & Hasil Ukur | `npd-trials` | Process Eng., Quality |
| FMEA | `npd-fmea` | Design Eng., Process Eng. |
| Control Plan | `npd-control-plan` | Process Eng., Quality |
| PPAP | `npd-ppap` | Quality |
| Handover ke Produksi | `npd-handover` | PM + Engineering Manager |

Persetujuan gate memakai inbox approval yang sudah ada (`approvals/pending`), jadi
approver tidak perlu layar terpisah.

> **Aturan yang tidak boleh dilanggar:** setiap `perm:` di rute NPD harus memakai
> salah satu link di atas. Tes `PermissionCoverageTest` sudah menyisir seluruh
> rute dan akan gagal kalau ada kunci izin yang bukan link menu.

---

## 6. Alur stage-gate

```
RFQ / Inquiry  →  Feasibility  ──(No-Go)──►  Proyek ditutup
                      │ (Go)
                      ▼
        Fase 1  Plan & Define        ──[GATE 1]──►
        Fase 2  Product Design       ──[GATE 2]──►
        Fase 3  Process Design       ──[GATE 3]──►
        Fase 4  Validation & PPAP    ──[GATE 4]──►
        Fase 5  Handover & Feedback  ──[TUTUP PROYEK]
```

Gate dijalankan oleh `ApprovalEngine`: `npd_project_phase` didaftarkan sebagai
`doc_type` dengan dua level (Engineering Manager, lalu PPIC/Plant Manager untuk
Gate 3–4). Keputusan approve/reject beserta pemberi, waktu, dan komentar tersimpan
di tabel `approvals` yang sama dengan dokumen lain.

**Gate tidak hanya mengunci fase berikutnya.** Sistem menolak pengajuan gate
selama masih ada deliverable **wajib** fase itu yang belum berstatus selesai.
Daftar deliverable wajib per fase adalah master (`npd_deliverable_std`), bukan
diketik ulang tiap proyek — kalau tidak, "checklist lengkap" hanya berarti
seseorang lupa menambahkan barisnya.

---

## 7. Kebutuhan fungsional

Penomoran mengikuti v1. **Tanda:** ⟳ = berubah karena disesuaikan, ✚ = baru,
✖ = dihapus karena sudah ada di AB-ERP.

### 7.1 RFQ dan Feasibility
- **FR-01** Mencatat RFQ pelanggan: nomor, pelanggan (`m_contacts`), drawing ref,
  qty, target harga, due date. ⟳ pelanggan dari master, bukan tabel sendiri.
- **FR-02** Feasibility study dengan kesimpulan Go / No-Go / Conditional dan
  catatan per aspek (teknis, kapasitas, biaya, ketersediaan material).
- **FR-03** RFQ ber-Go membentuk proyek NPD dengan kode dari `NumberingService`. ⟳

### 7.2 Proyek dan fase
- **FR-04** Proyek memuat kode, pelanggan, nama part, drawing no, tipe proyek
  (baru/modifikasi/derivatif), target SOP, PM, prioritas.
- **FR-05** Lima fase APQP dengan status per fase (planned/in_progress/done).
- **FR-06** Task per fase dengan PIC, rencana vs aktual, progres, dan dependensi.
- **FR-07** Milestone dengan tanggal rencana dan aktual.
- **FR-29** ✚ Proyek modifikasi/derivatif menunjuk `m_item` yang sudah ada
  sebagai induk, supaya perubahannya bisa ditelusuri dari part lama.

### 7.3 Dokumen
- **FR-08** Unggah dokumen yang menempel ke proyek atau entitas turunannya.
- **FR-09** Versi dan revisi; revisi lama tetap tersimpan dan bisa diunduh.
- **FR-10** Setiap deliverable APQP punya status dan penanggung jawab.
- **FR-30** ✚ Ukuran dan tipe berkas dibatasi di server (PDF/gambar/Office,
  maks. 20 MB) — batas yang hanya ada di layar bukan batas.

### 7.4 BOM dan costing
- **FR-11** Preliminary BOM menunjuk `m_item`; part yang belum ada dicatat
  sebagai baris "part baru" dengan spesifikasi sementara. ⟳
- **FR-12** Estimasi biaya: material dari `m_supplier_item`, proses dari
  `cst_rate` × cycle time, ditambah tooling, overhead, dan margin. ⟳
- **FR-13** Costing perlu approval sebelum quotation dikirim.
- **FR-31** ✚ Quotation yang disetujui dapat membentuk baris
  `m_pricelist_det` untuk pelanggan itu, sehingga SO pertama memakai harga yang
  benar-benar disepakati, bukan diketik ulang.

### 7.5 Trial, inspeksi, kualitas
- **FR-14** Trial/prototype: nomor, tipe (prototype/pilot/mass_trial), tanggal,
  mesin (`m_machine`), qty rencana/aktual, OK/NG. ⟳
- **FR-15** Hasil ukur per parameter (`m_inspection_param`) dengan nominal dan
  batas dari `m_item_inspection` bila part-nya sudah terdaftar; judgement
  OK/NG dihitung sistem, bukan diketik. ⟳
- **FR-16** DFMEA/PFMEA dengan **RPN dihitung otomatis** (S × O × D) dan ambang
  tindakan yang dapat diatur (mis. RPN ≥ 100 wajib punya tindakan). ⟳
- **FR-17** Control Plan: proses, karakteristik, metode ukur, sample size,
  frekuensi, reaction plan.
- **FR-32** ✚ Trial tipe `pilot`/`mass_trial` boleh ditautkan ke Work Order
  nyata (`prd_wo_main`), sehingga pemakaian material dan hasil MES-nya tercatat
  di jalur produksi biasa dan tidak perlu dicatat dua kali.

### 7.6 PPAP
- **FR-18** Submission dengan level PPAP 1–5 dan nomor PSW.
- **FR-19** Checklist 18 elemen; daftar elemennya **master ter-seed**, tiap
  elemen punya status dan dokumen pendukung. ⟳
- **FR-20** Submission ditolak sistem bila elemen wajib untuk level itu belum
  lengkap — dengan pesan menyebut elemen mana.
- **FR-21** Status persetujuan pelanggan: submitted/interim/approved/rejected.

### 7.7 Gate dan perubahan
- **FR-22** ✖ *(diganti)* Approval gate memakai `ApprovalEngine`; tidak ada
  tabel approval NPD sendiri.
- **FR-23** Fase berikutnya terkunci sampai gate disetujui **dan** deliverable
  wajib fase itu selesai. ⟳
- **FR-24** ✖ *(diganti)* ECR/ECN memakai modul ECN yang sudah ada
  (`eng_ecn_main`), ditambah kolom `npd_project_id` agar perubahan selama proyek
  tetap tercatat pada proyeknya. ECN kita sudah punya daftar kolom yang boleh
  diubah, pengecekan data berubah sejak disetujui, tanggal efektif, dan kenaikan
  revisi — semuanya tidak perlu dibuat ulang.
- **FR-25** ✖ *(sudah ada)* Jejak audit approval tersimpan di `approvals` +
  `AuditLogger`.

### 7.8 Handover ke produksi ✚ (bagian baru, tidak ada di v1)
- **FR-33** Satu aksi "Serahkan ke Produksi" yang, dalam satu transaksi:
  1. membuat `m_bom` + `m_bom_det_rm`/`m_bom_det_pm` dari preliminary BOM,
  2. membuat `m_process_main` + `m_process_main_det` dari **urutan proses pada
     control plan** — control plan APQP memang disusun per langkah proses, jadi
     itulah process flow yang sudah disepakati; dan mendaftarkannya sebagai
     routing prioritas 1 lewat `m_bom_pro`,
  3. mengisi `m_route_time` dari **cycle time yang dimasukkan process engineer
     pada layar serah terima** (hasil pengamatan trial). ⟳ *Bukan dihitung
     otomatis dari MES: angka yang dipakai perencanaan adalah cycle time yang
     sudah dinilai wajar, bukan rata-rata mentah yang memuat gangguan dan
     penyetelan.*
  4. mengisi `m_item_inspection` dari baris control plan yang ditandai "→ QC",
  5. menaikkan part dari `TRIAL` ke `MASSPRO` dan mengaktifkannya,
  6. menandai proyek `CLOSED` beserta tanggal serah terimanya.

  *(Pembuatan `m_item` sendiri terjadi lebih awal — lihat konsekuensi keputusan
  #3 di §14.)*
- **FR-34** Handover ditolak bila PPAP belum `approved`, atau bila BOM masih
  memuat part baru yang belum punya spesifikasi lengkap.
- **FR-35** Hasil handover masuk sebagai master **DRAFT** dan tetap melewati
  approval master yang sudah berlaku (`m_item`, `m_bom`, `m_process_main`
  semuanya sudah memakai `HasApproval`) — NPD tidak boleh menembus kontrol
  engineering yang sudah ada.

### 7.9 Dashboard dan laporan
- **FR-26** Funnel proyek per fase, proyek terlambat, gate menunggu approval.
- **FR-27** Laporan lead time NPD per proyek dan agregat.
- **FR-28** Notifikasi task jatuh tempo dan gate menunggu — memakai pola
  `sys_alert` + job harian yang sudah ada (`QuotaAlertJob`/`MinStockAlertJob`),
  bukan mekanisme notifikasi baru. ⟳

---

## 8. Integrasi konkret ke modul AB-ERP

| Titik | Arah | Cara |
|---|---|---|
| Master pelanggan | NPD → baca | `m_contacts` kategori Customer |
| Master item | NPD → tulis | Handover membuat `m_item` DRAFT |
| BOM produksi | NPD → tulis | Handover membuat `m_bom` + detail |
| Routing (WOS) | NPD → tulis | Handover membuat `m_process_main` + `m_route_time` |
| Parameter inspeksi | NPD → tulis | Control plan → `m_item_inspection` |
| Pricelist | NPD → tulis | Quotation disetujui → `m_pricelist_det` |
| Supplier & harga | NPD → baca | `m_supplier_item` untuk estimasi material |
| Tarif biaya | NPD → baca | `cst_rate` per proses untuk estimasi proses |
| Procurement | NPD → tulis | PR `pr_type = 'NPD'` untuk material trial |
| Work Order | NPD → tautan | Trial pilot/mass ditautkan ke `prd_wo_main` |
| ECN | dua arah | `eng_ecn_main.npd_project_id` |
| Approval | NPD → pakai | `ApprovalEngine` registry `npd_project_phase` |
| Alert | NPD → tulis | `sys_alert` untuk task telat & gate menunggu |

---

## 9. Model data

Detail lengkap ada di `ERD_Modul_NPD_FTPI.mermaid` (sudah disesuaikan). Ringkasan
13 tabel baru, mengikuti konvensi penamaan AB-ERP (`*_main` / `*_det`):

| Tabel | Isi |
|---|---|
| `npd_project` | Header proyek, pelanggan, part, target SOP, PM, status |
| `npd_rfq` | RFQ/inquiry masuk |
| `npd_feasibility` | Studi kelayakan dan kesimpulannya |
| `npd_phase` | Master 5 fase APQP (ter-seed) |
| `npd_project_phase` | Progres proyek per fase — **entitas yang di-gate** |
| `npd_task` | Task per fase, dengan dependensi |
| `npd_milestone` | Milestone kunci |
| `npd_deliverable_std` | Master deliverable wajib per fase (ter-seed) |
| `npd_deliverable` | Deliverable proyek beserta status & PIC |
| `npd_doc` | Dokumen berversi, menempel polimorfik |
| `npd_bom_main` / `npd_bom_det` | Preliminary BOM (FK ke `m_item`) |
| `npd_cost_main` / `npd_cost_det` | Estimasi biaya & quotation |
| `npd_trial_main` / `npd_trial_det` | Trial dan hasil ukur (FK ke `m_inspection_param`) |
| `npd_fmea_main` / `npd_fmea_det` | DFMEA/PFMEA, RPN otomatis |
| `npd_cp_main` / `npd_cp_det` | Control plan |
| `npd_ppap_main` / `npd_ppap_det` | PPAP submission + 18 elemen |
| `npd_ppap_std` | Master 18 elemen PPAP (ter-seed) |
| `npd_member` | Anggota tim proyek dan perannya |

Tabel yang **tidak** dibuat karena sudah ada: `npd_approvals` → `approvals`;
`npd_change_requests` → `eng_ecn_main`; `customers` → `m_contacts`.

---

## 10. Kebutuhan non-fungsional (disesuaikan)

- **NFR-01 Teknologi:** Laravel 13 REST + React SPA + MySQL + Tailwind — mengikuti
  AB-ERP, **bukan** Livewire seperti v1.
- **NFR-02 Autentikasi:** Sanctum. Integrasi Active Directory masuk backlog
  bersama 2FA (lihat GAP-ANALYSIS prioritas 4).
- **NFR-03 Otorisasi:** menu + hak per aksi; kunci izin = link menu.
- **NFR-04 Jejak audit:** `AuditLogger` untuk aksi, `approvals` untuk keputusan.
- **NFR-05 Bahasa:** label Indonesia. i18n ID/EN/JP belum tersedia di AB-ERP dan
  menjadi pekerjaan lintas modul, bukan milik NPD.
- **NFR-06 Berkas:** Laravel storage disk (default lokal). Bila nanti pindah ke
  SharePoint/M365, cukup ganti disk — path di `npd_doc` tidak berubah bentuknya.
- **NFR-07 Kinerja:** daftar proyek dan dashboard < 2 detik pada skala FTPI.
- **NFR-08 Backup:** mengikuti kebijakan server FTPI.
- **NFR-09** ✚ **Periode akuntansi:** aksi NPD yang menyentuh buku besar (bila
  nanti tooling dikapitalisasi) harus melewati middleware `period.open` seperti
  modul lain.

---

## 11. Metrik keberhasilan

- Lead time NPD rata-rata (RFQ → PPAP approved).
- Persentase proyek tepat waktu terhadap target SOP.
- Jumlah gate reject beserta alasannya.
- Kelengkapan elemen PPAP saat submission.
- ✚ **Persentase part baru yang masuk `m_item` lewat handover NPD**, bukan
  diketik langsung di master item — ini ukuran apakah modulnya benar-benar
  dipakai, bukan sekadar diisi setelah kejadian.

---

## 12. Tahapan implementasi (disesuaikan)

| Tahap | Isi | Perkiraan |
|---|---|---|
| **1 — MVP** | `npd_project`, RFQ, feasibility, 5 fase, task, milestone, tim, dokumen, gate lewat `ApprovalEngine`, dashboard | 3–4 hari |
| **2** | Preliminary BOM (FK `m_item`), costing dari `cst_rate` + `m_supplier_item`, quotation → pricelist | 2–3 hari |
| **3** | Trial + hasil ukur (FK `m_inspection_param`), tautan ke `prd_wo_main` | 2 hari |
| **4** | DFMEA/PFMEA dengan RPN, control plan | 2 hari |
| **5** | PPAP 18 elemen + blokir submission tidak lengkap | 2 hari |
| **6 — Handover** | Membuat `m_item` + `m_bom` + routing + `m_route_time` + `m_item_inspection` dalam satu transaksi | 2 hari |
| **7** | Alert task telat & gate menunggu, laporan lead time | 1 hari |

Tahap 1 dan 6 adalah yang paling menentukan: yang pertama membuat proyek terlihat,
yang terakhir membuat hasilnya terpakai. Tahap 2–5 bisa dikerjakan berurutan
tanpa menghalangi pemakaian tahap 1.

---

## 13. Asumsi dan batasan

- Alur gate mengikuti pola APQP otomotif. Untuk pelanggan non-otomotif, jumlah
  gate dapat disederhanakan per tipe proyek (konfigurasi di `npd_phase`).
- Input MSA/SPC manual pada fase awal; MES sudah mencatat aktual produksi dan
  dapat disambungkan menyusul.
- Angka sasaran pada v1 (turun 15 persen, dll.) adalah placeholder dan perlu
  disepakati manajemen — dokumen ini tidak mengubah statusnya.
- Modul ini menambah beban master data: part baru yang lahir dari NPD tetap harus
  melewati approval `m_item`, jadi Engineering Manager akan menerima lebih banyak
  pengajuan approval, bukan lebih sedikit.

---

## 14. Keputusan yang sudah diambil (4 Agustus 2026)

| # | Pertanyaan | Keputusan | Dampak ke rancangan |
|---|---|---|---|
| 1 | Level approval gate | **Dua level untuk semua gate** | Registry `ApprovalEngine`: level 1 `npd-projects` (PM/Engineering), level 2 `npd-handover` (SPV). Berlaku sama untuk Gate 1–4. |
| 2 | Quotation → pricelist | **Hanya quotation berstatus APPROVED** yang boleh dijadikan pricelist | `npd_cost_main.status` harus `APPROVED` sebelum tombol "Jadikan Pricelist" aktif; hasilnya tetap DRAFT dan lewat approval pricelist yang sudah ada. |
| 3 | Trial dan Work Order | **Setiap trial wajib punya WO khusus** | `npd_trial_main.wo_id` **NOT NULL**. WO trial ditandai `prd_wo_main.wo_kind = 'NPD_TRIAL'` supaya tidak terhitung sebagai pasokan di MRP dan tidak tercampur WO produksi. |
| 4 | Tooling | **Biaya proyek**, bukan aset tetap | `npd_cost_det.cost_type = 'TOOLING'`; tidak ada tautan ke `ast_main` dan tidak ada kapitalisasi. |
| 5 | Yang menyetujui Handover | **SPV** | Menu `npd-handover` dipegang SPV ke atas. PM mengajukan, SPV menyetujui — sekaligus menjadi level 2 seluruh gate. |

### Konsekuensi keputusan #3 yang perlu diketahui

WO membutuhkan `fg_id` yang menunjuk `m_item`. Untuk part yang benar-benar baru,
item itu belum ada sampai handover — jadi urutannya tidak bisa "trial dulu, item
belakangan".

Karena itu rancangan diubah sedikit: part **didaftarkan lebih awal** sebagai
`m_item` non-aktif (aksi "Daftarkan Part" pada fase 2), dan **handover
melengkapi lalu mengaktifkannya** — bukan membuatnya dari nol. Nomor part memang
sudah diketahui sejak drawing pelanggan diterima, jadi ini juga lebih dekat
dengan kenyataan di lapangan.

FR-33 diubah mengikuti ini: handover membuat BOM, routing, cycle time, dan
parameter inspeksi; item-nya diaktifkan, tidak dibuat.
