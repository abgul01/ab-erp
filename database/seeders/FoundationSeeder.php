<?php

namespace Database\Seeders;

use App\Models\menus;
use App\Models\status_id;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Everything the application needs before any business data exists: who can log
 * in, what they see, the chart of accounts, and which periods are open.
 *
 * Idempotent throughout — this runs on an existing database as often as needed
 * without duplicating a menu or resetting a password.
 */
class FoundationSeeder extends Seeder
{
    private const MENU_TREE = [
        ['Administrator', 'shield', [
            ['Approval Persetujuan', 'approvals', 'check'],
            ['Manajemen User', 'users', 'users'],
            ['Manajemen Menu', 'menus', 'layers'],
        ]],
        ['General Data Master', 'database', [
            ['Item Category', 'categories', 'tag'],
            ['Product Family', 'product-families', 'layers'],
            ['Production Line', 'production-lines', 'factory'],
            ['Unit of Measure', 'uoms', 'ruler'],
            ['Currency', 'currencies', 'coins'],
            ['Kurs Pajak (KMK)', 'exchange-rates', 'coins'],
            ['Tax Code', 'taxes', 'percent'],
            ['Maker', 'makers', 'factory'],
            ['Machine', 'machines', 'cog'],
            ['Kalender Kerja', 'work-calendar', 'calendar'],
            ['Contact Category', 'contact-categories', 'tags'],
            ['Contacts', 'contacts', 'users'],
            ['Process', 'processes', 'workflow'],
            ['Parameter Inspeksi', 'inspection-params', 'ruler'],
            ['Kode Defect', 'defectives', 'alert-triangle'],
        ]],
        ['Engineering', 'wrench', [
            ['Item Master', 'items', 'box'],
            ['Master Routing', 'process-mains', 'workflow'],
            ['Cycle Time (Routing)', 'route-times', 'cog'],
            ['Alat Bantu BOM', 'bom-tools', 'layers'],
            ['ECN (Perubahan Teknik)', 'ecn', 'file-text'],
        ]],
        ['NPD — Produk Baru', 'box', [
            ['RFQ & Feasibility', 'npd-rfq', 'clipboard-list'],
            ['Proyek NPD', 'npd-projects', 'workflow'],
            ['Task & Milestone', 'npd-tasks', 'calendar'],
            ['BOM & Costing NPD', 'npd-costing', 'calculator'],
            ['Trial & Hasil Ukur', 'npd-trials', 'ruler'],
            ['FMEA (Analisis Risiko)', 'npd-fmea', 'alert-triangle'],
            ['Control Plan', 'npd-control-plan', 'clipboard-list'],
            ['PPAP Submission', 'npd-ppap', 'package-check'],
            ['Laporan NPD', 'npd-reports', 'trending-up'],
            // Menu ini adalah kewenangan SPV: level 2 seluruh gate, dan nanti
            // tombol serah terima ke produksi.
            ['Serah Terima Produksi', 'npd-handover', 'check-circle'],
        ]],
        ['Procurement', 'shopping-cart', [
            ['Purchase Requisition', 'pr', 'clipboard-list'],
            ['Purchase Order', 'po', 'file-text'],
            ['Goods Receipt', 'grn', 'package-check'],
            ['QAS / Inspeksi', 'qas', 'check'],
            ['Import Quota', 'quotas', 'scale'],
            ['Landed Cost', 'landed-costs', 'calculator'],
            ['GR Reject', 'gr-rejects', 'undo'],
            ['AP Invoice', 'ap-invoices', 'receipt'],
            ['Penawaran Vendor', 'quotations', 'file-text'],
            ['Kontrak Vendor', 'contracts', 'scale'],
            ['Supplier Item & Price', 'supplier-items', 'coins'],
            ['Peringatan Operasional', 'alerts', 'bell'],
            ['Master Subcont', 'subcont-items', 'layers'],
            ['PO Subcont', 'subcont-po', 'file-text'],
            ['Subcont — Kirim', 'subcont-dn', 'send'],
            ['Subcont — Terima', 'subcont-gr', 'package-check'],
        ]],
        ['WMS Raw Material', 'warehouse', [
            ['Master Rak', 'racks', 'rows'],
            ['Incoming RM', 'incoming-rm', 'package-check'],
            ['Putaway RM', 'putaway', 'boxes'],
            ['Outgoing RM', 'outgoing-rm', 'send'],
            ['Remaining / Tankan', 'remaining-rm', 'undo'],
            ['Scrap RM', 'scrap-rm', 'ban'],
            ['Opname & Adjustment', 'stock-adjustments', 'scale'],
            ['Stok RM', 'stock-rm', 'boxes'],
        ]],
        ['WMS Finished Goods', 'boxes', [
            ['Incoming FG', 'incoming-fg', 'package-check'],
            ['Outgoing FG', 'outgoing-fg', 'send'],
            ['Stok FG', 'stock-fg', 'boxes'],
            ['Transfer FG', 'fg-transfer', 'undo'],
            ['Downgrade FG', 'fg-downgrade', 'arrow-down'],
        ]],
        ['WHS Tools & Sparepart', 'wrench', [
            ['Master Barang WHS', 'whs-items', 'box'],
            ['PO WHS', 'whs-po', 'file-text'],
            ['Penerimaan WHS', 'whs-incoming', 'package-check'],
            ['Pengeluaran WHS', 'whs-outgoing', 'send'],
            ['Pengembalian Alat', 'whs-returns', 'undo'],
            ['Stok WHS', 'whs-stock', 'boxes'],
        ]],
        ['Order Management', 'tags', [
            ['Forecast', 'forecasts', 'calendar'],
            ['Analisis Forecast', 'forecast-analysis', 'trending-up'],
            ['Pricelist Customer', 'pricelists', 'coins'],
            ['Sales Order', 'sales-orders', 'file-text'],
            ['Delivery Order', 'delivery-orders', 'send'],
            ['Packing List', 'packing-lists', 'package-open'],
            ['Shipping Order', 'shipping-orders', 'truck'],
            ['Sales Invoice', 'sales-invoices', 'receipt'],
            ['Sales Return', 'sales-returns', 'undo'],
        ]],
        ['Planning Control', 'calendar', [
            ['MPP (Rencana Bulanan)', 'mpp', 'calendar'],
            ['MRP (Kebutuhan Material)', 'mrp', 'calculator'],
            ['MPS (Jadwal Produksi)', 'mps', 'workflow'],
            ['CRP (Kapasitas)', 'crp', 'calculator'],
            ['Persetujuan Jadwal MPS', 'mps-approvals', 'check'],
        ]],
        ['Manufacturing', 'factory', [
            ['Work Order', 'work-orders', 'clipboard-list'],
            ['Kanban / Issue RM', 'kanbans', 'send'],
            ['MES — Cutting', 'mes-cutting', 'scissors'],
            ['MES — Processing', 'mes-processing', 'cog'],
            ['Final Check Sheet', 'fcs', 'check-circle'],
            ['MES — Keputusan Abnormal', 'mes-abnormal', 'ban'],
            ['MES — Aktual vs Rencana', 'mes-report', 'calculator'],
        ]],
        ['Costing & Asset', 'calculator', [
            ['Tarif Biaya', 'cost-rates', 'percent'],
            ['COGM (Biaya Produksi)', 'cogm', 'calculator'],
            ['Valuasi & Margin', 'inventory-valuation', 'scale'],
            ['Kategori Aset', 'asset-categs', 'layers'],
            ['Aset & Depresiasi', 'assets', 'scale'],
        ]],
        ['Accounting', 'database', [
            ['Chart of Accounts', 'coa', 'file-text'],
            ['Periode Akuntansi', 'acc-periods', 'calendar'],
            ['Jurnal & Buku Besar', 'journals', 'database'],
            ['Laporan Keuangan', 'fin-reports', 'file-text'],
            ['Pembayaran AP', 'ap-payments', 'receipt'],
            ['Penerimaan AR', 'ar-receipts', 'coins'],
            ['Ekspor Pajak', 'tax-export', 'receipt'],
        ]],
    ];

    private const COA = [
        ['1100', 'Kas & Bank', 'ASSET'],
        ['1200', 'Piutang Usaha', 'ASSET'],
        ['1210', 'PPN Masukan', 'ASSET'],
        ['1220', 'PPh 22 Dibayar Dimuka', 'ASSET'],
        ['1300', 'Persediaan Bahan Baku', 'ASSET'],
        ['1310', 'Persediaan Barang Dalam Proses', 'ASSET'],
        ['1320', 'Persediaan Barang Jadi', 'ASSET'],
        ['1340', 'Persediaan WHS (Sparepart, Consumable & Tools)', 'ASSET'],
        ['1500', 'Aset Tetap', 'ASSET'],
        ['1590', 'Akumulasi Penyusutan', 'ASSET'],
        ['2100', 'Hutang Usaha', 'LIABILITY'],
        ['2210', 'PPN Keluaran', 'LIABILITY'],
        ['2220', 'Hutang PPh 23', 'LIABILITY'],
        ['3100', 'Modal Disetor', 'EQUITY'],
        ['3200', 'Laba Ditahan', 'EQUITY'],
        ['4100', 'Penjualan', 'REVENUE'],
        ['4900', 'Laba Pelepasan Aset', 'REVENUE'],
        ['5100', 'Harga Pokok Penjualan', 'COGS'],
        ['5200', 'Biaya Produksi — Material', 'COGS'],
        ['5300', 'Biaya Produksi — Tenaga Kerja', 'COGS'],
        ['5400', 'Biaya Produksi — Overhead', 'COGS'],
        ['6100', 'Beban Penyusutan', 'EXPENSE'],
        ['6200', 'Beban Gaji', 'EXPENSE'],
        ['6300', 'Beban Umum & Administrasi', 'EXPENSE'],
        // Sparepart & barang habis pakai dibebankan saat keluar gudang; alat
        // baru dibebankan kalau rusak atau hilang, jadi keduanya dipisah supaya
        // pemborosan pemakaian tidak tersamar oleh kehilangan alat.
        ['6400', 'Beban Perlengkapan & Sparepart', 'EXPENSE'],
        ['6410', 'Beban Kerugian Alat (rusak/hilang)', 'EXPENSE'],
        ['6900', 'Selisih Persediaan', 'EXPENSE'],
        ['6910', 'Rugi Pelepasan Aset', 'EXPENSE'],
    ];

    public function run(): void
    {
        $this->seedStatuses();
        [$admin, $operator, $vendorUser] = $this->seedUsers();
        $menuLinks = $this->seedMenus();
        $this->seedPermissions($operator, $menuLinks);
        $this->seedCoa();
        $this->seedPeriods();

        $this->command?->info('  Foundation: '.count($menuLinks).' menu, '.count(self::COA).' akun COA.');
    }

    private function seedStatuses(): void
    {
        foreach (['ADMIN', 'ACTIVE', 'INACTIVE'] as $s) {
            status_id::firstOrCreate(['status' => $s]);
        }
    }

    private function seedUsers(): array
    {
        $adminStatus = status_id::where('status', 'ADMIN')->value('id');
        $activeStatus = status_id::where('status', 'ACTIVE')->value('id');

        $admin = User::updateOrCreate(['username' => 'admin'], [
            'name' => 'Administrator', 'email' => 'admin@ab-erp.local',
            'identity' => 'ADM-0001', 'password' => Hash::make('password'),
            'status_id' => $adminStatus,
        ]);

        $operator = User::updateOrCreate(['username' => 'operator'], [
            'name' => 'Budi Santoso (PPIC)', 'email' => 'operator@ab-erp.local',
            'identity' => 'OPR-0001', 'password' => Hash::make('password'),
            'status_id' => $activeStatus,
        ]);

        $vendor = User::updateOrCreate(['username' => 'vendor'], [
            'name' => 'PT Steel Supply Indonesia', 'email' => 'vendor@ssi.co.id',
            'identity' => 'VEN-0001', 'password' => Hash::make('password'),
            'status_id' => $activeStatus,
        ]);

        return [$admin, $operator, $vendor];
    }

    private function seedMenus(): array
    {
        $links = [];

        foreach (self::MENU_TREE as $rootSort => [$name, $icon, $children]) {
            $parent = menus::firstOrCreate(['name' => $name, 'parent_id' => 0], ['link' => '0', 'icon' => $icon]);
            $parent->update(['icon' => $icon, 'sort' => $rootSort]);

            foreach ($children as $childSort => [$cName, $cLink, $cIcon]) {
                menus::firstOrNew(['link' => $cLink])
                    ->fill(['name' => $cName, 'parent_id' => $parent->id, 'icon' => $cIcon, 'sort' => $childSort])
                    ->save();

                $links[$cLink] = menus::where('link', $cLink)->value('id');
            }
        }

        $this->pruneMenus(array_keys($links));

        return $links;
    }

    /**
     * Buang menu yang tidak lagi ada di pohon di atas.
     *
     * Seeder ini hanya pernah menambah dan memperbarui, tidak pernah menghapus —
     * jadi modul yang dibuang dari kode meninggalkan baris menunya di database.
     * Itu yang terjadi pada General Store setelah digantikan WHS Tools: kodenya
     * hilang, menunya tetap ada, dan pengguna yang menekannya sampai di halaman
     * yang tidak ada.
     *
     * Yang dibuang hanya menu berlink (anak). Induk tanpa anak ikut dibersihkan
     * supaya tidak menyisakan judul kosong di sidebar.
     *
     * @param  array<int, string>  $keep  link yang masih dideklarasikan
     */
    private function pruneMenus(array $keep): void
    {
        $stale = menus::where('link', '<>', '0')
            ->whereNotNull('link')
            ->whereNotIn('link', $keep)
            ->get();

        if ($stale->isNotEmpty()) {
            // Hak akses ikut dibuang: baris yang menunjuk menu yang tak ada
            // hanya akan membingungkan layar Manajemen User.
            DB::table('user_menu_permissions')->whereIn('menu_id', $stale->pluck('id'))->delete();
            menus::whereIn('id', $stale->pluck('id'))->delete();

            $this->command?->warn('  Menu usang dibuang: '.$stale->pluck('link')->implode(', '));
        }

        $emptyParents = menus::where('link', '0')
            ->whereNotIn('id', menus::where('parent_id', '<>', 0)->select('parent_id'))
            ->get();

        if ($emptyParents->isNotEmpty()) {
            menus::whereIn('id', $emptyParents->pluck('id'))->delete();
            $this->command?->warn('  Induk menu tanpa anak dibuang: '.$emptyParents->pluck('name')->implode(', '));
        }
    }

    private function seedPermissions(User $operator, array $menuLinks): void
    {
        $readOnly = ['items', 'process-mains', 'bom-tools', 'coa', 'acc-periods', 'mps-approvals', 'fin-reports', 'tax-export'];
        $noAccess = ['approvals', 'users', 'menus'];

        foreach ($menuLinks as $link => $menuId) {
            $view = ! in_array($link, $noAccess, true);
            $write = $view && ! in_array($link, $readOnly, true);

            DB::table('user_menu_permissions')->updateOrInsert(
                ['user_id' => $operator->id, 'menu_id' => $menuId],
                [
                    'can_view' => $view ? 1 : 0,
                    'can_create' => $write ? 1 : 0,
                    'can_edit' => $write ? 1 : 0,
                    'can_delete' => 0,
                    'can_download' => $view ? 1 : 0,
                    'can_import' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]
            );
        }
    }

    private function seedCoa(): void
    {
        foreach (self::COA as [$code, $name, $group]) {
            DB::table('acc_coa')->updateOrInsert(
                ['code' => $code],
                ['name' => $name, 'acc_group' => $group, 'postable' => 1]
            );
        }
    }

    private function seedPeriods(): void
    {
        $seeded = DemoCalendar::month();

        for ($i = -6; $i <= 3; $i++) {
            $p = $seeded->copy()->addMonths($i);
            $period = $p->format('Ym');

            DB::table('acc_period')->updateOrInsert(
                ['period' => $period],
                ['status' => $p->lt($seeded->copy()->startOfMonth()) ? 'CLOSED' : 'OPEN']
            );
        }
    }
}
