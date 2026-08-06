<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProcurementSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Menyemai Procurement, GRN, Serial Tracking & Landed Cost (100+ Transaksi)…');

        $this->seedPrs();
        $this->seedPos();
        $this->seedQuotaTxns();
        $this->seedGrnsAndSerials();
        $this->seedQasInspections();
        $this->seedPutaway();
        $this->seedApInvoices();
        $this->seedLandedCosts();

        $this->command?->info('  Procurement & Serial Tracking (100+ PO & 100+ GRN) disemai.');
    }

    private function seedPrs(): void
    {
        $prs = [];
        $prDets = [];

        for ($i = 1; $i <= 100; $i++) {
            $day = (($i - 1) % 10) + 3;
            $prs[] = [
                'id' => $i,
                'code' => sprintf('PR-2026-%03d', $i),
                'date' => DemoCalendar::date($day)->toDateString(),
                'pr_type' => ($i % 2 === 0) ? 'RM' : 'PM',
                'user_id' => 1,
                'status' => 'APPROVED',
                'created_at' => DemoCalendar::date($day),
                'updated_at' => DemoCalendar::date($day),
            ];

            $rmId = (($i % 40) + 1);
            $prDets[] = [
                'id' => $i,
                'main_id' => $i,
                'item_id' => $rmId,
                'qty' => 50 + ($i * 5),
                'uom_id' => 1,
                // Each requisition is raised against the Work Order that needs
                // the material, which is what makes MRP → PR traceable.
                'wo_id' => $i,
                'need_date' => DemoCalendar::date($day + 7)->toDateString(),
                'note' => sprintf('Permintaan material untuk WO-2026-%03d', $i),
            ];
        }

        foreach (array_chunk($prs, 50) as $chunk) {
            foreach ($chunk as $pr) {
                SeedWriter::put('prc_pr_main', $pr);
            }
        }
        foreach (array_chunk($prDets, 50) as $chunk) {
            foreach ($chunk as $prd) {
                SeedWriter::put('prc_pr_detail', $prd);
            }
        }
    }

    private function seedPos(): void
    {
        $poMains = [];
        $poDetails = [];
        $podId = 1;

        // 100 Purchase Orders
        for ($i = 1; $i <= 100; $i++) {
            $day = (($i - 1) % 10) + 4;
            $isImport = ($i % 2 === 0);
            $venId = $isImport ? (($i % 10) + 1) : (10 + ($i % 35));
            $quotaId = $isImport ? (($i % 5) + 1) : null;
            $currId = $isImport ? 2 : 1;
            $rate = $isImport ? 16250.00 : 1.00;

            $poMains[] = [
                'id' => $i,
                'code' => sprintf('PO-2026-%03d', $i),
                'date' => DemoCalendar::date($day)->toDateString(),
                'po_type' => 'RM',
                'source' => $isImport ? 'IMPORT' : 'LOCAL',
                'ven_id' => $venId,
                'quota_id' => $quotaId,
                'currency_id' => $currId,
                'rate' => $rate,
                'top_days' => 30,
                'user_id' => 1,
                // OPEN is the status an approved PO carries in this app; MRP
                // counts these as supply already on the way.
                'status' => 'OPEN',
                'created_at' => DemoCalendar::date($day),
                'updated_at' => DemoCalendar::date($day),
            ];

            $rmId = (($i % 40) + 1);
            $qty = 25;
            $price = $isImport ? 150.00 : 450000.00;

            $poDetails[] = [
                'id' => $podId++,
                'main_id' => $i,
                'pr_detail_id' => $i,
                'item_id' => $rmId,
                'qty' => $qty,
                'uom_id' => 1,
                'price' => $price,
                'price_kg' => $isImport ? 3.68 : 17281.00,
                'tax_id' => 1,
                'est_weight_unit' => 40.68,
                'est_length_unit' => 6000.00,
                'est_weight' => 1017.00,
                'est_length' => 150000.00,
                'due_date' => DemoCalendar::date($day + 3)->toDateString(),
                'qty_received' => $qty,
            ];
        }

        foreach (array_chunk($poMains, 50) as $chunk) {
            foreach ($chunk as $po) {
                SeedWriter::put('prc_po_main', $po);
            }
        }
        foreach (array_chunk($poDetails, 50) as $chunk) {
            foreach ($chunk as $pod) {
                SeedWriter::put('prc_po_detail', $pod);
            }
        }
    }

    private function seedQuotaTxns(): void
    {
        $quotaTxns = [];
        $qtId = 1;

        for ($i = 1; $i <= 50; $i++) {
            $quotaId = (($i % 5) + 1);
            $quotaTxns[] = [
                'id' => $qtId++,
                'quota_id' => $quotaId,
                'ref_type' => 'PO_RESERVE',
                'ref_id' => $i * 2,
                'ton' => 1.017,
                'sign' => -1,
                'note' => sprintf('Reservasi PO Impor PO-2026-%03d', $i * 2),
                'user_id' => 1,
                'created_at' => DemoCalendar::date(5),
            ];

            $quotaTxns[] = [
                'id' => $qtId++,
                'quota_id' => $quotaId,
                'ref_type' => 'GR_ACTUAL',
                'ref_id' => $i * 2,
                'ton' => 1.017,
                'sign' => -1,
                'note' => sprintf('Realisasi Kedatangan GRN-2026-%03d', $i * 2),
                'user_id' => 1,
                'created_at' => DemoCalendar::date(8),
            ];
        }

        foreach (array_chunk($quotaTxns, 50) as $chunk) {
            foreach ($chunk as $qt) {
                SeedWriter::put('prc_quota_txn', $qt);
            }
        }
    }

    private function seedGrnsAndSerials(): void
    {
        $grMains = [];
        $grDetails = [];
        $grSerials = [];
        $grdId = 1;
        $grsId = 1;

        // 100 Goods Receipt Notes
        for ($i = 1; $i <= 100; $i++) {
            $day = (($i - 1) % 10) + 7;
            $isImport = ($i % 2 === 0);
            $venId = $isImport ? (($i % 10) + 1) : (10 + ($i % 35));
            $rmId = (($i % 40) + 1);

            $grMains[] = [
                'id' => $i,
                'code' => sprintf('GRN-2026-%03d', $i),
                'ven_id' => $venId,
                'date' => DemoCalendar::date($day)->toDateString(),
                'user_id' => 1,
                'po_no' => sprintf('PO-2026-%03d', $i),
                'import_doc_no' => $isImport ? sprintf('PIB-%06d', 800000 + $i) : null,
                'status' => 'CONFIRMED',
                'created_at' => DemoCalendar::date($day),
                'updated_at' => DemoCalendar::date($day),
            ];

            $grDetails[] = [
                'id' => $grdId++,
                'id_prim' => $i,
                'po_id' => $i,
                'item_id' => $rmId,
                'quota_id' => $isImport ? (($i % 5) + 1) : null,
                'hs_code' => $isImport ? '7304.31.00' : null,
                'qty' => 5, // 5 serials per GRN (Total 500 serials)
                'length' => 6000,
                'weight' => 40.68,
                'w_total' => 203.40,
                'note' => 'OK Timbang & Inspeksi',
                'created_at' => DemoCalendar::date($day),
            ];

            // 5 individual serials per GRN
            for ($s = 1; $s <= 5; $s++) {
                $grSerials[] = [
                    'id' => $grsId++,
                    'det_id' => $grdId - 1,
                    'serial_id' => sprintf('STK%02d-2607-%05d', $rmId, ($i * 10) + $s),
                    'millsheet' => sprintf('MS-MILL-%05d', 40000 + $i),
                    'qty' => 1,
                    'length' => 6000,
                    'weight' => 40.68,
                    'status' => 'OK',
                    'ng_reason' => null,
                    'created_at' => DemoCalendar::date($day),
                    'updated_at' => DemoCalendar::date($day),
                ];
            }
        }

        foreach (array_chunk($grMains, 50) as $chunk) {
            foreach ($chunk as $gr) {
                SeedWriter::put('prc_gr_main', $gr);
            }
        }
        foreach (array_chunk($grDetails, 50) as $chunk) {
            foreach ($chunk as $grd) {
                SeedWriter::put('prc_gr_detail', $grd);
            }
        }
        foreach (array_chunk($grSerials, 100) as $chunk) {
            foreach ($chunk as $grs) {
                SeedWriter::put('prc_gr_serial', $grs);
            }
        }
    }

    private function seedQasInspections(): void
    {
        $qasMains = [];
        $qasDets = [];
        $qdId = 1;

        for ($i = 1; $i <= 100; $i++) {
            $day = (($i - 1) % 10) + 8;
            $qasMains[] = [
                'id' => $i,
                'code' => sprintf('QC-2026-%03d', $i),
                'date' => DemoCalendar::date($day)->toDateString(),
                'gr_id' => $i,
                'inspector_id' => 1,
                'result' => 'PASS',
                'status' => 'CONFIRMED',
                'created_at' => DemoCalendar::date($day),
                'updated_at' => DemoCalendar::date($day),
            ];

            /*
             * The readings behind the verdict. Recording the standard alongside
             * the actual is what makes an inspection re-checkable later —
             * "PASS" on its own proves nothing.
             */
            foreach ([
                ['OD', '60.30 ± 0.30', number_format(60.30 + (($i % 5) - 2) * 0.05, 2, '.', '')],
                ['THK', '3.00 ± 0.20', number_format(3.00 + (($i % 3) - 1) * 0.05, 2, '.', '')],
                ['LEN', '6000 ± 10', (string) (6000 + (($i % 7) - 3))],
            ] as [$param, $standard, $actual]) {
                $qasDets[] = [
                    'id' => $qdId++,
                    'main_id' => $i,
                    'param' => $param,
                    'standard' => $standard,
                    'actual' => $actual,
                    'judge' => 'OK',
                ];
            }
        }

        foreach (array_chunk($qasMains, 50) as $chunk) {
            foreach ($chunk as $qm) {
                SeedWriter::put('qc_incoming_main', $qm);
            }
        }
        foreach (array_chunk($qasDets, 50) as $chunk) {
            foreach ($chunk as $qd) {
                SeedWriter::put('qc_incoming_det', $qd);
            }
        }
    }

    private function seedPutaway(): void
    {
        $whIncMains = [];
        $whIncDets = [];
        $widId = 1;

        for ($i = 1; $i <= 100; $i++) {
            $day = (($i - 1) % 10) + 8;
            $rmId = (($i % 40) + 1);
            $rackId = (($i % 70) + 1);

            $whIncMains[] = [
                'id' => $i,
                'code' => sprintf('PUT-2026-%03d', $i),
                'user_id' => 1,
                'gr_id' => $i,
                'date' => DemoCalendar::date($day)->toDateString(),
                'shift_id' => 1,
                'created_at' => DemoCalendar::date($day),
            ];

            $whIncDets[] = [
                'id' => $widId++,
                'id_prim' => $i,
                'serial_id' => sprintf('STK%02d-2607-%05d', $rmId, ($i * 10) + 1),
                'length' => 6000,
                'qty' => 5,
                'item_id' => $rmId,
                'rack_id' => $rackId,
                'created_at' => DemoCalendar::date($day),
            ];
        }

        foreach (array_chunk($whIncMains, 50) as $chunk) {
            foreach ($chunk as $wim) {
                SeedWriter::put('wh_inc_main', $wim);
            }
        }
        foreach (array_chunk($whIncDets, 50) as $chunk) {
            foreach ($chunk as $wid) {
                SeedWriter::put('wh_inc_detail', $wid);
            }
        }
    }

    private function seedApInvoices(): void
    {
        $invMains = [];
        $invDets = [];
        $idId = 1;

        for ($i = 1; $i <= 100; $i++) {
            $day = (($i - 1) % 10) + 9;
            $isImport = ($i % 2 === 0);
            $venId = $isImport ? (($i % 10) + 1) : (10 + ($i % 35));
            $dpp = $isImport ? 60937500.00 : 15750000.00;
            $total = $dpp * 1.12;

            // VAT is charged on the DPP Nilai Lain (11/12 of value) per PMK 131/2024.
            $vat = round($dpp * 0.916667 * 0.12, 2);

            $invMains[] = [
                'id' => $i,
                'code' => sprintf('API-2026-%03d', $i),
                'date' => DemoCalendar::date($day)->toDateString(),
                'ven_id' => $venId,
                'po_id' => $i,
                'inv_no' => sprintf('INV-VEND-2026-%03d', $i),
                'dpp' => $dpp,
                'vat' => $vat,
                'wht23' => 0.00,
                'total' => $dpp + $vat,
                'tax_inv_no' => sprintf('010.000-26.%08d', 1000 + $i),
                'tax_inv_date' => DemoCalendar::date($day)->toDateString(),
                // 30-day terms: a third of the month's invoices fall due inside
                // the demo window, which is what gives AP ageing something to show.
                'due_date' => DemoCalendar::date($day + 30)->toDateString(),
                'user_id' => 1,
                'status' => 'POSTED',
                'created_at' => DemoCalendar::date($day),
                'updated_at' => DemoCalendar::date($day),
            ];

            // Billed against the receipt line, which is what makes the 3-way
            // match (PO ↔ GR ↔ invoice) possible.
            $invDets[] = [
                'id' => $idId++,
                'main_id' => $i,
                'gr_detail_id' => $i,
                'qty' => 25,
                'price' => $dpp / 25,
                'amount' => $dpp,
            ];
        }

        foreach (array_chunk($invMains, 50) as $chunk) {
            foreach ($chunk as $im) {
                SeedWriter::put('prc_inv_main', $im);
            }
        }
        foreach (array_chunk($invDets, 50) as $chunk) {
            foreach ($chunk as $id) {
                SeedWriter::put('prc_inv_detail', $id);
            }
        }
    }

    private function seedLandedCosts(): void
    {
        $costMains = [];
        $costDets = [];
        $costAllocs = [];
        $cdId = 1;
        $caId = 1;

        // 50 Import Landed Costs
        for ($i = 1; $i <= 50; $i++) {
            $poId = $i * 2; // Import POs
            $day = 10;

            /*
             * A landed-cost sheet hangs off the supplier invoice, not the PO:
             * the freight and duty bills arrive with the invoice, and it is the
             * invoiced weight the cost is spread over.
             *
             * PPh 22 is computed on the customs value (goods + freight + duty)
             * and stored on the header as a tax credit — it is deliberately not
             * part of the amount allocated into inventory.
             */
            $goods = 60937500.00;
            $freight = 5000000.00;
            $duty = 3000000.00;
            $pph22Base = $goods + $freight + $duty;

            $costMains[] = [
                'id' => $i,
                'code' => sprintf('LC-2026-%03d', $i),
                'date' => DemoCalendar::date($day)->toDateString(),
                'po_id' => $poId,
                'gr_id' => $poId,
                'inv_id' => $poId,
                'alloc_basis' => 'WEIGHT',
                'pph22_base' => $pph22Base,
                'pph22_rate' => 2.50,               // importer holds an API licence
                'pph22_amount' => round($pph22Base * 0.025, 2),
                'has_api' => 1,
                'status' => 'FINAL',
                'user_id' => 1,
                'created_at' => DemoCalendar::date($day),
                'updated_at' => DemoCalendar::date($day),
            ];

            foreach ([['FREIGHT', $freight, 'Ocean freight & THC'], ['DUTY', $duty, 'Bea masuk PIB'], ['EMKL', 1500000.00, 'Jasa EMKL & trucking']] as $k => [$type, $amount, $desc]) {
                $costDets[] = [
                    'id' => $cdId++,
                    'main_id' => $i,
                    'cost_type' => $type,
                    'descrip' => sprintf('%s — PO-2026-%03d', $desc, $poId),
                    'amount' => $amount,
                    'currency_id' => 1,
                    'rate' => 1.0,
                    'amount_idr' => $amount,
                ];
            }

            // Allocated onto the receipt line by weight: 9.5 juta over 203.4 kg.
            $allocated = $freight + $duty + 1500000.00;
            $costAllocs[] = [
                'id' => $caId++,
                'main_id' => $i,
                'gr_detail_id' => $poId,
                'serial_id' => null,
                'amount' => $allocated,
                'unit_cost_kg' => round($allocated / 203.40, 4),
            ];
        }

        foreach (array_chunk($costMains, 50) as $chunk) {
            foreach ($chunk as $cm) {
                SeedWriter::put('prc_cost_main', $cm);
            }
        }
        foreach (array_chunk($costDets, 50) as $chunk) {
            foreach ($chunk as $cd) {
                SeedWriter::put('prc_cost_detail', $cd);
            }
        }
        foreach (array_chunk($costAllocs, 50) as $chunk) {
            foreach ($chunk as $ca) {
                SeedWriter::put('prc_cost_alloc', $ca);
            }
        }
    }
}
