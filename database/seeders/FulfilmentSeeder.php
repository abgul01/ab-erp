<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FulfilmentSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Menyemai Pengiriman & Penjualan (100+ DO, Outgoing FG, Sales Invoice & e-Faktur)…');

        $this->seedDeliveryOrders();
        $this->seedOutgoingFg();
        $this->seedSalesInvoices();
        // The paperwork that travels with the goods, on deliveries that exist.
        $this->seedPackingAndShipping();

        $this->command?->info('  Data Pengiriman & Penjualan (100+ DO & Invoices) disemai.');
    }

    private function seedDeliveryOrders(): void
    {
        $doMains = [];
        $doDetails = [];
        $dodId = 1;

        for ($i = 1; $i <= 100; $i++) {
            $day = (($i - 1) % 10) + 20;
            $cusId = 60 + (($i % 50) + 1);
            $fgId = 70 + (($i % 40) + 1);

            /*
             * You can only ship what was actually received into finished goods.
             * The lots that lost pieces at final inspection hold less than the
             * work order planned, and shipping the planned figure anyway drove
             * FG stock negative — which then reappeared in MRP as phantom supply.
             */
            $shipQty = $this->lotQty($i);

            $doMains[] = [
                'id' => $i,
                'code' => sprintf('DO-2026-%03d', $i),
                'so_id' => $i,
                'cus_id' => $cusId,
                'date' => DemoCalendar::date($day)->toDateString(),
                /*
                 * Kosakata status DO adalah DRAFT → SHIPPED → RECEIVED. Seeder
                 * ini sempat menulis "DELIVERED" yang tidak pernah dihasilkan
                 * maupun diterima kode mana pun, sehingga seratus DO demo
                 * terkunci: tidak bisa dikirim (bukan DRAFT), tidak bisa
                 * ditandai diterima (bukan SHIPPED), tidak bisa dibatalkan.
                 */
                'status' => 'RECEIVED',
                'user_id' => 1,
                'created_at' => DemoCalendar::date($day),
                'updated_at' => DemoCalendar::date($day),
            ];

            $doDetails[] = [
                'id' => $dodId++,
                'main_id' => $i,
                'so_detail_id' => ($i * 2) - 1,
                'item_id' => $fgId,
                'qty' => $shipQty,
                'fg_code' => sprintf('LOT-FG-%03d', $i),
            ];
        }

        foreach (array_chunk($doMains, 50) as $chunk) {
            foreach ($chunk as $dom) {
                SeedWriter::put('sls_do_main', $dom);
            }
        }
        foreach (array_chunk($doDetails, 50) as $chunk) {
            foreach ($chunk as $dod) {
                SeedWriter::put('sls_do_detail', $dod);
            }
        }
    }

    /**
     * Packing lists and shipping orders for the first thirty deliveries.
     *
     * Each delivery is split into two boxes — the realistic case, and the one
     * that proves the boxes have to add up to the delivery — and every three
     * deliveries share a truck, because that is what actually happens: one
     * vehicle, one trip, several customers on the route.
     */
    private function seedPackingAndShipping(): void
    {
        $packDetId = 1;
        $shipDetId = 1;
        $shipId = 0;

        for ($i = 1; $i <= 30; $i++) {
            $line = DB::table('sls_do_detail')->where('main_id', $i)->first();
            if (! $line || (int) $line->qty < 2) {
                continue;
            }

            $day = (($i - 1) % 10) + 20;
            $at = DemoCalendar::date($day);
            $unitWeight = (float) (DB::table('m_item')->where('id', $line->item_id)->value('weight') ?? 0);

            // Two boxes: the second carries whatever the first left over, so the
            // pair always sums to exactly what is being delivered.
            $first = intdiv((int) $line->qty, 2);
            $boxes = [$first, (int) $line->qty - $first];

            SeedWriter::put('sls_pack_main', [
                'id' => $i,
                'code' => sprintf('PL/2026/07/%05d', $i),
                'date' => $at->toDateString(),
                'do_id' => $i,
                'status' => 'FINAL',
                'note' => 'Dikemas dalam peti kayu, disegel.',
                'user_id' => 1,
            ], $at);

            foreach ($boxes as $n => $qty) {
                $net = round($qty * $unitWeight, 2);
                SeedWriter::put('sls_pack_det', [
                    'id' => $packDetId++,
                    'main_id' => $i,
                    'box_no' => sprintf('BOX-%03d-%d', $i, $n + 1),
                    'do_detail_id' => $line->id,
                    'item_id' => $line->item_id,
                    'qty' => $qty,
                    'net_weight' => $net,
                    // Crate and strapping: the difference a customer weighs on arrival.
                    'gross_weight' => round($net + 12.5, 2),
                    'dimension' => '1200x800x600',
                ]);
            }

            // A new truck every third delivery.
            if (($i - 1) % 3 === 0) {
                $shipId++;
                SeedWriter::put('sls_ship_main', [
                    'id' => $shipId,
                    'code' => sprintf('SO-SHIP/2026/07/%05d', $shipId),
                    'date' => $at->toDateString(),
                    'carrier_id' => (($shipId % 45) + 1),
                    'vehicle_no' => sprintf('B %04d %s', 1000 + $shipId, ['XYZ', 'ABC', 'JKL', 'PQR'][$shipId % 4]),
                    'driver' => ['Sutrisno', 'Bambang', 'Slamet', 'Hendra', 'Wawan'][$shipId % 5],
                    'driver_phone' => sprintf('0812-3456-%04d', 1000 + $shipId),
                    'destination' => ['Karawang', 'Cikarang', 'Bekasi', 'Purwakarta', 'Sunter'][$shipId % 5],
                    'plan_depart' => $at->copy()->setTime(7, 0),
                    'departed_at' => $at->copy()->setTime(7, 25),
                    'arrived_at' => $at->copy()->setTime(11, 40),
                    'status' => 'DELIVERED',
                    'user_id' => 1,
                ], $at);
            }

            SeedWriter::put('sls_ship_det', [
                'id' => $shipDetId++,
                'main_id' => $shipId,
                'do_id' => $i,
            ]);
        }

        $this->command?->info('  Dokumen pengiriman: '.($packDetId - 1)." kotak pada 30 packing list, {$shipId} shipping order.");
    }

    /**
     * How much that finished-goods lot actually holds.
     *
     * Delivery, the warehouse issue and the invoice all read this rather than
     * the planned figure: lots that lost pieces at final inspection hold less
     * than the work order asked for, and shipping the planned quantity anyway
     * drove FG stock negative — which then showed up in MRP as supply that did
     * not exist.
     */
    private function lotQty(int $lotNo): int
    {
        return (int) DB::table('tr_inc_fg_det')
            ->where('code', sprintf('LOT-FG-%03d', $lotNo))
            ->value('qty');
    }

    private function seedOutgoingFg(): void
    {
        $outFgMains = [];
        $outFgDets = [];
        $ofdId = 1;

        for ($i = 1; $i <= 100; $i++) {
            $day = (($i - 1) % 10) + 20;
            $fgId = 70 + (($i % 40) + 1);
            $shipQty = $this->lotQty($i);

            $outFgMains[] = [
                'id' => $i,
                'code' => sprintf('OUT-FG-%03d', $i),
                'code_do' => sprintf('DO-2026-%03d', $i),
                'date' => DemoCalendar::date($day)->toDateString(),
                'user_id' => 1,
                'created_at' => DemoCalendar::date($day),
            ];

            $outFgDets[] = [
                'id' => $ofdId++,
                'main_id' => $i,
                'item_id' => $fgId,
                'fg_code' => sprintf('LOT-FG-%03d', $i),
                'code' => sprintf('OUT-FG-%03d-A', $i),
                'qty' => $shipQty,
                'created_at' => DemoCalendar::date($day),
            ];
        }

        foreach (array_chunk($outFgMains, 50) as $chunk) {
            foreach ($chunk as $ofm) {
                SeedWriter::put('tr_out_fg_main', $ofm);
            }
        }
        foreach (array_chunk($outFgDets, 50) as $chunk) {
            foreach ($chunk as $ofd) {
                SeedWriter::put('tr_out_fg_det', $ofd);
            }
        }
    }

    private function seedSalesInvoices(): void
    {
        $slsInvs = [];
        $slsInvDets = [];
        $sidId = 1;

        for ($i = 1; $i <= 100; $i++) {
            $day = (($i - 1) % 10) + 21;
            $cusId = 60 + (($i % 50) + 1);
            $fgId = 70 + (($i % 40) + 1);
            // Billed for what was delivered, not for what was ordered.
            $qty = $this->lotQty($i);
            $price = 45000.00 + (($i % 40) * 1000.00);
            $dpp = $qty * $price;
            $dppNilaiLain = round($dpp * 0.916667, 2);
            $vat = round($dppNilaiLain * 0.12, 2);
            $total = $dpp + $vat;

            $slsInvs[] = [
                'id' => $i,
                'code' => sprintf('INV-SLS-2026-%03d', $i),
                'do_id' => $i,
                'cus_id' => $cusId,
                'date' => DemoCalendar::date($day)->toDateString(),
                'due_date' => DemoCalendar::date($day + 30)->toDateString(),
                'dpp' => $dpp,
                'dpp_nilai_lain' => $dppNilaiLain,
                'vat' => $vat,
                'total' => $total,
                'tax_inv_no' => sprintf('010.000-26.%08d', 5000 + $i),
                'tax_inv_date' => DemoCalendar::date($day)->toDateString(),
                'status' => 'APPROVED',
                'user_id' => 1,
                'created_at' => DemoCalendar::date($day),
                'updated_at' => DemoCalendar::date($day),
            ];

            // Billed against the delivery line, so the invoice can always be
            // traced back to what physically left the warehouse.
            $slsInvDets[] = [
                'id' => $sidId++,
                'main_id' => $i,
                'do_detail_id' => $i,
                'item_id' => $fgId,
                'qty' => $qty,
                'price' => $price,
                'amount' => $dpp,
            ];
        }

        foreach (array_chunk($slsInvs, 50) as $chunk) {
            foreach ($chunk as $si) {
                SeedWriter::put('sls_inv_main', $si);
            }
        }
        foreach (array_chunk($slsInvDets, 50) as $chunk) {
            foreach ($chunk as $sid) {
                SeedWriter::put('sls_inv_detail', $sid);
            }
        }
    }
}
