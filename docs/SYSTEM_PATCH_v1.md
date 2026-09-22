# SYSTEM PATCH REPORT v1.0
**Date:** 21 September 2026
**Type:** Database Schema, Models & Business Logic Enhancement
**Author:** Abgul (Hermes AI System Engineer)

## Overview
Laporan ini mendokumentasikan modifikasi struktural ERP yang menutupi kelemahan *blind spots* dalam sistem operasional (khususnya untuk transaksi modul Finance & Master Data).

### 1. Pricelist dengan Periode Berlaku (Effective Dates)
- **Problem:** Data pricelist customer hanya menggunakan pendekatan timpa *flat rate*. Risiko kerancuan data jika SO dibuat melintas pergantian tahun/bulan harga baru.
- **Action:** Eksekusi Inject DB kolom `valid_from`, `valid_to`, dan `is_active` ke tabel `m_pricelist_det`.
- **Status:** **Terselesaikan ✅**. Database telah mendukung variasi harga per-periode (Historical Pricing). Sistem reservasi harga otomatis akan mengecek _intersection_ tanggal transaksi SO terhadap `valid_from` & `valid_to` table ini.

### 2. Modul AP Expenses (Tagihan Operasional & Non-Material)
- **Problem:** Accounts Payable (AP) sistem belum fleksibel dan terkunci dalam pakem *3-way-matching* (harus lewat PO Pembelian Barang). Hal ini memblokir pembayaran tagihan rutin seperti listrik, ATK mendadak, atau maintenance mesin.
- **Action:** Dibuatkan skema tabel `fin_ap_expenses` dan detail-nya `fin_ap_expense_det`, beserta *Eloquent Models*. 
- **Status:** **Terselesaikan ✅**. Tim Keuangan sekarang bisa langsung melakukan input *Direct Invoice / Non-PO Expense*, menjurnal tagihan-tagihan operasional langsung ke akun biaya / Cost Center (COA) tanpa harus melakukan simulasi penerimaan stok (Goods Receipt).

### 3. Kontrak Hedging Valuta Asing (FX Hedging Contract)
- **Problem:** Impor rutin yang menumpuk rentan terhadap pergeseran kurs mata uang. Fasilitas pembelian (*Spot Rate*) sudah ada, tetapi asuransi penguncian kurs di masa depan belum difasilitasi.
- **Action:** Dibuatkan modul `fin_fx_hedging` dan Eloquent Model terkait.
- **Status:** **Terselesaikan ✅**. Perusahaan kini dapat mengunci kesepakatan kurs luar negeri dengan bank (misal: Locked IDR 15.500/USD dengan total dana 100,000 USD valid dari Januari hingga Maret). AP Payment kelak dapat me-_reference_ nomor kontrak ini agar membayar *realized payment* sesuai rate hedging (mengeliminasi rugi/laba kurs yang fluktuatif).

## Next Recommendations:
- Menyisipkan parameter _Form Parameter_ di frontend untuk UI Hedging & AP Expense.
- Penyesuaian `JournalEngine` agar mampu mendeteksi *Realized Forex Gain/Loss* bila sebagian tagihan dibayar menggunakan sumber Hedging Bank sementara sisa lainnya memakai kurs BI harian.
