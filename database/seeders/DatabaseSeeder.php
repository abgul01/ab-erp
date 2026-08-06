<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One month of running a pipe factory (ERP + MES).
 *
 * The seed is a single chain, not a pile of unrelated rows: a customer forecast
 * becomes a sales order, which becomes a monthly plan, a schedule and a work
 * order; the work order's bill of material drives a purchase requisition, a PO,
 * a goods receipt with real serial numbers, inspection and putaway; those exact
 * serials are then booked, issued, cut, processed, checked and received into
 * finished goods, delivered against that same sales order, invoiced, paid, and
 * finally costed and journalled.
 *
 * Every document points at a document that genuinely exists, which makes the system
 * realistic and fully interconnected.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Master (reference) tables — wiped first because operational tables FK into them.
     * Child tables come before parents to avoid FK constraint errors on truncate.
     */
    private const MASTER_TABLES = [
        // BOM & routing details (depend on bom/process headers & items)
        'm_bom_det_rm', 'm_bom_det_pm', 'm_bom',
        'm_process_main_det', 'm_route_time', 'm_process_main',
        // item cross-references
        'm_item_customer', 'm_pricelist_det', 'm_pricelist_main',
        'm_quota_item', 'm_quota',
        // item master (depended on by almost everything)
        'm_item',
        // warehouse reference
        'm_pallet', 'm_rack',
        // machine & maker
        'm_machine', 'm_maker_m',
        // asset
        'ast_main', 'm_asset_categ',
        // costing
        'cst_rate',
        // QAS params & defects
        'm_inspection_param', 'm_defective',
        'm_work_calendar', 'm_holiday', 'm_supplier_item',
        // engineering change notices reference items & BOM lines
        'eng_ecn_det', 'eng_ecn_main',
        // master gudang non-material (transaksinya sudah dihapus di atas)
        'm_whs_item',
        // NPD (anak→induk); master fase & deliverable ikut disemai ulang
        'npd_member', 'npd_doc', 'npd_deliverable', 'npd_milestone', 'npd_task',
        'npd_project_phase', 'npd_feasibility', 'npd_rfq', 'npd_project',
        'npd_deliverable_std', 'npd_phase',
        // contact & category
        'm_contacts', 'm_cont_categ',
        // financial master
        'm_rate', 'm_currency',
        'm_tax',
        // UOM & item category
        'm_uom', 'm_i_category',
        // process
        'm_process',
    ];

    /**
     * Transactional tables the demo owns. Wiped before seeding so a re-run
     * produces a clean, consistent dataset.
     */
    private const OPERATIONAL_TABLES = [
        // procurement & QAS
        'prc_pr_main', 'prc_pr_detail', 'prc_po_main', 'prc_po_detail',
        'prc_gr_main', 'prc_gr_detail', 'prc_gr_serial', 'prc_quota_txn',
        'prc_inv_main', 'prc_inv_detail', 'prc_cost_main', 'prc_cost_detail', 'prc_cost_alloc',
        'prc_gr_reject', 'prc_po_schedule',
        'qc_incoming_main', 'qc_incoming_det',
        // warehouse
        'wh_inc_main', 'wh_inc_detail', 'wh_out_main', 'wh_out_detail',
        'wh_rem_main', 'wh_rem_detail', 'wh_adj_main', 'wh_adj_detail',
        // gudang WHS (anak→induk)
        'whs_ret_det', 'whs_ret_main', 'whs_tool_unit',
        'whs_out_det', 'whs_out_main', 'whs_inc_det', 'whs_inc_main',
        'whs_po_det', 'whs_po_main',
        'stock_check',
        // planning & production
        'prd_mpp', 'prd_mps', 'prd_mps_resched', 'prd_mrp_main', 'prd_mrp_detail', 'prd_crp',
        'prd_wo_main', 'prd_wo_detail_rm', 'prd_wo_serial_rm', 'prd_wo_detail_pm', 'prd_wo_serial_pm',
        'prd_kanban', 'prd_wip', 'prd_fcs_main', 'prd_scrap_decisions',
        // MES
        'tr_cut_main', 'tr_cut_detail', 'tr_cut_serial', 'tr_cut_pal_pr',
        'tr_pro_main', 'tr_pro_detail', 'tr_pro_pallet', 'tr_pro_pal_pr',
        'tr_ab_cut_main', 'tr_ab_cut_det', 'tr_ab_pro', 'tr_dt_cut_main', 'tr_dt_pro_main',
        'mes_oplog',
        // finished goods & sales
        'tr_inc_fg_main', 'tr_inc_fg_det', 'tr_out_fg_main', 'tr_out_fg_det',
        'sls_forecast', 'sls_so_main', 'sls_so_detail', 'sls_do_main', 'sls_do_detail',
        'sls_inv_main', 'sls_inv_detail', 'sls_return',
        // shipping paperwork (child→parent)
        'sls_pack_det', 'sls_pack_main', 'sls_ship_det', 'sls_ship_main',
        // subcontract
        'sub_po_main', 'sub_dn_main', 'sub_gr_main', 'sub_progress',
        // costing & accounting
        'cst_cogm', 'ast_depre',
        'acc_journal_main', 'acc_journal_det',
        'acc_ap_pay_main', 'acc_ap_pay_det', 'acc_ar_rec_main', 'acc_ar_rec_det',
        'approvals', 'log_prc',
    ];

    public function run(): void
    {
        $this->command?->info('Membersihkan semua data lama (operasional + master)…');
        $this->wipe();

        $this->call([
            FoundationSeeder::class,     // status, user, menu, hak akses, COA, periode
            MasterDataSeeder::class,     // item, BOM, routing, mesin, rak, harga, kuota, aset, tarif
            DemandSeeder::class,         // forecast → sales order
            PlanningSeeder::class,       // MPP → MPS → MRP → CRP → Work Order
            ProcurementSeeder::class,    // PR → PO → GR + serial → QAS → putaway → invoice → landed cost
            ProductionSeeder::class,     // booking → kanban → MES cutting → MES processing → subcont → FCS → FG
            FulfilmentSeeder::class,     // DO → outgoing FG → sales invoice & e-Faktur
            AccountingSeeder::class,     // AP/AR payments, COGM, depresiasi, opname, jurnal, audit log
            // Setelah akuntansi: jurnal WHS memakai nomor lanjutan, bukan nomor
            // yang sudah dipakai seeder akuntansi.
            WhsSeeder::class,            // gudang non-material: master, PO, terima, pakai, pinjam-kembali alat
            NpdSeeder::class,            // NPD: master 5 fase APQP, deliverable wajib, proyek contoh
            // Runs last on purpose: MRP can only demonstrate netting once stock,
            // open POs and existing requisitions are in place.
            ReplanSeeder::class,         // MRP ulang → Purchase Requisition
        ]);

        $this->command?->newLine();
        $this->command?->info('Semua data seeder berhasil diperbarui & disemai secara lengkap!');
        $this->command?->info('Login Administrator: admin / password');
        $this->command?->info('Login Operator (PPIC): operator / password');
        $this->command?->info('Login Vendor Portal: vendor / password');
    }

    private function wipe(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Wipe operational first (they FK into master)
        foreach (self::OPERATIONAL_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        // Then wipe master tables (child→parent order already respected in the list)
        foreach (self::MASTER_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
