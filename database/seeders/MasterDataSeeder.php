<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\WorkCalendarService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Menyemai Master Data Skala Besar (100+ Data per Entitas)…');

        $this->seedCategories();
        $this->seedUoms();
        $this->seedCurrenciesAndRates();
        $this->seedTaxes();
        $this->seedContacts();
        $this->seedItems();
        $this->seedBoms();
        // Machines first: cycle times are recorded per machine, so the routing
        // step below needs them to already exist.
        $this->seedMachinesAndMakers();
        $this->seedProcessesAndRoutings();
        $this->seedRacksAndPallets();
        $this->seedQuotas();
        $this->seedPricelists();
        $this->seedInspectionParamsAndDefects();
        $this->seedAssetsAndCostRates();
        $this->seedWorkCalendar();
        $this->seedSupplierItems();
        // Last: an engineering change notice points at an item and its BOM, so
        // both have to exist before one can be written against them.
        $this->seedEcn();

        $this->command?->info('  Master Data Skala Besar selesai disemai.');
    }

    private function seedCategories(): void
    {
        $categories = [
            ['id' => 1, 'name_c' => 'Raw Material Pipe (RM)'],
            ['id' => 2, 'name_c' => 'Part Material Component (PM)'],
            ['id' => 3, 'name_c' => 'Finished Goods Tube Part (FG)'],
            ['id' => 4, 'name_c' => 'Consumables & Spareparts'],
            ['id' => 5, 'name_c' => 'Subcontract Services'],
        ];

        foreach ($categories as $cat) {
            SeedWriter::put('m_i_category', $cat);
        }
    }

    private function seedUoms(): void
    {
        $uoms = [
            ['id' => 1, 'code' => 'PCS',   'name' => 'Pieces (Pcs)',        'uom_type' => 'QTY',    'active' => 1],
            ['id' => 2, 'code' => 'MM',    'name' => 'Millimeter (mm)',     'uom_type' => 'LENGTH', 'active' => 1],
            ['id' => 3, 'code' => 'KG',    'name' => 'Kilogram (kg)',       'uom_type' => 'WEIGHT', 'active' => 1],
            ['id' => 4, 'code' => 'TON',   'name' => 'Metrik Ton (MT)',     'uom_type' => 'WEIGHT', 'active' => 1],
            ['id' => 5, 'code' => 'LOT',   'name' => 'Lot / Batch',        'uom_type' => 'QTY',    'active' => 1],
            ['id' => 6, 'code' => 'BOX',   'name' => 'Box / Kotak',        'uom_type' => 'QTY',    'active' => 1],
            ['id' => 7, 'code' => 'SET',   'name' => 'Set / Pasang',       'uom_type' => 'QTY',    'active' => 1],
            ['id' => 8, 'code' => 'MTR',   'name' => 'Meter (m)',          'uom_type' => 'LENGTH', 'active' => 1],
            ['id' => 9, 'code' => 'DRUM',  'name' => 'Drum / Jerigen',     'uom_type' => 'VOLUME', 'active' => 1],
            ['id' => 10, 'code' => 'PACK', 'name' => 'Pack / Kemasan',     'uom_type' => 'QTY',    'active' => 1],
        ];

        foreach ($uoms as $uom) {
            SeedWriter::put('m_uom', $uom);
        }
    }

    private function seedCurrenciesAndRates(): void
    {
        // m_currency carries no symbol column — the code doubles as the label.
        $currencies = [
            ['id' => 1, 'code' => 'IDR', 'name' => 'Rupiah Indonesia', 'is_base' => 1],
            ['id' => 2, 'code' => 'USD', 'name' => 'Dolar Amerika Serikat', 'is_base' => 0],
            ['id' => 3, 'code' => 'JPY', 'name' => 'Yen Jepang', 'is_base' => 0],
            ['id' => 4, 'code' => 'EUR', 'name' => 'Euro Eropa', 'is_base' => 0],
            ['id' => 5, 'code' => 'SGD', 'name' => 'Dolar Singapura', 'is_base' => 0],
        ];

        foreach ($currencies as $c) {
            SeedWriter::put('m_currency', $c);
        }

        $validDate = DemoCalendar::date(1)->toDateString();
        $rates = [
            ['id' => 1, 'currency_id' => 1, 'rate_type' => 'KMK', 'rate' => 1.000000,      'valid_date' => $validDate],
            ['id' => 2, 'currency_id' => 2, 'rate_type' => 'KMK', 'rate' => 16250.000000,  'valid_date' => $validDate],
            ['id' => 3, 'currency_id' => 3, 'rate_type' => 'KMK', 'rate' => 105.500000,    'valid_date' => $validDate],
            ['id' => 4, 'currency_id' => 4, 'rate_type' => 'KMK', 'rate' => 17600.000000,  'valid_date' => $validDate],
            ['id' => 5, 'currency_id' => 5, 'rate_type' => 'KMK', 'rate' => 12100.000000,  'valid_date' => $validDate],
        ];

        foreach ($rates as $r) {
            SeedWriter::put('m_rate', $r);
        }
    }

    private function seedTaxes(): void
    {
        $effFrom = DemoCalendar::date(1)->toDateString();
        $taxes = [
            ['id' => 1, 'code' => 'PPN12',  'name' => 'PPN 12% (DPP Nilai Lain 11/12)',  'rate_pct' => 12.000000, 'dpp_factor' => 0.916667, 'is_luxury' => 0, 'effective_from' => $effFrom],
            ['id' => 2, 'code' => 'PPH22',  'name' => 'PPh 22 Impor (0.25%)',           'rate_pct' => 0.250000,  'dpp_factor' => 1.000000, 'is_luxury' => 0, 'effective_from' => $effFrom],
            ['id' => 3, 'code' => 'PPH23',  'name' => 'PPh 23 Jasa Subcont (2%)',       'rate_pct' => 2.000000,  'dpp_factor' => 1.000000, 'is_luxury' => 0, 'effective_from' => $effFrom],
            ['id' => 4, 'code' => 'NONTAX', 'name' => 'Bebas Pajak',                    'rate_pct' => 0.000000,  'dpp_factor' => 1.000000, 'is_luxury' => 0, 'effective_from' => $effFrom],
            ['id' => 5, 'code' => 'PPN11',  'name' => 'PPN Standar 11%',               'rate_pct' => 11.000000, 'dpp_factor' => 1.000000, 'is_luxury' => 0, 'effective_from' => $effFrom],
        ];

        foreach ($taxes as $t) {
            SeedWriter::put('m_tax', $t);
        }
    }

    private function seedContacts(): void
    {
        // m_cont_categ.name is varchar(20) — keep these short.
        $categories = [
            ['id' => 1, 'name' => 'Supplier'],
            ['id' => 2, 'name' => 'Subcontractor'],
            ['id' => 3, 'name' => 'Customer'],
        ];

        foreach ($categories as $cat) {
            SeedWriter::put('m_cont_categ', $cat);
        }

        $contacts = [];
        $cId = 1;

        // 45 Vendors (1 to 45)
        for ($i = 1; $i <= 45; $i++) {
            $contacts[] = [
                'id' => $cId++,
                'u_code' => sprintf('VEND-%03d', $i),
                'initial' => sprintf('V%02d', $i),
                'nick_n' => "Supplier Steel $i",
                'company_n' => "PT Steel Supply Industry $i",
                'category_id' => 1,
                // identity is a short internal reference (varchar 10); the tax
                // number lives in npwp, which is what the e-Faktur export reads.
                'identity' => sprintf('V%04d', $i),
                'npwp' => sprintf('01.%03d.%03d.8-012.000', $i, $i * 3),
                'address' => "Kawasan Industri Cikarang Blok B-$i",
                'name' => "Contact Person Vendor $i",
                'phone' => sprintf('021-898%04d', $i),
                'email' => "vendor$i@steelsupply.co.id",
                'active' => 1,
            ];
        }

        // 15 Subcontractors (46 to 60)
        for ($i = 1; $i <= 15; $i++) {
            $id = $cId++;
            $contacts[] = [
                'id' => $id,
                'u_code' => sprintf('SUBC-%03d', $i),
                'initial' => sprintf('SC%02d', $i),
                'nick_n' => "Subcont Process $i",
                'company_n' => "PT Heat Treat & Plating Utama $i",
                'category_id' => 2,
                'identity' => sprintf('S%04d', $i),
                'npwp' => sprintf('02.%03d.%03d.9-015.000', $i, $i * 5),
                'address' => "Kawasan Industri KIIC Karawang Lot C-$i",
                'name' => "Manager Subcont $i",
                'phone' => sprintf('0267-845%03d', $i),
                'email' => "subcont$i@heattreat.co.id",
                'active' => 1,
            ];
        }

        // 50 Customers (61 to 110)
        for ($i = 1; $i <= 50; $i++) {
            $id = $cId++;
            $contacts[] = [
                'id' => $id,
                'u_code' => sprintf('CUST-%03d', $i),
                'initial' => sprintf('C%02d', $i),
                'nick_n' => "Customer Auto $i",
                'company_n' => "PT Astra Heavy Equipment & Automotive Parts $i",
                'category_id' => 3,
                'identity' => sprintf('C%04d', $i),
                'npwp' => sprintf('01.%03d.%03d.3-045.000', $i + 10, $i * 7),
                'address' => "Kawasan Industri Pulogadung / EJIP Lot D-$i",
                'name' => "Purchasing Manager $i",
                'phone' => sprintf('021-460%04d', $i),
                'email' => "purchasing$i@astra-he.co.id",
                'active' => 1,
            ];
        }

        foreach (array_chunk($contacts, 50) as $chunk) {
            foreach ($chunk as $c) {
                SeedWriter::put('m_contacts', $c);
            }
        }

        User::where('username', 'vendor')->update(['ven_id' => 1]);
    }

    private function seedItems(): void
    {
        $items = [];
        $iId = 1;

        // 40 RM Items (IDs 1..40)
        for ($i = 1; $i <= 40; $i++) {
            $od = 30 + ($i * 2);
            $thick = 2 + ($i % 5);
            $id_val = $od - (2 * $thick);
            $weight = round(3.14159 * ($od - $thick) * $thick * 0.00785 * 6.0, 2);

            $items[] = [
                'id' => $iId++,
                'code' => sprintf('RM-STKM-%03d', $i),
                'part_name' => sprintf('Pipa Steel STKM OD %dmm Thk %dmm', $od, $thick),
                'type' => 'RM',
                'descrip' => sprintf('Bahan Baku Pipa Baja Presisi STKM Gr.%d', 11 + ($i % 3)),
                'category_id' => 1,
                'o_d' => $od,
                'i_d' => $id_val,
                'thick' => $thick,
                'length' => 6000.00,
                'length_cut' => 6000.00,
                'weight' => $weight,
                'tolerance' => '±0.05mm',
                'min_stock' => 50,
                'max_stock' => 500,
                // Purchasing constraints MRP rounds an order up to: the mill
                // will not sell fewer than 25 bars and ships them in bundles.
                'moq' => 25,
                'order_lot' => ($i % 3 === 0) ? 25 : 10,
                'lead_time_days' => ($i % 2 === 0) ? 45 : 14,   // impor vs lokal
                'pm' => 0,
                'active' => 1,
                'status' => 'APPROVED',
            ];
        }

        // 30 PM Items (IDs 41..70)
        for ($i = 1; $i <= 30; $i++) {
            $items[] = [
                'id' => $iId++,
                'code' => sprintf('PM-COMP-%03d', $i),
                'part_name' => sprintf('Rubber Cap Protective & Ring Component %dmm', $i * 2),
                'type' => 'PM',
                'descrip' => sprintf('Komponen Pelindung Ujung Pipa Plastik/Karet %dmm', $i * 2),
                'category_id' => 2,
                'o_d' => 30 + ($i * 2),
                'i_d' => 28 + ($i * 2),
                'thick' => 2.00,
                'length' => 25.00,
                'length_cut' => 25.00,
                'weight' => 0.05,
                'tolerance' => '±0.1mm',
                'min_stock' => 500,
                'max_stock' => 5000,
                'pm' => 1,
                'active' => 1,
                'status' => 'APPROVED',
            ];
        }

        // 40 FG Items (IDs 71..110)
        for ($i = 1; $i <= 40; $i++) {
            $rmRef = $items[$i - 1]; // RM 1..40
            $cutLength = 100.00 + ($i * 5);
            $useLength = $cutLength - 5.00;
            $weight = round(($rmRef['weight'] / 6000.00) * $useLength, 2);

            $items[] = [
                'id' => $iId++,
                'code' => sprintf('FG-PART-%03d', $i),
                'part_name' => sprintf('Bush & Tube Component %dx%dmm Part #%d', (int) $rmRef['o_d'], (int) $useLength, $i),
                'type' => 'FG',
                'descrip' => sprintf('Finished Goods Bush Steering & Cylinder Tube Heavy Duty Part #%d', $i),
                'category_id' => 3,
                'o_d' => $rmRef['o_d'],
                'i_d' => $rmRef['i_d'],
                'thick' => $rmRef['thick'],
                'length' => $useLength,
                'length_cut' => $cutLength,
                'weight' => $weight,
                'tolerance' => '±0.02mm',
                'min_stock' => 100,
                'max_stock' => 1000,
                'pm' => 0,
                'active' => 1,
                'status' => 'APPROVED',
            ];
        }

        /*
         * Consumable sengaja tidak lagi disemai ke master item produksi.
         *
         * Sejak modul WHS Tools dibangun, sparepart dan barang habis pakai punya
         * masternya sendiri (`m_whs_item`) beserta PO, penerimaan, pengeluaran,
         * dan stoknya. Sepuluh baris "CS-ITEM" yang dulu ada di sini tidak
         * pernah dirujuk apa pun, tetapi tetap muncul di pemilih item —
         * sehingga pisau gergaji bisa masuk ke rencana produksi atau sales
         * order, dan golongannya berubah jadi bahan baku begitu seseorang
         * membuka lalu menyimpannya dari layar Item Master.
         *
         * Yang tersisa di master ini hanyalah tiga golongan yang memang
         * diproduksi atau dipakai membuat produk: RM, PM, dan FG.
         */

        foreach (array_chunk($items, 50) as $chunk) {
            foreach ($chunk as $it) {
                SeedWriter::put('m_item', $it);
            }
        }

        // Customer Part Mappings for 40 FG items across 50 Customers
        $custItems = [];
        $ciId = 1;
        for ($i = 1; $i <= 40; $i++) {
            $fgId = 70 + $i; // FG Items 71..110
            $cusId = 60 + (($i % 50) + 1); // Customers 61..110
            $custItems[] = [
                'id' => $ciId++,
                'item_id' => $fgId,
                'cus_id' => $cusId,
                // m_item_customer only records which customer an FG belongs to
                // and in what order it is offered — no part-number column here.
                'priority' => 1,
                'active' => 1,
            ];
        }

        foreach ($custItems as $ci) {
            SeedWriter::put('m_item_customer', $ci);
        }
    }

    private function seedBoms(): void
    {
        $boms = [];
        $bomRms = [];
        $bomPms = [];

        for ($i = 1; $i <= 40; $i++) {
            $fgId = 70 + $i; // FG Item 71..110
            $rmId = $i;      // RM Item 1..40
            $pmId = 40 + (($i % 30) + 1); // PM Item 41..70

            $boms[] = ['id' => $i, 'item_id' => $fgId, 'active' => 1];

            $cutLen = 100.00 + ($i * 5);
            $useLen = $cutLen - 5.00;

            $bomRms[] = [
                'id' => $i,
                'id_prim' => $i,
                'mat_id' => $rmId,
                'length_cut' => $cutLen,
                'length_use' => $useLen,
                'priority' => 1,
            ];

            $bomPms[] = [
                'id' => $i,
                'id_prim' => $i,
                'pm_id' => $pmId,
                'qty' => 2,
            ];
        }

        foreach ($boms as $b) {
            SeedWriter::put('m_bom', $b);
        }
        foreach ($bomRms as $br) {
            SeedWriter::put('m_bom_det_rm', $br);
        }
        foreach ($bomPms as $bp) {
            SeedWriter::put('m_bom_det_pm', $bp);
        }
    }

    private function seedProcessesAndRoutings(): void
    {
        $processes = [
            ['id' => 1, 'code' => 'PROC-CUT', 'name_p' => 'Cutting (Pemotongan Pipa)', 'descript' => 'Pemotongan pipa batang sesuai panjang potong BOM', 'active' => 1],
            ['id' => 2, 'code' => 'PROC-MACH', 'name_p' => 'Machining (Bubut CNC)', 'descript' => 'Proses pembubutan OD/ID dan facing ujung pipa', 'active' => 1],
            ['id' => 3, 'code' => 'PROC-CHAM', 'name_p' => 'Chamfering (Pengasahan)', 'descript' => 'Pembuatan chamfer bevel ujung pipa', 'active' => 1],
            ['id' => 4, 'code' => 'PROC-SUBC', 'name_p' => 'Subcont Heat Treatment', 'descript' => 'Proses pengerasan/heat treatment di vendor luar', 'active' => 1],
            ['id' => 5, 'code' => 'PROC-PACK', 'name_p' => 'Inspection & Packing', 'descript' => 'Pemeriksaan akhir (OQC) dan pengemasan ke pallet/box', 'active' => 1],
        ];

        foreach ($processes as $p) {
            SeedWriter::put('m_process', $p);
        }

        /*
         * A routing is a reusable template, not a property of one item: several
         * products share the same sequence of operations. An item is linked to
         * the templates it may run through in m_bom_pro, ranked by priority, and
         * a Work Order picks one of them. Three templates cover the catalogue —
         * with and without an outside heat-treatment step, plus a short one.
         */
        $templates = [
            1 => ['code' => 'RTG-STD', 'name' => 'Standar: Cut → Machining → Chamfer → Packing', 'steps' => [1, 2, 3, 5]],
            2 => ['code' => 'RTG-SUB', 'name' => 'Dengan Subcont: Cut → Machining → Heat Treat → Packing', 'steps' => [1, 2, 4, 5]],
            3 => ['code' => 'RTG-SHT', 'name' => 'Pendek: Cut → Machining → Packing', 'steps' => [1, 2, 5]],
        ];

        $pmdId = 1;
        foreach ($templates as $tid => $tpl) {
            SeedWriter::put('m_process_main', ['id' => $tid,
                'code' => $tpl['code'], 'name' => $tpl['name'], 'active' => 1, 'status' => 'APPROVED',
            ]);

            foreach ($tpl['steps'] as $seq => $procId) {
                DB::table('m_process_main_det')->updateOrInsert(['id' => $pmdId++], [
                    'main_id' => $tid, 'proc_id' => $procId, 'sequence' => $seq + 1,
                ]);
            }
        }

        // Every FG gets a primary routing plus a documented alternative, so the
        // Work Order screen genuinely has something to choose between.
        $bpId = 1;
        for ($i = 1; $i <= 40; $i++) {
            $fgId = 70 + $i;
            $primary = ($i % 3) + 1;
            $alternate = ($primary % 3) + 1;

            foreach ([$primary => 1, $alternate => 2] as $tid => $priority) {
                DB::table('m_bom_pro')->updateOrInsert(['id' => $bpId++], [
                    'item_id' => $fgId, 'process_main_id' => $tid, 'priority' => $priority,
                ]);
            }
        }

        /*
         * Cycle time is per item × process × machine: the same operation runs at
         * different speeds on different machines, and the planner picks by the
         * priority recorded here. Cutting runs on the saws, everything else on
         * the lathes; the subcontracted step has no in-house machine at all.
         */
        $machinesFor = [1 => [1, 2, 3], 2 => [4, 5, 6], 3 => [7, 8], 5 => [9, 10]];
        $rtId = 1;

        for ($i = 1; $i <= 40; $i++) {
            $fgId = 70 + $i;

            foreach ($machinesFor as $procId => $machines) {
                foreach ($machines as $rank => $machineId) {
                    DB::table('m_route_time')->updateOrInsert(['id' => $rtId++], [
                        'item_id' => $fgId,
                        'proc_id' => $procId,
                        'machine_id' => $machineId,
                        // The preferred machine is the fastest; the fallbacks lose a few seconds.
                        'cycle_sec' => 15.0 + ($i % 10) + ($rank * 3),
                        'setup_min' => 10.0 + ($i % 5),
                        'priority' => $rank + 1,
                        'active' => 1,
                    ]);
                }
            }
        }
    }

    private function seedMachinesAndMakers(): void
    {
        $makers = [
            ['id' => 1, 'name' => 'Tsune Seiki Japan', 'address' => 'Toyama, Japan', 'active' => 1],
            ['id' => 2, 'name' => 'Takisawa Machine Tool', 'address' => 'Okayama, Japan', 'active' => 1],
            ['id' => 3, 'name' => 'Amada Engineering', 'address' => 'Kanagawa, Japan', 'active' => 1],
            ['id' => 4, 'name' => 'Yaskawa Robotics', 'address' => 'Fukuoka, Japan', 'active' => 1],
            ['id' => 5, 'name' => 'Mazak CNC Systems', 'address' => 'Aichi, Japan', 'active' => 1],
        ];

        foreach ($makers as $m) {
            SeedWriter::put('m_maker_m', $m);
        }

        $machines = [];
        for ($i = 1; $i <= 20; $i++) {
            $categ = ($i <= 6) ? 'CUTTING' : (($i <= 14) ? 'MACHINING' : 'CHAMFERING');
            $machines[] = [
                'id' => $i,
                'code' => sprintf('MC-%s-%02d', substr($categ, 0, 3), $i),
                'name' => sprintf('Mesin %s #%d', $categ, $i),
                'model' => sprintf('MODEL-PRO-%03d', $i),
                'categ' => $categ,
                'maker_id' => ($i % 5) + 1,
                'min_d' => 10.00,
                'max_d' => 150.00,
                'func_id' => ($i <= 6) ? 1 : 2,
                'serial' => sprintf('SN-2023-%05d', $i * 111),
                'y_made' => 2023,
                'etd' => '2023-01-15',
                'pic_jp_id' => 1,
                'pic_local_id' => 2,
                'book_y_local' => '2023-02-01',
                'deps_m' => 120,
                'deps_exp' => '2033-02-01',
                'kwh' => 15.50,
                'active' => 1,
            ];
        }

        foreach ($machines as $mc) {
            SeedWriter::put('m_machine', $mc);
        }
    }

    private function seedRacksAndPallets(): void
    {
        $racks = [];
        $rId = 1;

        // 70 RM Racks (R-RM-001..070)
        for ($i = 1; $i <= 70; $i++) {
            $racks[] = [
                'id' => $rId++,
                'location' => sprintf('R-RM-%03d', $i),
                'descriptions' => sprintf('Rak RM Pipa Seksi %d', $i),
                'height' => 300,
                'width' => 600,
                'area' => 18,
                'rem_rack' => 0,
                'active' => 1,
                'depth' => '6500',
            ];
        }

        // 10 Remnant Racks (R-REM-001..010)
        for ($i = 1; $i <= 10; $i++) {
            $racks[] = [
                'id' => $rId++,
                'location' => sprintf('R-REM-%03d', $i),
                'descriptions' => sprintf('Rak Remnant / Tankan #%d', $i),
                'height' => 200,
                'width' => 400,
                'area' => 8,
                'rem_rack' => 1,
                'active' => 1,
                'depth' => '3000',
            ];
        }

        // 20 FG Racks (R-FG-001..020)
        for ($i = 1; $i <= 20; $i++) {
            $racks[] = [
                'id' => $rId++,
                'location' => sprintf('R-FG-%03d', $i),
                'descriptions' => sprintf('Rak Barang Jadi #%d', $i),
                'height' => 250,
                'width' => 300,
                'area' => 7.5,
                'rem_rack' => 0,
                'active' => 1,
                'depth' => '1200',
            ];
        }

        foreach ($racks as $r) {
            SeedWriter::put('m_rack', $r);
        }

        // 50 Pallets
        $pallets = [];
        for ($i = 1; $i <= 50; $i++) {
            $pallets[] = [
                'id' => $i,
                'code' => sprintf('PL-%03d', $i),
                'name_pa' => sprintf('Pallet Container Metal Industrial #%d', $i),
                'type_id' => ($i % 2) + 1,
                'size' => 1.20,
                'cap_kg' => 1000.00,
                'cap_m3' => 1.50,
                'note' => 'WIP Production Pallet',
                'active' => 1,
            ];
        }

        foreach ($pallets as $p) {
            SeedWriter::put('m_pallet', $p);
        }
    }

    private function seedQuotas(): void
    {
        $quotas = [];
        $quotaItems = [];
        $qiId = 1;
        $validFrom = DemoCalendar::date(1)->toDateString();
        $validTo = DemoCalendar::date(30)->toDateString();

        for ($i = 1; $i <= 5; $i++) {
            $quotas[] = [
                'id' => $i,
                'code' => sprintf('IMP-2026-%02d', $i),
                'descrip' => sprintf('Alokasi Kuota Impor Pipa Baja Resmi Kementerian Kategori-%d', $i),
                'hs_code' => sprintf('7304.%02d.00.00', 10 + $i),
                'total_ton' => 500.000 * $i,
                'valid_from' => $validFrom,
                'valid_to' => $validTo,
                'active' => 1,
            ];

            // Map 8 RM items to each quota
            for ($j = 1; $j <= 8; $j++) {
                $rmId = (($i - 1) * 8) + $j;
                $quotaItems[] = [
                    'id' => $qiId++,
                    'quota_id' => $i,
                    'item_id' => $rmId,
                ];
            }
        }

        foreach ($quotas as $q) {
            SeedWriter::put('m_quota', $q);
        }
        foreach ($quotaItems as $qi) {
            SeedWriter::put('m_quota_item', $qi);
        }
    }

    private function seedPricelists(): void
    {
        $pricelistMains = [];
        $pricelistDets = [];
        $pdId = 1;

        // 10 Customer Pricelist Mains
        for ($i = 1; $i <= 10; $i++) {
            $cusId = 60 + $i; // Customer 61..70
            $pricelistMains[] = [
                'id' => $i,
                'cus_id' => $cusId,
                // m_pricelist_main has no name column; the code carries the label
                // and the customer identifies whose contract it is.
                'code' => sprintf('PL-CUST-%03d-2026', $i),
                'user_id' => 1,
                'status' => 'APPROVED',
            ];

            // 10 Pricelist Details per Main (Total 100 entries)
            for ($j = 1; $j <= 10; $j++) {
                $fgId = 70 + $j; // FG Items 71..80
                $pricelistDets[] = [
                    'id' => $pdId++,
                    'main_id' => $i,
                    'item_id' => $fgId,
                    'price' => 40000.00 + ($j * 2500.00),
                    'currency_id' => 1,          // sold in rupiah
                    'min_qty' => 1,
                    'valid_from' => DemoCalendar::date(1)->toDateString(),
                    'valid_to' => DemoCalendar::date(30)->toDateString(),
                ];
            }
        }

        foreach ($pricelistMains as $pm) {
            SeedWriter::put('m_pricelist_main', $pm);
        }
        foreach ($pricelistDets as $pd) {
            SeedWriter::put('m_pricelist_det', $pd);
        }
    }

    private function seedInspectionParamsAndDefects(): void
    {
        $params = [
            ['id' => 1,  'code' => 'PAR-OD',   'name' => 'Outer Diameter (OD)',        'uom' => 'mm',    'method' => 'CALIPER',    'active' => 1],
            ['id' => 2,  'code' => 'PAR-ID',   'name' => 'Inner Diameter (ID)',        'uom' => 'mm',    'method' => 'CALIPER',    'active' => 1],
            ['id' => 3,  'code' => 'PAR-THK',  'name' => 'Wall Thickness',            'uom' => 'mm',    'method' => 'MICROMETER', 'active' => 1],
            ['id' => 4,  'code' => 'PAR-LEN',  'name' => 'Length',                    'uom' => 'mm',    'method' => 'TAPE',       'active' => 1],
            ['id' => 5,  'code' => 'PAR-SURF', 'name' => 'Surface Roughness (Ra)',    'uom' => 'µm',    'method' => 'PROFILOMETER', 'active' => 1],
            ['id' => 6,  'code' => 'PAR-HARD', 'name' => 'Hardness Rockwell (HRC)',   'uom' => 'HRC',   'method' => 'HARDNESS',   'active' => 1],
            ['id' => 7,  'code' => 'PAR-CONC', 'name' => 'Concentricity / Ovality',  'uom' => 'mm',    'method' => 'CMM',        'active' => 1],
            ['id' => 8,  'code' => 'PAR-BEV',  'name' => 'Bevel Angle Chamfer',      'uom' => 'deg',   'method' => 'VISUAL',     'active' => 1],
            ['id' => 9,  'code' => 'PAR-WGT',  'name' => 'Weight Verification',       'uom' => 'kg',    'method' => 'SCALE',      'active' => 1],
            ['id' => 10, 'code' => 'PAR-VIS',  'name' => 'Visual Appearance Defect', 'uom' => 'score', 'method' => 'VISUAL',     'active' => 1],
        ];

        foreach ($params as $pr) {
            SeedWriter::put('m_inspection_param', $pr);
        }

        $defects = [
            ['id' => 1, 'code' => 'DEF-BENT', 'name' => 'Pipa Bengkok (Bent Pipe)', 'type' => 'REJECT'],
            ['id' => 2, 'code' => 'DEF-BURR', 'name' => 'Burr Hasil Potong (Cutting Burr)', 'type' => 'REWORK'],
            ['id' => 3, 'code' => 'DEF-OD-OUT', 'name' => 'OD Out of Spec', 'type' => 'REJECT'],
            ['id' => 4, 'code' => 'DEF-SCRATCH', 'name' => 'Goresan Permukaan (Surface Scratch)', 'type' => 'REJECT'],
            ['id' => 5, 'code' => 'DEF-RUST', 'name' => 'Karatan Permukaan (Surface Rust)', 'type' => 'REWORK'],
            ['id' => 6, 'code' => 'DEF-CRACK', 'name' => 'Retak Permukaan (Surface Crack)', 'type' => 'REJECT'],
            ['id' => 7, 'code' => 'DEF-DENT', 'name' => 'Penyok Akibat Benturan (Dent)', 'type' => 'REJECT'],
            ['id' => 8, 'code' => 'DEF-SHORT', 'name' => 'Ukuran Potong Kurang (Short Length)', 'type' => 'REJECT'],
            ['id' => 9, 'code' => 'DEF-OVAL', 'name' => 'Keovalasi Melebihi Toleransi', 'type' => 'REWORK'],
            ['id' => 10, 'code' => 'DEF-CHAMFER', 'name' => 'Bevel Chamfer Miring/Cacat', 'type' => 'REWORK'],
        ];

        foreach ($defects as $df) {
            SeedWriter::put('m_defective', $df);
        }
    }

    private function seedAssetsAndCostRates(): void
    {
        // useful_life in MONTHS as per schema DDL comment
        $assetCategs = [
            ['id' => 1, 'code' => 'AST-MC', 'name' => 'Mesin & Peralatan Pabrik',        'useful_life' => 120, 'depr_method' => 'STRAIGHT'],
            ['id' => 2, 'code' => 'AST-VH', 'name' => 'Kendaraan Operasional Forklift',  'useful_life' => 60,  'depr_method' => 'STRAIGHT'],
        ];

        foreach ($assetCategs as $ac) {
            SeedWriter::put('m_asset_categ', $ac);
        }

        // ast_main fillable: code, categ_id, name, acq_date, acq_cost, useful_life, po_id, gr_detail_id, machine_id, status
        $assets = [];
        for ($i = 1; $i <= 20; $i++) {
            $assets[] = [
                'id' => $i,
                'code' => sprintf('AST-MC-%03d', $i),
                'name' => sprintf('Mesin Aset Tetap Processing Pipe #%d', $i),
                'categ_id' => 1,
                'acq_date' => '2023-01-15',
                'acq_cost' => 800000000.00 + ($i * 50000000.00),
                'useful_life' => 120,
                'machine_id' => $i,
                'status' => 'ACTIVE',
            ];
        }

        foreach ($assets as $ast) {
            SeedWriter::put('ast_main', $ast);
        }

        /*
         * Standard rates are per period and per process — COGM multiplies the
         * hours a Work Order spent on a process by the LABOR and FOH rate in
         * force that month, so a rate row without a process would never be found.
         */
        $period = DemoCalendar::period();
        $crId = 1;

        foreach ([1 => 'Cutting', 2 => 'Machining', 3 => 'Chamfering', 5 => 'Packing'] as $procId => $label) {
            foreach ([['LABOR', 45000.00], ['FOH', 85000.00]] as [$type, $base]) {
                DB::table('cst_rate')->updateOrInsert(['id' => $crId++], [
                    'period' => $period,
                    'rate_type' => $type,
                    'process_id' => $procId,
                    // Machining carries the heavier overhead; packing the lightest.
                    'rate_per_hour' => $base * ($procId === 2 ? 1.3 : ($procId === 5 ? 0.7 : 1.0)),
                ]);
            }
        }
    }

    /**
     * Working calendar for the seeded month and the two after it.
     *
     * Without this, MPS and CRP silently assume every weekday is a full
     * two-shift day. A couple of holidays are included so the demo shows the
     * schedule bending around them rather than pretending they do not exist.
     */
    private function seedWorkCalendar(): void
    {
        $svc = app(WorkCalendarService::class);
        $periods = [DemoCalendar::period(), DemoCalendar::nextPeriod(1), DemoCalendar::nextPeriod(2)];

        /*
         * Example holidays only.
         *
         * The real Indonesian public holidays are set by SKB 3 Menteri each
         * year and cannot be derived — Islamic dates shift and cuti bersama is
         * a government decision. Inventing plausible-looking dates here would
         * be worse than leaving them out, because a planner would trust them.
         * These are deliberately named as samples and dated relative to the
         * seeded month; replace them with the published SKB dates.
         */
        foreach ($periods as $i => $period) {
            /*
             * Deliberately placed on weekdays. A cuti bersama that lands on a
             * Saturday is already a rest day, so it would prove nothing about
             * the skeleton-crew rule the demo is meant to show.
             */
            $nationalDay = DemoCalendar::date(15)->copy()->addMonths($i)->startOfMonth()
                ->addDays(14)->next(Carbon::WEDNESDAY);
            $cutiBersama = $nationalDay->copy()->addDay();   // Thursday

            SeedWriter::put('m_holiday', [
                'date' => $nationalDay->toDateString(),
                'name' => 'CONTOH — Libur Nasional (ganti dengan SKB)',
                'type' => 'NASIONAL',
                'is_working' => 0,
                'hours' => 0,
                'active' => 1,
            ]);

            // Cuti bersama with a skeleton crew: the plant runs, but at one
            // shift instead of two. This is why the two are separate types.
            SeedWriter::put('m_holiday', [
                'date' => $cutiBersama->toDateString(),
                'name' => 'CONTOH — Cuti Bersama (kru minimal)',
                'type' => 'CUTI_BERSAMA',
                'is_working' => 1,
                'hours' => 8,
                'active' => 1,
            ]);
        }

        // Generation now reads the holiday master, so nothing is passed here.
        foreach ($periods as $period) {
            $svc->generate($period, [], 16.0, saturdayWorks: false);
        }

        // Two machines run a single shift — capacity is per machine, not a
        // plant-wide constant.
        DB::table('m_machine')->whereIn('id', [9, 10])->update(['daily_hours' => 8]);

        $days = count($svc->daysIn(DemoCalendar::period()));
        $this->command?->info("  Kalender kerja: {$days} hari kerja pada periode berjalan (libur nasional dikecualikan).");
    }

    /**
     * Supplier Item & Price — two or three suppliers per raw material.
     *
     * MOQ, order lot and lead time live here rather than on the item, because
     * they are terms a supplier offers: the priority-1 mill sells in bundles on
     * long lead time, the backup sells smaller quantities faster and dearer.
     * MRP picks the priority-1 row, so these numbers decide what gets ordered.
     */
    private function seedSupplierItems(): void
    {
        $id = 1;

        for ($item = 1; $item <= 40; $item++) {
            // Primary: cheapest, but slow and sold in bundles.
            SeedWriter::put('m_supplier_item', [
                'id' => $id++,
                'ven_id' => (($item % 45) + 1),
                'item_id' => $item,
                'priority' => 1,
                'price' => 430000 + ($item * 1500),
                'currency_id' => 1,
                'moq' => 25,
                'order_lot' => ($item % 3 === 0) ? 25 : 10,
                'lead_time_days' => ($item % 2 === 0) ? 45 : 14,
                'supplier_part_no' => sprintf('SP-%04d', $item),
                'active' => 1,
            ]);

            // Backup: quicker and more flexible, at a premium.
            SeedWriter::put('m_supplier_item', [
                'id' => $id++,
                'ven_id' => ((($item + 12) % 45) + 1),
                'item_id' => $item,
                'priority' => 2,
                'price' => 468000 + ($item * 1500),
                'currency_id' => 1,
                'moq' => 5,
                'order_lot' => 5,
                'lead_time_days' => 7,
                'supplier_part_no' => sprintf('ALT-%04d', $item),
                'active' => 1,
            ]);
        }

        $this->command?->info('  Supplier Item & Price: '.($id - 1).' penawaran (2 supplier per material).');
    }

    /**
     * Engineering Change Notices in each of the three states that matter.
     *
     * One already applied (so an item carries revision 1 and the change is
     * visible in its history), one approved but not yet due, and one still
     * waiting for a signature — because the interesting question on this screen
     * is always "what is about to change", not "what changed last year".
     */
    private function seedEcn(): void
    {
        $applied = DemoCalendar::date(8);

        // 1. Applied: a tolerance tightened after a customer complaint.
        SeedWriter::put('eng_ecn_main', [
            'id' => 1,
            'code' => 'ECN/2026/07/00001',
            'date' => DemoCalendar::date(5)->toDateString(),
            'change_type' => 'ITEM',
            'item_id' => 71,
            'reason' => 'Keluhan pelanggan: toleransi panjang terlalu longgar pada part #1.',
            'impact' => 'Setting cutting perlu dikalibrasi ulang; WO berjalan diselesaikan dengan revisi lama.',
            'effective_date' => $applied->toDateString(),
            'status' => 'APPLIED',
            'user_id' => 1,
            'applied_by' => 1,
            'applied_at' => $applied,
        ], $applied);

        SeedWriter::put('eng_ecn_det', [
            'id' => 1, 'main_id' => 1, 'action' => 'UPDATE',
            'target_table' => 'm_item', 'target_id' => 71, 'field' => 'tolerance',
            'old_value' => '±0.5', 'new_value' => '±0.2',
            'note' => 'Sesuai drawing rev B dari pelanggan.',
        ]);

        // The item carries the revision that change produced.
        DB::table('m_item')->where('id', 71)->update([
            'tolerance' => '±0.2', 'rev' => 1, 'rev_date' => $applied->toDateString(),
        ]);

        // 2. Approved, effective next month: a supplier change with a longer lead time.
        SeedWriter::put('eng_ecn_main', [
            'id' => 2,
            'code' => 'ECN/2026/07/00002',
            'date' => DemoCalendar::date(22)->toDateString(),
            'change_type' => 'ITEM',
            'item_id' => 3,
            'reason' => 'Ganti sumber material ke mill impor; lead time naik dari 14 ke 45 hari.',
            'impact' => 'MRP harus memesan lebih awal; stok minimum dinaikkan sebagai penyangga.',
            'effective_date' => DemoCalendar::date(22)->addMonth()->toDateString(),
            'status' => 'APPROVED',
            'user_id' => 1,
        ], DemoCalendar::date(22));

        SeedWriter::put('eng_ecn_det', [
            'id' => 2, 'main_id' => 2, 'action' => 'UPDATE',
            'target_table' => 'm_item', 'target_id' => 3, 'field' => 'lead_time_days',
            'old_value' => (string) (DB::table('m_item')->where('id', 3)->value('lead_time_days') ?? 14),
            'new_value' => '45', 'note' => 'Berlaku untuk PO yang terbit setelah tanggal efektif.',
        ]);
        SeedWriter::put('eng_ecn_det', [
            'id' => 3, 'main_id' => 2, 'action' => 'UPDATE',
            'target_table' => 'm_item', 'target_id' => 3, 'field' => 'min_stock',
            'old_value' => (string) (DB::table('m_item')->where('id', 3)->value('min_stock') ?? 0),
            'new_value' => '150', 'note' => 'Penyangga selama lead time impor.',
        ]);

        // 3. Waiting for a signature: a BOM change that shortens the cut.
        $bomLine = DB::table('m_bom_det_rm as d')
            ->join('m_bom as b', 'b.id', '=', 'd.id_prim')
            ->where('b.item_id', 72)
            ->value('d.id');

        if ($bomLine) {
            SeedWriter::put('eng_ecn_main', [
                'id' => 3,
                'code' => 'ECN/2026/07/00003',
                'date' => DemoCalendar::date(27)->toDateString(),
                'change_type' => 'BOM',
                'item_id' => 72,
                'reason' => 'Perpendek potongan 5 mm untuk menekan sisa (tankan) per batang.',
                'impact' => 'Hemat material ±3%; perlu uji coba satu lot sebelum diterapkan penuh.',
                'effective_date' => DemoCalendar::date(27)->addWeeks(2)->toDateString(),
                'status' => 'SUBMITTED',
                'user_id' => 1,
            ], DemoCalendar::date(27));

            $current = DB::table('m_bom_det_rm')->where('id', $bomLine)->value('length_use');
            SeedWriter::put('eng_ecn_det', [
                'id' => 4, 'main_id' => 3, 'action' => 'UPDATE',
                'target_table' => 'm_bom_det_rm', 'target_id' => $bomLine, 'field' => 'length_use',
                'old_value' => (string) $current, 'new_value' => (string) max(1, (float) $current - 5),
                'note' => 'Menunggu persetujuan Engineering & PPIC.',
            ]);
        }

        $this->command?->info('  ECN: 3 notice (1 diterapkan, 1 menunggu tanggal efektif, 1 menunggu tanda tangan).');
    }
}
