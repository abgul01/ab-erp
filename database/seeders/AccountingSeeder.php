<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Menyemai Akuntansi & Keuangan Skala Besar (100+ AP/AR, COGM, Jurnal GL)…');

        $this->seedApPayments();
        $this->seedArReceipts();
        $this->seedCogm();
        $this->seedAssetDepreciation();
        $this->seedStockAdjustments();
        $this->seedJournals();
        $this->seedApprovalsAndAuditLogs();

        $this->command?->info('  Akuntansi & Keuangan Skala Besar disemai.');
    }

    private function seedApPayments(): void
    {
        $apPays = [];
        $apDets = [];
        $apdId = 1;

        for ($i = 1; $i <= 100; $i++) {
            $day = (($i - 1) % 10) + 21;
            $venId = ($i % 45) + 1;
            $amount = 15000000.00 + ($i * 500000.00);

            $apPays[] = [
                'id' => $i,
                'code' => sprintf('PAY-AP-2026-%03d', $i),
                'ven_id' => $venId,
                'date' => DemoCalendar::date($day)->toDateString(),
                'amount' => $amount,
                'user_id' => 1,
                'status' => 'POSTED',
                'created_at' => DemoCalendar::date($day),
            ];

            $apDets[] = [
                'id' => $apdId++,
                'main_id' => $i,
                'inv_id' => $i,
                'amount' => $amount,
            ];
        }

        foreach (array_chunk($apPays, 50) as $chunk) {
            foreach ($chunk as $ap) {
                SeedWriter::put('acc_ap_pay_main', $ap);
            }
        }
        foreach (array_chunk($apDets, 50) as $chunk) {
            foreach ($chunk as $apd) {
                SeedWriter::put('acc_ap_pay_det', $apd);
            }
        }
    }

    private function seedArReceipts(): void
    {
        $arRecs = [];
        $arDets = [];
        $ardId = 1;

        for ($i = 1; $i <= 100; $i++) {
            $day = (($i - 1) % 10) + 23;
            $cusId = 60 + (($i % 50) + 1);
            $amount = 25000000.00 + ($i * 750000.00);

            $arRecs[] = [
                'id' => $i,
                'code' => sprintf('REC-AR-2026-%03d', $i),
                'cus_id' => $cusId,
                'date' => DemoCalendar::date($day)->toDateString(),
                'amount' => $amount,
                'user_id' => 1,
                'status' => 'POSTED',
                'created_at' => DemoCalendar::date($day),
            ];

            $arDets[] = [
                'id' => $ardId++,
                'main_id' => $i,
                'inv_id' => $i,
                'amount' => $amount,
            ];
        }

        foreach (array_chunk($arRecs, 50) as $chunk) {
            foreach ($chunk as $ar) {
                SeedWriter::put('acc_ar_rec_main', $ar);
            }
        }
        foreach (array_chunk($arDets, 50) as $chunk) {
            foreach ($chunk as $ard) {
                SeedWriter::put('acc_ar_rec_det', $ard);
            }
        }
    }

    private function seedCogm(): void
    {
        $cogms = [];
        for ($i = 1; $i <= 100; $i++) {
            $fgId = 70 + (($i % 40) + 1);
            $qtyOk = 500 + ($i * 10);
            $mat = 20000000.00 + ($i * 500000.00);
            $labor = 2500000.00 + ($i * 50000.00);
            $foh = 4500000.00 + ($i * 100000.00);
            $subcont = ($i % 2 === 0) ? 3500000.00 : 0.00;
            $totalCogm = $mat + $labor + $foh + $subcont;
            $unitCost = round($totalCogm / $qtyOk, 2);

            // Scrap sold back recovers a little of the material cost, so the
            // total is the sum of the inputs less that recovery.
            $scrapRecovery = ($i % 4 === 0) ? 450000.00 : 0.00;
            $totalCogm -= $scrapRecovery;
            $unitCost = round($totalCogm / $qtyOk, 2);

            $cogms[] = [
                'id' => $i,
                'period' => DemoCalendar::period(),
                'wo_id' => $i,
                'material_cost' => $mat,
                'labor_cost' => $labor,
                'foh_cost' => $foh,
                'subcont_cost' => $subcont,
                'scrap_recovery' => $scrapRecovery,
                'total' => $totalCogm,
                'unit_cost' => $unitCost,
            ];
        }

        foreach (array_chunk($cogms, 50) as $chunk) {
            foreach ($chunk as $cg) {
                SeedWriter::put('cst_cogm', $cg);
            }
        }
    }

    private function seedAssetDepreciation(): void
    {
        $period = DemoCalendar::period();
        $depres = [];

        for ($i = 1; $i <= 20; $i++) {
            $depres[] = [
                'id' => $i,
                'ast_id' => $i,       // model fillable: ast_id
                'period' => $period,
                'amount' => 7500000.00 + ($i * 500000.00),
                // journal_id left null — FK to journal posted below
            ];
        }

        foreach ($depres as $dp) {
            SeedWriter::put('ast_depre', $dp);
        }
    }

    private function seedStockAdjustments(): void
    {
        $stockChecks = [];
        $adjMains = [];
        $adjDets = [];
        $adId = 1;

        for ($i = 1; $i <= 50; $i++) {
            $rmId = (($i % 40) + 1);
            $rackId = (($i % 70) + 1);

            $stockChecks[] = [
                'id' => $i,
                'user_id' => 1,
                'date' => DemoCalendar::date(28),
                'item_id' => $rmId,
                'rack_id' => $rackId,
                'length' => 6000,
                'match' => 1,
                'qty_data' => 25,
                'qty_actual' => 25,
                'created_at' => DemoCalendar::date(28),
            ];

            /*
             * A month-end count. Most lines tally exactly; every fifth shows a
             * small shortfall, which is what gives the variance journal and the
             * Opname screen something real to display.
             */
            $counted = ($i % 5 === 0) ? 23 : 25;
            $unitCost = 2437500.00;

            $adjMains[] = [
                'id' => $i,
                'code' => sprintf('ADJ-2026-%03d', $i),
                'date' => DemoCalendar::date(28)->toDateString(),
                'adj_type' => 'OPNAME',
                'warehouse' => 'RM',
                'user_id' => 1,
                'posted_by' => 1,
                'posted_at' => DemoCalendar::date(28),
                'reason' => sprintf('Stock opname bulanan rak #%d', $rackId),
                'status' => 'POSTED',
                'created_at' => DemoCalendar::date(28),
            ];

            $adjDets[] = [
                'id' => $adId++,
                'main_id' => $i,
                'item_id' => $rmId,
                'qty_system' => 25,
                'qty_counted' => $counted,
                'qty_diff' => $counted - 25,
                'unit_cost' => $unitCost,
                'note' => $counted < 25 ? 'Selisih kurang, dugaan salah catat pengeluaran' : null,
            ];
        }

        foreach (array_chunk($stockChecks, 50) as $chunk) {
            foreach ($chunk as $sc) {
                SeedWriter::put('stock_check', $sc);
            }
        }
        foreach (array_chunk($adjMains, 50) as $chunk) {
            foreach ($chunk as $am) {
                SeedWriter::put('wh_adj_main', $am);
            }
        }
        foreach (array_chunk($adjDets, 50) as $chunk) {
            foreach ($chunk as $ad) {
                SeedWriter::put('wh_adj_detail', $ad);
            }
        }
    }

    private function seedJournals(): void
    {
        $period = DemoCalendar::period();
        $journalMains = [];
        $journalDets = [];
        $jdId = 1;
        $jId = 1;

        // Account codes resolved once; the ledger stores ids, not codes.
        $coa = DB::table('acc_coa')->pluck('id', 'code');

        /*
         * Journals are derived from the documents that were seeded, not from
         * round numbers — otherwise the trial balance would disagree with the
         * invoice list and the balance sheet would not tie out.
         */

        // Purchases: inventory and input VAT against the payable.
        foreach (DB::table('prc_inv_main')->orderBy('id')->get() as $inv) {
            $journalMains[] = [
                'id' => $jId,
                'code' => sprintf('JRN-AP-%03d', $inv->id),
                'period' => $period,
                'date' => $inv->date,
                'jrn_type' => 'AP',
                'ref_type' => 'AP_INV',
                'ref_id' => $inv->id,
                'descrip' => "AP Invoice {$inv->code} — {$inv->inv_no}",
                'status' => 'POSTED',
                'user_id' => 1,
                'created_at' => $inv->date,
            ];

            // A service invoice is expensed, a material invoice goes to stock.
            $isService = (float) $inv->wht23 > 0;

            $journalDets[] = [
                'id' => $jdId++, 'main_id' => $jId,
                'coa_id' => $isService ? $coa['5400'] : $coa['1300'],
                'debit' => $inv->dpp, 'credit' => 0,
                'memo' => $isService ? 'Biaya subkontrak' : 'Persediaan bahan baku',
            ];
            $journalDets[] = ['id' => $jdId++, 'main_id' => $jId, 'coa_id' => $coa['1210'], 'debit' => $inv->vat, 'credit' => 0, 'memo' => 'PPN Masukan'];
            $journalDets[] = ['id' => $jdId++, 'main_id' => $jId, 'coa_id' => $coa['2100'], 'debit' => 0, 'credit' => $inv->total, 'memo' => 'Hutang usaha'];

            /*
             * Tax withheld at source is not paid to the vendor — it becomes a
             * liability to the tax office. Leaving it out is what makes the
             * entry short on the credit side by exactly the withheld amount.
             */
            if ($isService) {
                $journalDets[] = ['id' => $jdId++, 'main_id' => $jId, 'coa_id' => $coa['2220'], 'debit' => 0, 'credit' => $inv->wht23, 'memo' => 'Hutang PPh 23 dipotong'];
            }
            $jId++;
        }

        // Sales: receivable against revenue and output VAT.
        foreach (DB::table('sls_inv_main')->orderBy('id')->get() as $inv) {
            $journalMains[] = [
                'id' => $jId,
                'code' => sprintf('JRN-AR-%03d', $inv->id),
                'period' => $period,
                'date' => $inv->date,
                'jrn_type' => 'AR',
                'ref_type' => 'SALES_INV',
                'ref_id' => $inv->id,
                'descrip' => "Sales Invoice {$inv->code}",
                'status' => 'POSTED',
                'user_id' => 1,
                'created_at' => $inv->date,
            ];

            $journalDets[] = ['id' => $jdId++, 'main_id' => $jId, 'coa_id' => $coa['1200'], 'debit' => $inv->total, 'credit' => 0, 'memo' => 'Piutang usaha'];
            $journalDets[] = ['id' => $jdId++, 'main_id' => $jId, 'coa_id' => $coa['4100'], 'debit' => 0, 'credit' => $inv->dpp, 'memo' => 'Penjualan'];
            $journalDets[] = ['id' => $jdId++, 'main_id' => $jId, 'coa_id' => $coa['2210'], 'debit' => 0, 'credit' => $inv->vat, 'memo' => 'PPN Keluaran'];
            $jId++;
        }

        // Cost of goods sold, recognised against the finished-goods inventory.
        foreach (DB::table('cst_cogm')->orderBy('id')->get() as $cogm) {
            $journalMains[] = [
                'id' => $jId,
                'code' => sprintf('JRN-CGS-%03d', $cogm->id),
                'period' => $period,
                'date' => DemoCalendar::date(26)->toDateString(),
                'jrn_type' => 'JV',
                'ref_type' => 'COGM',
                'ref_id' => $cogm->id,
                'descrip' => 'COGM WO-2026-'.sprintf('%03d', $cogm->wo_id),
                'status' => 'POSTED',
                'user_id' => 1,
                'created_at' => DemoCalendar::date(26),
            ];

            $journalDets[] = ['id' => $jdId++, 'main_id' => $jId, 'coa_id' => $coa['5100'], 'debit' => $cogm->total, 'credit' => 0, 'memo' => 'Harga pokok penjualan'];
            $journalDets[] = ['id' => $jdId++, 'main_id' => $jId, 'coa_id' => $coa['1320'], 'debit' => 0, 'credit' => $cogm->total, 'memo' => 'Persediaan barang jadi'];
            $jId++;
        }

        // Opening capital, so the balance sheet starts from something.
        $journalMains[] = [
            'id' => $jId,
            'code' => 'JRN-OPEN-001',
            'period' => $period,
            'date' => DemoCalendar::date(1)->toDateString(),
            'jrn_type' => 'JV',
            'ref_type' => 'OPENING',
            'ref_id' => null,
            'descrip' => 'Saldo awal kas & modal disetor',
            'status' => 'POSTED',
            'user_id' => 1,
            'created_at' => DemoCalendar::date(1),
        ];
        $journalDets[] = ['id' => $jdId++, 'main_id' => $jId, 'coa_id' => $coa['1100'], 'debit' => 25_000_000_000, 'credit' => 0, 'memo' => 'Kas & bank'];
        $journalDets[] = ['id' => $jdId++, 'main_id' => $jId, 'coa_id' => $coa['3100'], 'debit' => 0, 'credit' => 25_000_000_000, 'memo' => 'Modal disetor'];
        $jId++;

        foreach (array_chunk($journalMains, 50) as $chunk) {
            foreach ($chunk as $jm) {
                SeedWriter::put('acc_journal_main', $jm);
            }
        }
        foreach (array_chunk($journalDets, 100) as $chunk) {
            foreach ($chunk as $jd) {
                SeedWriter::put('acc_journal_det', $jd);
            }
        }
    }

    private function seedApprovalsAndAuditLogs(): void
    {
        $approvals = [];
        $logs = [];
        $appId = 1;
        $logId = 1;

        // 100 Document Approvals
        for ($i = 1; $i <= 100; $i++) {
            $day = (($i - 1) % 10) + 4;
            /*
             * doc_type is the document's table name and required_role is the
             * menu an approver needs edit rights on — that is what the engine
             * matches against, so anything else would leave the row unreachable
             * from the approval inbox.
             *
             * Purchase orders run two levels; the last ten are left with level 2
             * still pending so the inbox is not empty in the demo.
             */
            $poLevel2Pending = $i > 90;

            $approvals[] = [
                'id' => $appId++,
                'doc_type' => 'prc_po_main',
                'doc_id' => $i,
                'level' => 1,
                'required_role' => 'pr',
                'status' => 'APPROVED',
                'acted_by' => 1,
                'acted_at' => DemoCalendar::date($day),
                'note' => 'Kebutuhan material sesuai MRP',
            ];

            $approvals[] = [
                'id' => $appId++,
                'doc_type' => 'prc_po_main',
                'doc_id' => $i,
                'level' => 2,
                'required_role' => 'po',
                'status' => $poLevel2Pending ? 'PENDING' : 'APPROVED',
                'acted_by' => $poLevel2Pending ? null : 1,
                'acted_at' => $poLevel2Pending ? null : DemoCalendar::date($day + 1),
                'note' => $poLevel2Pending ? null : 'Harga sesuai kontrak vendor',
            ];

            $approvals[] = [
                'id' => $appId++,
                'doc_type' => 'sls_so_main',
                'doc_id' => $i,
                'level' => 1,
                'required_role' => 'sales-orders',
                'status' => 'APPROVED',
                'acted_by' => 1,
                'acted_at' => DemoCalendar::date($day),
                'note' => 'Harga sesuai pricelist berlaku',
            ];

            $logs[] = [
                'id' => $logId++,
                'code_tr' => sprintf('PO-2026-%03d', $i),
                'date' => DemoCalendar::date($day),
                'user_id' => 1,
                'ip_user' => '127.0.0.1',
                'hostname' => 'localhost',
                'action' => 'RELEASE_PURCHASE_ORDER',
                'created_at' => DemoCalendar::date($day),
                'updated_at' => DemoCalendar::date($day),
            ];

            $logs[] = [
                'id' => $logId++,
                'code_tr' => sprintf('CUT-2026-%03d', $i),
                'date' => DemoCalendar::date($day + 8),
                'user_id' => 2,
                'ip_user' => '192.168.1.101',
                'hostname' => 'mes-terminal-01',
                'action' => 'EXECUTE_MES_CUTTING',
                'created_at' => DemoCalendar::date($day + 8),
                'updated_at' => DemoCalendar::date($day + 8),
            ];
        }

        foreach (array_chunk($approvals, 50) as $chunk) {
            foreach ($chunk as $app) {
                SeedWriter::put('approvals', $app);
            }
        }
        foreach (array_chunk($logs, 50) as $chunk) {
            foreach ($chunk as $lg) {
                SeedWriter::put('log_prc', $lg);
            }
        }
    }
}
