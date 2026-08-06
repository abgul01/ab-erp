<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemandSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Menyemai Sales Forecast & Sales Orders (100+ Transaksi Penjualan)…');

        $this->seedForecasts();
        $this->seedSalesOrders();

        $this->command?->info('  Demand & Sales Orders (100+ SO) disemai.');
    }

    private function seedForecasts(): void
    {
        $period = DemoCalendar::period();
        $forecasts = [];
        $fcId = 1;

        // 100 Forecast entries
        for ($i = 1; $i <= 100; $i++) {
            $cusId = 60 + (($i % 50) + 1);
            $fgId = 70 + (($i % 40) + 1);
            $qty = 500 + ($i * 20);

            $forecasts[] = [
                'id' => $fcId++,
                'cus_id' => $cusId,
                'item_id' => $fgId,
                'period' => $period,
                'qty' => $qty,
                'created_at' => DemoCalendar::date(1),
            ];
        }

        foreach (array_chunk($forecasts, 50) as $chunk) {
            foreach ($chunk as $fc) {
                SeedWriter::put('sls_forecast', $fc);
            }
        }
    }

    private function seedSalesOrders(): void
    {
        $soMains = [];
        $soDetails = [];
        $sodId = 1;

        // 100 Sales Orders
        for ($i = 1; $i <= 100; $i++) {
            $day = (($i - 1) % 10) + 1; // Days 1..10
            $cusId = 60 + (($i % 50) + 1);
            $code = sprintf('SO-2026-%03d', $i);
            $date = DemoCalendar::date($day)->toDateString();

            $soMains[] = [
                'id' => $i,
                'code' => $code,
                'cus_id' => $cusId,
                'cus_po_no' => sprintf('CUS-PO-%04d', 8000 + $i),
                'date' => $date,
                'user_id' => 1,
                'status' => 'APPROVED',
                'created_at' => DemoCalendar::date($day),
                'updated_at' => DemoCalendar::date($day),
            ];

            // 2 detail lines per SO (Total 200 details)
            for ($j = 1; $j <= 2; $j++) {
                $fgIdx = (($i + $j) % 40) + 1;
                $fgId = 70 + $fgIdx;
                $qty = 200 + ($j * 100);
                $price = 45000.00 + ($fgIdx * 1000.00);
                $gross = $qty * $price;

                /*
                 * Tax is frozen on the line, not recomputed at invoice time:
                 * PMK 131/2024 charges 12% on a taxable base of 11/12 of the
                 * contract value, so the effective rate is 11% and the figures
                 * below are what will still be reported years from now even if
                 * the tax code changes.
                 */
                $dpp = round($gross * 0.916667, 2);
                $ppnValue = round($dpp * 0.12, 2);

                $soDetails[] = [
                    'id' => $sodId++,
                    'main_id' => $i,
                    'item_id' => $fgId,
                    'qty' => $qty,
                    'price' => $price,
                    'tax_id' => 1,
                    'ppn' => 12.00,
                    'dpp' => $dpp,
                    'ppn_value' => $ppnValue,
                    'pph' => 0,
                    'pph_value' => 0,
                    'local_mat' => 1,
                    'qty_delivered' => 0,
                    'due_date' => DemoCalendar::date($day + 20)->toDateString(),
                ];
            }
        }

        foreach (array_chunk($soMains, 50) as $chunk) {
            foreach ($chunk as $so) {
                SeedWriter::put('sls_so_main', $so);
            }
        }

        foreach (array_chunk($soDetails, 50) as $chunk) {
            foreach ($chunk as $sod) {
                SeedWriter::put('sls_so_detail', $sod);
            }
        }
    }
}
