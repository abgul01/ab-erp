<?php

namespace Database\Seeders;

use App\Models\menus;
use App\Models\status_id;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Idempotent foundation seed: statuses, admin + demo operator,
     * dynamic menu tree, per-user permissions, and sample master data.
     */
    public function run(): void
    {
        $this->seedStatuses();
        [$admin, $operator] = $this->seedUsers();
        $menus = $this->seedMenus();
        $this->seedPermissions($operator, $menus);
        $this->seedMasterData();
        $this->call(DemoDataSeeder::class);
    }

    private function seedStatuses(): void
    {
        foreach (['ADMIN', 'ACTIVE', 'INACTIVE'] as $s) {
            status_id::firstOrCreate(['status' => $s]);
        }
    }

    /** @return array{0: User, 1: User} */
    private function seedUsers(): array
    {
        $adminStatus = status_id::where('status', 'ADMIN')->value('id');
        $activeStatus = status_id::where('status', 'ACTIVE')->value('id');

        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator',
                'email' => 'admin@ab-erp.local',
                'identity' => 'ADM-0001',
                'password' => Hash::make('password'),
                'status_id' => $adminStatus,
            ]
        );

        $operator = User::updateOrCreate(
            ['username' => 'operator'],
            [
                'name' => 'Operator Master Data',
                'email' => 'operator@ab-erp.local',
                'identity' => 'OPR-0001',
                'password' => Hash::make('password'),
                'status_id' => $activeStatus,
            ]
        );

        return [$admin, $operator];
    }

    /**
     * Seed the hierarchical menu. Returns a flat map link => menu id
     * for leaf menus (those with a real route link).
     *
     * @return array<string, int>
     */
    private function seedMenus(): array
    {
        $tree = [
            ['Administrator', '0', 'shield', []],
            ['General Data Master', '0', 'database', [
                ['Item Category', 'categories', 'tag'],
                ['Unit of Measure', 'uoms', 'ruler'],
                ['Currency', 'currencies', 'coins'],
                ['Tax Code', 'taxes', 'percent'],
                ['Maker', 'makers', 'factory'],
                ['Machine', 'machines', 'cog'],
                ['Contact Category', 'contact-categories', 'tags'],
                ['Contacts', 'contacts', 'users'],
                ['Process', 'processes', 'workflow'],
            ]],
            ['Engineering', '0', 'wrench', [
                ['Item Master', 'items', 'box'],
                ['Master Routing', 'process-mains', 'workflow'],
                ['Cycle Time (Routing)', 'route-times', 'cog'],
            ]],
            ['Procurement', '0', 'shopping-cart', [
                ['Purchase Requisition', 'pr', 'clipboard-list'],
                ['Purchase Order', 'po', 'file-text'],
                ['Goods Receipt', 'grn', 'package-check'],
                ['Import Quota', 'quotas', 'scale'],
                ['Landed Cost', 'landed-costs', 'calculator'],
                ['GR Reject', 'gr-rejects', 'undo'],
                ['AP Invoice', 'ap-invoices', 'receipt'],
                ['Subcont — Kirim', 'subcont-dn', 'send'],
                ['Subcont — Terima', 'subcont-gr', 'package-check'],
            ]],
            ['WMS Raw Material', '0', 'warehouse', [
                ['Master Rak', 'racks', 'rows'],
                ['Incoming RM', 'incoming-rm', 'package-check'],
                ['Outgoing RM', 'outgoing-rm', 'send'],
                ['Remaining / Tankan', 'remaining-rm', 'undo'],
                ['Stok RM', 'stock-rm', 'boxes'],
            ]],
            ['WMS Finished Goods', '0', 'boxes', [
                ['Incoming FG', 'incoming-fg', 'package-check'],
                ['Outgoing FG', 'outgoing-fg', 'send'],
                ['Stok FG', 'stock-fg', 'boxes'],
            ]],
            ['Order Management', '0', 'tags', [
                ['Forecast', 'forecasts', 'calendar'],
                ['Pricelist Customer', 'pricelists', 'coins'],
                ['Sales Order', 'sales-orders', 'file-text'],
                ['Delivery Order', 'delivery-orders', 'send'],
                ['Sales Invoice', 'sales-invoices', 'receipt'],
                ['Sales Return', 'sales-returns', 'undo'],
            ]],
            // planning comes before the floor that executes it
            ['Planning Control', '0', 'calendar', [
                ['MPP (Rencana Bulanan)', 'mpp', 'calendar'],
                ['MRP (Kebutuhan Material)', 'mrp', 'calculator'],
                ['MPS (Jadwal Produksi)', 'mps', 'workflow'],
                ['Persetujuan Jadwal MPS', 'mps-approvals', 'check'],
            ]],
            ['Costing & Asset', '0', 'calculator', [
                ['Tarif Biaya', 'cost-rates', 'percent'],
                ['COGM (Biaya Produksi)', 'cogm', 'calculator'],
                ['Kategori Aset', 'asset-categs', 'layers'],
                ['Aset & Depresiasi', 'assets', 'scale'],
            ]],
            ['Accounting', '0', 'database', [
                ['Chart of Accounts', 'coa', 'file-text'],
                ['Periode Akuntansi', 'acc-periods', 'calendar'],
                ['Jurnal & Buku Besar', 'journals', 'database'],
                ['Pembayaran AP', 'ap-payments', 'receipt'],
                ['Penerimaan AR', 'ar-receipts', 'coins'],
            ]],
            ['Manufacturing', '0', 'factory', [
                ['Work Order', 'work-orders', 'clipboard-list'],
                ['MES — Cutting', 'mes-cutting', 'workflow'],
                ['MES — Processing', 'mes-processing', 'cog'],
                ['MES — Keputusan Abnormal', 'mes-abnormal', 'ban'],
                ['MES — Aktual vs Rencana', 'mes-report', 'calculator'],
            ]],
        ];

        $links = [];
        foreach ($tree as $rootSort => [$name, $link, $icon, $children]) {
            $parent = menus::firstOrCreate(['name' => $name, 'parent_id' => 0], ['link' => $link, 'icon' => $icon]);
            $parent->update(['icon' => $icon, 'sort' => $rootSort]);

            foreach ($children as $childSort => [$cName, $cLink, $cIcon]) {
                // Match on the LINK, not (name, parent): that way a menu can be
                // renamed or moved to another group without the seeder creating
                // a duplicate and orphaning the old row (with its permissions).
                menus::firstOrNew(['link' => $cLink])
                    ->fill(['name' => $cName, 'parent_id' => $parent->id, 'icon' => $cIcon, 'sort' => $childSort])
                    ->save();
                $links[$cLink] = menus::where('link', $cLink)->value('id');
            }
        }

        return $links;
    }

    /**
     * Give the demo operator view+create+edit on master data, view-only on engineering,
     * to demonstrate the RBAC/menu filtering. (admin bypasses via super-admin.)
     *
     * @param  array<string, int>  $menuLinks
     */
    private function seedPermissions(User $operator, array $menuLinks): void
    {
        $full = ['categories', 'uoms', 'currencies', 'taxes', 'makers', 'machines', 'contact-categories', 'contacts', 'processes', 'process-mains',
            'pr', 'po', 'grn', 'quotas', 'landed-costs', 'gr-rejects', 'ap-invoices', 'subcont-dn', 'subcont-gr',
            'racks', 'incoming-rm', 'outgoing-rm', 'remaining-rm', 'stock-rm', 'mpp', 'mrp', 'mps', 'work-orders',
            'incoming-fg', 'outgoing-fg', 'stock-fg', 'delivery-orders', 'sales-invoices',
            'forecasts', 'sales-orders', 'pricelists', 'sales-returns', 'route-times', 'mes-cutting', 'mes-processing', 'mes-abnormal', 'mes-report',
            'cost-rates', 'cogm', 'asset-categs', 'assets',
            'coa', 'acc-periods', 'journals', 'ap-payments', 'ar-receipts'];
        // operator can request reschedules (mps edit) & watch approvals, but not approve them
        $viewOnly = ['items', 'mps-approvals'];

        foreach ($menuLinks as $link => $menuId) {
            $isFull = in_array($link, $full, true);
            $isView = $isFull || in_array($link, $viewOnly, true);

            DB::table('user_menu_permissions')->updateOrInsert(
                ['user_id' => $operator->id, 'menu_id' => $menuId],
                [
                    'can_view' => $isView ? 1 : 0,
                    'can_create' => $isFull ? 1 : 0,
                    'can_edit' => $isFull ? 1 : 0,
                    'can_delete' => 0,
                    'can_download' => 0,
                    'can_import' => 0,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function seedMasterData(): void
    {
        // Item group (m_i_category is used as the item Group: Material / FG only)
        foreach (['Material', 'FG'] as $c) {
            DB::table('m_i_category')->updateOrInsert(['name_c' => $c], ['updated_at' => now(), 'created_at' => now()]);
        }

        // UoM
        $uoms = [
            ['PCS', 'Pieces', 'COUNT'],
            ['KG', 'Kilogram', 'WEIGHT'],
            ['MM', 'Milimeter', 'LENGTH'],
            ['BATANG', 'Batang / Bar', 'COUNT'],
            ['BOX', 'Box / Lot', 'COUNT'],
        ];
        foreach ($uoms as [$code, $name, $type]) {
            DB::table('m_uom')->updateOrInsert(['code' => $code], [
                'name' => $name, 'uom_type' => $type, 'active' => 1, 'updated_at' => now(), 'created_at' => now(),
            ]);
        }

        // Currency
        DB::table('m_currency')->updateOrInsert(['code' => 'IDR'], ['name' => 'Rupiah', 'is_base' => 1]);
        DB::table('m_currency')->updateOrInsert(['code' => 'USD'], ['name' => 'US Dollar', 'is_base' => 0]);
        DB::table('m_currency')->updateOrInsert(['code' => 'JPY'], ['name' => 'Japanese Yen', 'is_base' => 0]);

        // Tax codes (PMK 131/2024): non-luxury DPP nilai lain 11/12, rate 12%
        DB::table('m_tax')->updateOrInsert(['code' => 'PPN-DN'], [
            'name' => 'PPN Dalam Negeri (Non-Mewah)', 'rate_pct' => 12.0000,
            'dpp_factor' => 0.916667, 'is_luxury' => 0, 'effective_from' => '2025-01-01',
        ]);
        // PPh tariffs — starting values, adjust in the Tax Code master as needed
        DB::table('m_tax')->updateOrInsert(['code' => 'PPH-22'], [
            'name' => 'PPh Pasal 22 (industri baja)', 'rate_pct' => 0.3000,
            'dpp_factor' => 1.000000, 'is_luxury' => 0, 'effective_from' => '2025-01-01',
        ]);
        DB::table('m_tax')->updateOrInsert(['code' => 'PPH-23'], [
            'name' => 'PPh Pasal 23 (jasa)', 'rate_pct' => 2.0000,
            'dpp_factor' => 1.000000, 'is_luxury' => 0, 'effective_from' => '2025-01-01',
        ]);
        DB::table('m_tax')->updateOrInsert(['code' => 'PPN-LX'], [
            'name' => 'PPN Barang Mewah', 'rate_pct' => 12.0000,
            'dpp_factor' => 1.000000, 'is_luxury' => 1, 'effective_from' => '2025-01-01',
        ]);

        // Makers
        foreach (['Nippon Steel', 'JFE Steel', 'Krakatau Steel'] as $m) {
            DB::table('m_maker_m')->updateOrInsert(['name' => $m], ['active' => 1, 'updated_at' => now(), 'created_at' => now()]);
        }

        // Processes (first process for RM pipe = Cutting)
        $processes = [
            ['CUT', 'Cutting', 'Pemotongan pipa (proses pertama)'],
            ['MCH', 'Machining', 'Pemesinan / turning'],
            ['CHM', 'Chamfering', 'Chamfer ujung'],
            ['DRL', 'Drilling', 'Pengeboran'],
            ['WLD', 'Welding', 'Pengelasan'],
            ['PLT', 'Plating', 'Pelapisan (subcont)'],
            // terminal marker: every routing ends here → goods enter the FG warehouse
            ['FG', 'Finished Goods', 'Masuk gudang barang jadi (langkah akhir routing)'],
        ];
        foreach ($processes as [$code, $name, $desc]) {
            DB::table('m_process')->updateOrInsert(['code' => $code], [
                'name_p' => $name, 'descript' => $desc, 'active' => 1, 'created_at' => now(), 'updated' => now(),
            ]);
        }

        // Contact categories + sample customer & vendor
        $catId = DB::table('m_cont_categ')->where('name', 'Customer')->value('id')
            ?: DB::table('m_cont_categ')->insertGetId(['name' => 'Customer']);
        $venCatId = DB::table('m_cont_categ')->where('name', 'Vendor')->value('id')
            ?: DB::table('m_cont_categ')->insertGetId(['name' => 'Vendor']);

        DB::table('m_contacts')->updateOrInsert(['u_code' => 'CUS001'], [
            'company_n' => 'PT Astra Otoparts', 'nick_n' => 'Astra', 'category_id' => $catId,
            'active' => 1, 'updated_at' => now(), 'created_at' => now(),
        ]);
        DB::table('m_contacts')->updateOrInsert(['u_code' => 'VEN001'], [
            'company_n' => 'PT Steel Supply Indonesia', 'nick_n' => 'SSI', 'category_id' => $venCatId,
            'active' => 1, 'updated_at' => now(), 'created_at' => now(),
        ]);

        // Sample items — group = Material / FG; type = physical shape
        $matGroup = DB::table('m_i_category')->where('name_c', 'Material')->value('id');
        $fgGroup = DB::table('m_i_category')->where('name_c', 'FG')->value('id');

        DB::table('m_item')->updateOrInsert(['code' => 'RM-STK-42x6000'], [
            'part_name' => 'Steel Tube OD42 t3', 'type' => 'Pipe', 'descrip' => 'Pipa baja OD42 tebal 3mm',
            'category_id' => $matGroup, 'o_d' => 42.00, 'thick' => 3.00, 'length' => 6000.00, 'weight' => 17.30,
            'min_stock' => 0, 'max_stock' => 0, 'active' => 1,
            'updated_at' => now(), 'created_at' => now(),
        ]);
        DB::table('m_item')->updateOrInsert(['code' => 'FG-BOSS-001'], [
            'part_name' => 'Boss Steering Axle', 'type' => 'Roundbar', 'descrip' => 'Boss untuk steering axle',
            'category_id' => $fgGroup, 'min_stock' => 0, 'max_stock' => 0, 'active' => 1,
            'updated_at' => now(), 'created_at' => now(),
        ]);

        // Shifts (dipakai WMS putaway / MES)
        foreach ([['S1', 'Shift 1'], ['S2', 'Shift 2'], ['S3', 'Shift 3']] as [$code, $name]) {
            DB::table('m_shift')->updateOrInsert(['code' => $code], ['name' => $name]);
        }

        // Sample import quota (Fase 2) covering the RM steel tube
        $rmItemId = DB::table('m_item')->where('code', 'RM-STK-42x6000')->value('id');
        DB::table('m_quota')->updateOrInsert(['code' => 'PI-2026-001'], [
            'descrip' => 'Kuota impor baja 2026', 'hs_code' => '7304.31',
            'total_ton' => 500.000, 'valid_from' => '2026-01-01', 'valid_to' => '2026-12-31',
            'active' => 1, 'updated_at' => now(), 'created_at' => now(),
        ]);
        $quotaId = DB::table('m_quota')->where('code', 'PI-2026-001')->value('id');
        if ($quotaId && $rmItemId) {
            DB::table('m_quota_item')->updateOrInsert(['quota_id' => $quotaId, 'item_id' => $rmItemId], []);
        }
    }
}
