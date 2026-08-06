<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Menyemai Produksi (100+ Transaksi MES Cutting, Processing, Subcont, FCS, RFG)…');

        $this->seedBookings();
        $this->seedKanbanAndOutward();
        $this->seedMesCutting();
        $this->seedScrapDecisions();
        $this->seedMesProcessing();
        $this->seedSubcontract();
        $this->seedSubcontInvoices();
        $this->seedFcs();
        $this->seedIncFg();

        $this->command?->info('  Data Produksi (100+ MES & RFG) disemai.');
    }

    private function seedBookings(): void
    {
        $woSerialRm = [];
        $woSerialPm = [];
        $wsrId = 1;
        $wspId = 1;

        for ($i = 1; $i <= 100; $i++) {
            $day = (($i - 1) % 10) + 10;
            $rmId = (($i % 40) + 1);
            $pmId = 40 + (($i % 30) + 1);

            $woSerialRm[] = [
                'id' => $wsrId++,
                'detail_id' => $i,
                'serial_id' => sprintf('STK%02d-2607-%05d', $rmId, ($i * 5) + 1),
                'length_asal' => 6000.00,
                'length_book' => 6000.00,
                'qty_per_serial' => 40,
                'length_rem' => 0.00,
                'qty_serial' => 40,
                'scrap' => 0,
                'created_at' => DemoCalendar::date($day),
            ];

            $woSerialPm[] = [
                'id' => $wspId++,
                'detail_id' => $i,
                'serial_id' => sprintf('PM-COMP-%03d', $pmId),
                'qty' => 1000,
                'created_at' => DemoCalendar::date($day),
            ];
        }

        foreach (array_chunk($woSerialRm, 50) as $chunk) {
            foreach ($chunk as $wsr) {
                SeedWriter::put('prd_wo_serial_rm', $wsr);
            }
        }
        foreach (array_chunk($woSerialPm, 50) as $chunk) {
            foreach ($chunk as $wsp) {
                SeedWriter::put('prd_wo_serial_pm', $wsp);
            }
        }
    }

    private function seedKanbanAndOutward(): void
    {
        $kanbans = [];
        $whOutMains = [];
        $whOutDetails = [];
        $wodId = 1;

        for ($i = 1; $i <= 100; $i++) {
            $day = (($i - 1) % 10) + 11;
            $cusId = 60 + (($i % 50) + 1);
            $rmId = (($i % 40) + 1);

            $kanbans[] = [
                'id' => $i,
                'code' => sprintf('KB-2026-%03d', $i),
                'wo_id' => $i,
                'item_id' => $rmId,
                'rm_detail_id' => $i,
                'qty_planned' => 500 + ($i * 10),
                'qty_issued' => 500 + ($i * 10),   // fully drawn against the WO
                'status' => 'ISSUED',
                'created_by' => 1,
                'issued_by' => 2,
                'issued_at' => DemoCalendar::date($day),
                'created_at' => DemoCalendar::date($day),
            ];

            $whOutMains[] = [
                'id' => $i,
                'code' => sprintf('OUT-2026-%03d', $i),
                'wo_id' => $i,
                'item_id' => $rmId,
                'user_id' => 1,
                'date' => DemoCalendar::date($day)->toDateString(),
                'cus_id' => $cusId,
                'shift_id' => 1,
                'created_at' => DemoCalendar::date($day),
            ];

            $whOutDetails[] = [
                'id' => $wodId++,
                'id_prim' => $i,
                'serial_id' => sprintf('STK%02d-2607-%05d', $rmId, ($i * 5) + 1),
                'qty' => 1,
                'pm' => 0,
                'length_serial' => 6000.00,
                'length_used' => 6000.00,
                'length_rem' => 0.00,
                'rem_data' => 0,
                'created_at' => DemoCalendar::date($day),
            ];
        }

        foreach (array_chunk($kanbans, 50) as $chunk) {
            foreach ($chunk as $kb) {
                SeedWriter::put('prd_kanban', $kb);
            }
        }
        foreach (array_chunk($whOutMains, 50) as $chunk) {
            foreach ($chunk as $wom) {
                SeedWriter::put('wh_out_main', $wom);
            }
        }
        foreach (array_chunk($whOutDetails, 50) as $chunk) {
            foreach ($chunk as $wod) {
                SeedWriter::put('wh_out_detail', $wod);
            }
        }
    }

    private function seedMesCutting(): void
    {
        $wipRecords = [];
        $trCutMains = [];
        $trCutDets = [];
        $trCutSerials = [];
        $trCutPalPrs = [];
        $tcdId = 1;
        $tcsId = 1;
        $tcpId = 1;

        for ($i = 1; $i <= 100; $i++) {
            $day = (($i - 1) % 10) + 12;
            $fgId = 70 + (($i % 40) + 1);
            $rmId = (($i % 40) + 1);

            $wipRecords[] = [
                'id' => $i,
                'code' => sprintf('WIP-2026-%03d', $i),
                'wo_id' => $i,
                'item_id' => $fgId,
                'created_at' => DemoCalendar::date($day),
            ];

            $trCutMains[] = [
                'id' => $i,
                'code' => sprintf('CUT-2026-%03d', $i),
                'user_id' => '2',
                'wip_id' => $i,
                'no_dp' => sprintf('WO-2026-%03d', $i),
                'item_id' => $fgId,
                'process_id' => 1,
                'date' => DemoCalendar::date($day)->toDateString(),
                'subcont' => 0,
                'repair' => 0,
                'created_at' => DemoCalendar::date($day),
            ];

            $trCutDets[] = [
                'id' => $tcdId++,
                'main_id' => $i,
                'machine_id' => (($i % 6) + 1), // Cutting machines 1..6
                'start_time' => '08:00:00',
                'end_time' => '12:00:00',
                'finish' => 1,
                'created_at' => DemoCalendar::date($day),
            ];

            $trCutSerials[] = [
                'id' => $tcsId++,
                'detail_id' => $tcdId - 1,
                'serial_id' => sprintf('STK%02d-2607-%05d', $rmId, ($i * 5) + 1),
                'qty' => 40,
                'length_rem' => 120.00,
                'finish' => 1,
                'created_at' => DemoCalendar::date($day),
            ];

            $trCutPalPrs[] = [
                'id' => $tcpId++,
                'code' => sprintf('PAL-CUT-%03d', $i),
                'cut_id' => $i,
                'qty' => 500 + ($i * 10),
                'process_id' => 1,
                'created_at' => DemoCalendar::date($day),
            ];
        }

        foreach (array_chunk($wipRecords, 50) as $chunk) {
            foreach ($chunk as $wip) {
                SeedWriter::put('prd_wip', $wip);
            }
        }
        foreach (array_chunk($trCutMains, 50) as $chunk) {
            foreach ($chunk as $tcm) {
                SeedWriter::put('tr_cut_main', $tcm);
            }
        }
        foreach (array_chunk($trCutDets, 50) as $chunk) {
            foreach ($chunk as $tcd) {
                SeedWriter::put('tr_cut_detail', $tcd);
            }
        }
        foreach (array_chunk($trCutSerials, 50) as $chunk) {
            foreach ($chunk as $tcs) {
                SeedWriter::put('tr_cut_serial', $tcs);
            }
        }
        foreach (array_chunk($trCutPalPrs, 50) as $chunk) {
            foreach ($chunk as $tcp) {
                SeedWriter::put('tr_cut_pal_pr', $tcp);
            }
        }
    }

    private function seedScrapDecisions(): void
    {
        /*
         * Leftovers that were judged after cutting. Most are scrapped because
         * the offcut is shorter than the smallest length any BOM asks for, but
         * a handful were overruled and kept — the reason is recorded either way,
         * which is what the Scrap RM screen is there to show.
         */
        $scraps = [];
        for ($i = 1; $i <= 50; $i++) {
            $rmId = (($i % 40) + 1);
            $keepIt = ($i % 7 === 0);
            $remaining = $keepIt ? 480.00 : 120.00;

            $scraps[] = [
                'id' => $i,
                'serial_id' => sprintf('STK%02d-2607-%05d', $rmId, ($i * 5) + 1),
                'wo_serial_rm_id' => $i,
                'wo_id' => $i,
                'item_id' => $rmId,
                'length_rem' => $remaining,
                'min_bom_length' => 500.00,
                'decision' => $keepIt ? 'USABLE' : 'SCRAP',
                'reason' => $keepIt
                    ? 'Disisihkan untuk sampel uji tarik walau di bawah panjang minimum BOM'
                    : sprintf('Sisa %d mm di bawah potongan terpendek BOM (500 mm)', $remaining),
                // The rule proposed SCRAP in both cases; the second was overruled.
                'auto_flag' => 1,
                'decided_by' => 1,
                'created_at' => DemoCalendar::date(12),
            ];
        }

        foreach (array_chunk($scraps, 50) as $chunk) {
            foreach ($chunk as $sc) {
                SeedWriter::put('prd_scrap_decisions', $sc);
            }
        }
    }

    private function seedMesProcessing(): void
    {
        $trProMains = [];
        $trProDets = [];
        $trProPallets = [];
        $tpdId = 1;
        $tppId = 1;

        for ($i = 1; $i <= 100; $i++) {
            $day = (($i - 1) % 10) + 13;
            $fgId = 70 + (($i % 40) + 1);

            $trProMains[] = [
                'id' => $i,
                'code' => sprintf('PRO-2026-%03d', $i),
                'wip_id' => $i,
                'cut_id' => $i,
                'item_id' => $fgId,
                'user_id' => '2',
                'process_id' => 2,
                // The pallet scanned in is the one cutting produced — that link
                // is what carries traceability from finished goods back to bar.
                'pallet_code' => sprintf('PAL-CUT-%03d', $i),
                'no_dp' => sprintf('WO-2026-%03d', $i),
                'date' => DemoCalendar::date($day)->toDateString(),
                'shift_id' => (($i % 3) + 1),
                'start_time' => '13:00:00',
                'end_time' => '17:00:00',
                'finish' => 1,
                'qty_half' => 0,
                'qty_full' => 500 + ($i * 10),
                'sq_process' => 2,
                'subcont' => 0,
                'repair' => 0,
                'created_at' => DemoCalendar::date($day),
            ];

            $trProDets[] = [
                'id' => $tpdId++,
                'main_id' => $i,
                'machine_id' => (7 + ($i % 8)), // CNC Lathe machines 7..14
                'user_id' => 2,
                'qty_half' => 0,
                'qty_full' => 500 + ($i * 10),
                'finish' => 1,
                'start_time' => '13:00:00',
                'end_time' => '17:00:00',
                'created_at' => DemoCalendar::date($day),
            ];

            $trProPallets[] = [
                'id' => $tppId++,
                'detail_id' => $tpdId - 1,
                'cut_id' => $i,
                'pallet_code' => sprintf('PAL-PRO-%03d', $i),
                'qty_half' => 0,
                'qty_full' => 500 + ($i * 10),
                'finish' => 1,
                'created_at' => DemoCalendar::date($day),
            ];
        }

        foreach (array_chunk($trProMains, 50) as $chunk) {
            foreach ($chunk as $tpm) {
                SeedWriter::put('tr_pro_main', $tpm);
            }
        }
        foreach (array_chunk($trProDets, 50) as $chunk) {
            foreach ($chunk as $tpd) {
                SeedWriter::put('tr_pro_detail', $tpd);
            }
        }
        foreach (array_chunk($trProPallets, 50) as $chunk) {
            foreach ($chunk as $tpp) {
                SeedWriter::put('tr_pro_pallet', $tpp);
            }
        }
    }

    private function seedSubcontract(): void
    {
        $subPos = [];
        $subPoDets = [];
        $subDns = [];
        $subDnDets = [];
        $subGrs = [];

        // 50 Subcontract Orders
        for ($i = 1; $i <= 50; $i++) {
            $woId = $i * 2;
            $fgId = 70 + (($woId % 40) + 1);
            $day = 14;

            $venId = 45 + ($i % 15) + 1;   // subcontractors occupy ids 46..60

            $subPos[] = [
                'id' => $i,
                'code' => sprintf('SUBC-PO-%03d', $i),
                'date' => DemoCalendar::date($day)->toDateString(),
                'ven_id' => $venId,
                'user_id' => 1,
                'note' => sprintf('Heat treatment untuk WO-2026-%03d', $woId),
                'status' => 'APPROVED',
                'created_at' => DemoCalendar::date($day),
            ];

            // What the subcontractor is being paid to do, on which product.
            $subPoDets[] = [
                'id' => $i,
                'main_id' => $i,
                'item_id' => $fgId,
                'process_id' => 4,               // outside heat treatment
                'qty' => 500,
                'price' => 7500.00,
            ];

            $subDns[] = [
                'id' => $i,
                'code' => sprintf('SUBC-DN-%03d', $i),
                'po_id' => $i,
                'ven_id' => $venId,
                'user_id' => 1,
                'date' => DemoCalendar::date($day)->toDateString(),
                'status' => 'SENT',
                'created_at' => DemoCalendar::date($day),
            ];

            // The pallet physically handed over — stock stays ours while it sits
            // at the subcontractor, which is why the line names the WO and pallet.
            $subDnDets[] = [
                'id' => $i,
                'main_id' => $i,
                'wo_id' => $woId,
                'item_id' => $fgId,
                'serial_id' => null,
                'pallet_code' => sprintf('PAL-PRO-%03d', $woId),
                'qty' => 500,
            ];

            $subGrs[] = [
                'id' => $i,
                'code' => sprintf('SUBC-GR-%03d', $i),
                'po_id' => $i,
                'dn_id' => $i,
                'ven_dn_no' => sprintf('SJ-VEND-%05d', 40000 + $i),
                'user_id' => 1,
                'date' => DemoCalendar::date($day + 3)->toDateString(),
                // A couple of pieces come back out of spec — the demo should not
                // pretend outside processing is always perfect.
                'qty_ok' => $i % 9 === 0 ? 496 : 500,
                'qty_ng' => $i % 9 === 0 ? 4 : 0,
                'status' => 'RECEIVED',
                'created_at' => DemoCalendar::date($day + 3),
            ];
        }

        foreach (array_chunk($subPos, 50) as $chunk) {
            foreach ($chunk as $sp) {
                SeedWriter::put('sub_po_main', $sp);
            }
        }
        foreach (array_chunk($subPoDets, 50) as $chunk) {
            foreach ($chunk as $spd) {
                SeedWriter::put('sub_po_detail', $spd);
            }
        }
        foreach (array_chunk($subDns, 50) as $chunk) {
            foreach ($chunk as $sd) {
                SeedWriter::put('sub_dn_main', $sd);
            }
        }
        foreach (array_chunk($subDnDets, 50) as $chunk) {
            foreach ($chunk as $sdd) {
                SeedWriter::put('sub_dn_detail', $sdd);
            }
        }
        foreach (array_chunk($subGrs, 50) as $chunk) {
            foreach ($chunk as $sg) {
                SeedWriter::put('sub_gr_main', $sg);
            }
        }
    }

    /**
     * Supplier invoices for the outside processing.
     *
     * Subcontracting is a service, so PPh 23 is withheld at 2% — the plant pays
     * the vendor the net and owes the difference to the tax office. These are
     * the invoices the e-Bupot export reports; without them the withholding
     * report would have nothing in it.
     */
    private function seedSubcontInvoices(): void
    {
        $invoices = [];
        $lines = [];

        for ($i = 1; $i <= 50; $i++) {
            $id = 100 + $i;                       // AP ids 1..100 are the material buys
            $venId = 45 + ($i % 15) + 1;
            $day = 17;

            $dpp = 3_750_000.00;                  // 500 pcs × Rp 7.500 jasa
            $vat = round($dpp * 0.916667 * 0.12, 2);
            $wht23 = round($dpp * 0.02, 2);

            $invoices[] = [
                'id' => $id,
                'code' => sprintf('API-SUB-%03d', $i),
                'date' => DemoCalendar::date($day)->toDateString(),
                'ven_id' => $venId,
                'po_id' => null,
                'inv_no' => sprintf('INV-JASA-%04d', 3000 + $i),
                'dpp' => $dpp,
                'vat' => $vat,
                'wht23' => $wht23,
                'wht23_code' => '24-104-27',       // jasa lain
                'wht23_rate' => 2.00,
                // Payable is the gross less the tax withheld at source.
                'total' => $dpp + $vat - $wht23,
                'tax_inv_no' => sprintf('010.000-26.%08d', 9000 + $i),
                'tax_inv_date' => DemoCalendar::date($day)->toDateString(),
                'due_date' => DemoCalendar::date($day + 30)->toDateString(),
                'user_id' => 1,
                'status' => 'POSTED',
                'created_at' => DemoCalendar::date($day),
            ];

            $lines[] = [
                'id' => $id,
                'main_id' => $id,
                'gr_detail_id' => null,
                'qty' => 500,
                'price' => 7500.00,
                'amount' => $dpp,
            ];
        }

        foreach ($invoices as $inv) {
            SeedWriter::put('prc_inv_main', $inv);
        }
        foreach ($lines as $line) {
            SeedWriter::put('prc_inv_detail', $line);
        }
    }

    private function seedFcs(): void
    {
        $fcsRecords = [];
        for ($i = 1; $i <= 100; $i++) {
            $day = (($i - 1) % 10) + 18;
            $rmId = (($i % 40) + 1);

            $planned = 500 + ($i * 10);
            // A small fraction fails final inspection, as it would on a real line.
            $good = $i % 11 === 0 ? $planned - 12 : $planned;

            $fcsRecords[] = [
                'id' => $i,
                'wo_id' => $i,
                'fg_item_id' => 70 + (($i % 40) + 1),
                'qty_planned' => $planned,
                'qty_good' => $good,
                /*
                 * The traceability snapshot is frozen at approval: which bar the
                 * product came from and which operations it passed. Recomputing
                 * it later would be worthless — the underlying rows move on.
                 */
                'traceability' => json_encode([
                    'wo' => sprintf('WO-2026-%03d', $i),
                    'rm_serials' => [sprintf('STK%02d-2607-%05d', $rmId, ($i * 5) + 1)],
                    'pallets' => [sprintf('PAL-CUT-%03d', $i), sprintf('PAL-PRO-%03d', $i)],
                    'operations' => ['CUT', 'MACHINING', 'CHAMFER', 'PACKING'],
                ]),
                'notes' => $good < $planned ? 'Sebagian ditolak: chamfer di luar toleransi' : null,
                'status' => 'APPROVED',
                'created_by' => 1,
                'approved_by' => 1,
                'approved_at' => DemoCalendar::date($day),
                'created_at' => DemoCalendar::date($day),
            ];
        }

        foreach (array_chunk($fcsRecords, 50) as $chunk) {
            foreach ($chunk as $fcs) {
                SeedWriter::put('prd_fcs_main', $fcs);
            }
        }
    }

    private function seedIncFg(): void
    {
        $incFgMains = [];
        $incFgDets = [];
        $ifdId = 1;

        for ($i = 1; $i <= 100; $i++) {
            $day = (($i - 1) % 10) + 19;
            $fgId = 70 + (($i % 40) + 1);

            $incFgMains[] = [
                'id' => $i,
                'code' => sprintf('RFG-2026-%03d', $i),
                'date' => DemoCalendar::date($day)->toDateString(),
                'user_id' => 1,
                'created_at' => DemoCalendar::date($day),
            ];

            $incFgDets[] = [
                'id' => $ifdId++,
                'main_id' => $i,
                'code' => sprintf('LOT-FG-%03d', $i),
                'pal_pro_code' => sprintf('PAL-PRO-%03d', $i),
                'item_id' => $fgId,
                'cut_id' => $i,
                // Quantity received is what passed final inspection, not what
                // was planned — the two differ on the lots that had rejects.
                'qty' => ($i % 11 === 0) ? (500 + ($i * 10)) - 12 : 500 + ($i * 10),
                'unit_cost' => 32500.00 + (($i % 40) * 250),
                'source' => 'PRODUCTION',
                'status' => 'ACTIVE',
                'wip_id' => $i,
                'created_at' => DemoCalendar::date($day),
            ];
        }

        foreach (array_chunk($incFgMains, 50) as $chunk) {
            foreach ($chunk as $ifm) {
                SeedWriter::put('tr_inc_fg_main', $ifm);
            }
        }
        foreach (array_chunk($incFgDets, 50) as $chunk) {
            foreach ($chunk as $ifd) {
                SeedWriter::put('tr_inc_fg_det', $ifd);
            }
        }
    }
}
