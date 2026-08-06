<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Satu bulan gudang WHS berjalan: barang dibeli, diterima, dipakai, dan alat
 * dipinjam lalu dikembalikan — sebagian dalam keadaan rusak.
 *
 * Angkanya sengaja dibuat konsisten: yang dikeluarkan tidak pernah melebihi
 * yang diterima, dan tiap unit alat yang tercatat dipinjam benar-benar berasal
 * dari satu baris pengeluaran. Demo yang stoknya minus hanya mengajari orang
 * untuk tidak mempercayai layarnya.
 */
class WhsSeeder extends Seeder
{
    /** Berapa yang diterima dan dikeluarkan per jenis barang. */
    private const RECEIVE = ['PART' => 20, 'CONSUMABLE' => 100, 'TOOL' => 4];

    private const ISSUE = ['PART' => 6, 'CONSUMABLE' => 30, 'TOOL' => 2];

    public function run(): void
    {
        $this->command?->info('Menyemai Gudang WHS (master, PO, penerimaan, pemakaian, pinjam-kembali alat)…');

        $items = $this->seedItems();
        $this->seedPos($items);
        $this->seedIncoming($items);
        $this->seedOutgoing($items);
        $this->seedReturns();

        $this->command?->info('  Gudang WHS disemai.');
    }

    /** 30 barang: 12 sparepart, 10 habis pakai, 8 alat. */
    private function seedItems(): array
    {
        $catalog = [
            ['PART', 'Bearing 6205 ZZ', 'Bearing', 'NSK', 185000, 6],
            ['PART', 'Seal Pompa Hidrolik 35mm', 'Seal', 'NOK', 240000, 4],
            ['PART', 'V-Belt B-52', 'Belt', 'Mitsuboshi', 165000, 6],
            ['PART', 'Kontaktor 3P 25A', 'Elektrik', 'Schneider', 620000, 3],
            ['PART', 'Solenoid Valve 1/4"', 'Pneumatik', 'SMC', 850000, 3],
            ['PART', 'Proximity Sensor M18', 'Sensor', 'Omron', 720000, 4],
            ['PART', 'Roller Guide Cutting', 'Mekanik', 'Lokal', 1250000, 2],
            ['PART', 'Chuck Jaw Set 3"', 'Mekanik', 'Lokal', 1850000, 2],
            ['PART', 'Filter Udara Kompresor', 'Filter', 'Donaldson', 430000, 4],
            ['PART', 'Selang Hidrolik 1/2" 2m', 'Hidrolik', 'Parker', 380000, 5],
            ['PART', 'Motor Fan 1/4 HP', 'Elektrik', 'Panasonic', 1450000, 2],
            ['PART', 'Limit Switch Roller', 'Elektrik', 'Omron', 265000, 5],

            ['CONSUMABLE', 'Mata Gergaji Pipa HSS 350mm', 'Cutting Tool', 'Bahco', 480000, 20],
            ['CONSUMABLE', 'Batu Gerinda 4"', 'Abrasif', 'Nippon', 28000, 50],
            ['CONSUMABLE', 'Sarung Tangan Kulit', 'APD', 'Krisbow', 45000, 40],
            ['CONSUMABLE', 'Kacamata Safety', 'APD', '3M', 85000, 25],
            ['CONSUMABLE', 'Masker Debu N95', 'APD', '3M', 32000, 60],
            ['CONSUMABLE', 'Oli Hidrolik ISO 68 (liter)', 'Pelumas', 'Shell', 42000, 60],
            ['CONSUMABLE', 'Grease Bearing (kg)', 'Pelumas', 'Shell', 95000, 20],
            ['CONSUMABLE', 'Majun / Kain Lap (kg)', 'Kebersihan', 'Lokal', 18000, 40],
            ['CONSUMABLE', 'Kawat Las 2.6mm (kg)', 'Las', 'Nikko', 65000, 30],
            ['CONSUMABLE', 'Cairan Coolant (liter)', 'Pelumas', 'Castrol', 55000, 40],

            ['TOOL', 'Kunci Momen 20-100 Nm', 'Hand Tool', 'Tohnichi', 4850000, 2],
            ['TOOL', 'Jangka Sorong Digital 300mm', 'Alat Ukur', 'Mitutoyo', 3250000, 2],
            ['TOOL', 'Micrometer 0-25mm', 'Alat Ukur', 'Mitutoyo', 2150000, 2],
            ['TOOL', 'Dial Indicator 0.01mm', 'Alat Ukur', 'Mitutoyo', 1750000, 2],
            ['TOOL', 'Bor Tangan 13mm', 'Power Tool', 'Makita', 2450000, 1],
            ['TOOL', 'Gerinda Tangan 4"', 'Power Tool', 'Makita', 1650000, 2],
            ['TOOL', 'Tang Ampere Digital', 'Alat Ukur', 'Fluke', 3850000, 1],
            ['TOOL', 'Hydraulic Puller Set', 'Hand Tool', 'Enerpac', 7850000, 1],
        ];

        $prefix = ['PART' => 'SP', 'CONSUMABLE' => 'CN', 'TOOL' => 'TL'];
        $seq = ['PART' => 0, 'CONSUMABLE' => 0, 'TOOL' => 0];
        $items = [];

        foreach ($catalog as $id => [$type, $name, $categ, $brand, $cost, $min]) {
            $code = sprintf('%s-%04d', $prefix[$type], ++$seq[$type]);

            SeedWriter::put('m_whs_item', [
                'id' => $id + 1,
                'code' => $code,
                'name' => $name,
                'whs_type' => $type,
                'categ' => $categ,
                'uom_id' => 1,
                'brand' => $brand,
                'spec' => $name,
                'rack_loc' => sprintf('WHS-%s-%02d', substr($type, 0, 1), ($id % 8) + 1),
                'min_stock' => $min,
                'max_stock' => $min * 5,
                'standard_cost' => $cost,
                'active' => 1,
            ], DemoCalendar::date(1));

            $items[] = ['id' => $id + 1, 'type' => $type, 'cost' => $cost, 'name' => $name, 'code' => $code];
        }

        return $items;
    }

    /** Enam PO, dibagi per pemasok; semuanya sudah disetujui dan diterima penuh. */
    private function seedPos(array $items): void
    {
        $detId = 0;

        foreach (array_chunk($items, 5) as $poIndex => $chunk) {
            $poId = $poIndex + 1;
            $date = DemoCalendar::date(2 + $poIndex);

            SeedWriter::put('whs_po_main', [
                'id' => $poId,
                'code' => sprintf('WPO/2026/07/%05d', $poId),
                'date' => $date->toDateString(),
                'ven_id' => (($poIndex * 7) % 45) + 1,
                'currency_id' => 1,
                'rate' => 1,
                'top_days' => 30,
                'eta' => $date->copy()->addDays(7)->toDateString(),
                'status' => 'CLOSE',
                'note' => 'Pengadaan rutin gudang WHS.',
                'user_id' => 1,
            ], $date);

            foreach ($chunk as $item) {
                $qty = self::RECEIVE[$item['type']];
                SeedWriter::put('whs_po_det', [
                    'id' => ++$detId,
                    'main_id' => $poId,
                    'item_id' => $item['id'],
                    'qty' => $qty,
                    'qty_received' => $qty,
                    'price' => $item['cost'],
                ]);
            }
        }
    }

    /**
     * Penerimaan: satu dokumen per PO, semuanya sudah di-post.
     *
     * Tiap alat langsung mendapat nomor unitnya sendiri di sini — sama seperti
     * yang dilakukan WhsPostingService saat dokumen di-post lewat layar.
     */
    private function seedIncoming(array $items): void
    {
        $detId = 0;
        $unitId = 0;
        $poDetId = 0;
        $journalRef = [];

        foreach (array_chunk($items, 5) as $incIndex => $chunk) {
            $incId = $incIndex + 1;
            $date = DemoCalendar::date(9 + $incIndex);
            $amount = 0.0;

            SeedWriter::put('whs_inc_main', [
                'id' => $incId,
                'code' => sprintf('WIN/2026/07/%05d', $incId),
                'date' => $date->toDateString(),
                'po_id' => $incId,
                'ven_id' => (($incIndex * 7) % 45) + 1,
                'do_no' => sprintf('SJ-%05d', 4200 + $incId),
                'status' => 'POSTED',
                'posted_at' => $date,
                'user_id' => 1,
            ], $date);

            foreach ($chunk as $item) {
                $qty = self::RECEIVE[$item['type']];
                $amount += $qty * $item['cost'];

                SeedWriter::put('whs_inc_det', [
                    'id' => ++$detId,
                    'main_id' => $incId,
                    'po_det_id' => ++$poDetId,
                    'item_id' => $item['id'],
                    // Alat memakai nomor unit; sparepart & barang habis pakai
                    // memakai serial batch, satu per baris penerimaan.
                    'serial_code' => $item['type'] === 'TOOL'
                        ? null
                        : sprintf('%s/%s/%03d', $item['code'], $date->format('ym'), $incId),
                    'qty' => $qty,
                    'unit_cost' => $item['cost'],
                ]);

                if ($item['type'] === 'TOOL') {
                    for ($n = 1; $n <= $qty; $n++) {
                        SeedWriter::put('whs_tool_unit', [
                            'id' => ++$unitId,
                            'item_id' => $item['id'],
                            'code' => sprintf('%s#%03d', $item['code'], $n),
                            'inc_det_id' => $detId,
                            'status' => 'IN_STOCK',
                            'unit_cost' => $item['cost'],
                        ], $date);
                    }
                }
            }

            $journalRef[] = ['id' => $incId, 'date' => $date, 'amount' => $amount];
        }

        foreach ($journalRef as $j) {
            $this->journal('WHS_INC', $j['id'], $j['date'], 'WHS_IN', [
                ['1340', $j['amount'], 0, 'Persediaan WHS'],
                ['2100', 0, $j['amount'], 'Utang pembelian WHS'],
            ], sprintf('Penerimaan WHS WIN/2026/07/%05d', $j['id']));
        }
    }

    /**
     * Pemakaian: sparepart & barang habis pakai dibebankan, alat dipinjamkan.
     *
     * Alat sengaja tidak masuk jurnal di sini — selama masih di tangan orang,
     * alatnya masih milik perusahaan.
     */
    private function seedOutgoing(array $items): void
    {
        $detId = 0;
        $outId = 0;
        $unitCursor = [];

        foreach (array_chunk($items, 5) as $outIndex => $chunk) {
            $outId = $outIndex + 1;
            $date = DemoCalendar::date(14 + $outIndex);
            $expense = 0.0;
            $receiver = sprintf('Teknisi Shift %d', ($outIndex % 3) + 1);
            $costCenter = ($outIndex % 2 === 0) ? 'MAINTENANCE' : 'PROD_CUTTING';

            SeedWriter::put('whs_out_main', [
                'id' => $outId,
                'code' => sprintf('WOU/2026/07/%05d', $outId),
                'date' => $date->toDateString(),
                'dept' => ($outIndex % 2 === 0) ? 'MAINTENANCE' : 'PRODUKSI',
                'receiver' => $receiver,
                'cost_center' => $costCenter,
                'status' => 'POSTED',
                'posted_at' => $date,
                'user_id' => 1,
            ], $date);

            foreach ($chunk as $item) {
                $qty = self::ISSUE[$item['type']];

                SeedWriter::put('whs_out_det', [
                    'id' => ++$detId,
                    'main_id' => $outId,
                    'item_id' => $item['id'],
                    // Batch yang diambil — sama seperti yang dipilih petugas di
                    // layar, dan inilah kode yang nanti muncul di catatan
                    // downtime mesin.
                    'serial_code' => DB::table('whs_inc_det')->where('item_id', $item['id'])
                        ->whereNotNull('serial_code')->orderBy('id')->value('serial_code'),
                    'qty' => $qty,
                    'unit_cost' => $item['cost'],
                    'cost_center' => $costCenter,
                    'machine_id' => (($item['id'] % 20) + 1),
                    'qty_returned' => 0,
                ]);

                if ($item['type'] === 'TOOL') {
                    // Unit yang dipinjam diambil berurutan dari unit milik alat
                    // itu, jadi tidak ada unit yang dipinjam dua kali.
                    $taken = $unitCursor[$item['id']] ?? 0;
                    $units = DB::table('whs_tool_unit')
                        ->where('item_id', $item['id'])
                        ->orderBy('id')->skip($taken)->take($qty)->pluck('id');
                    $unitCursor[$item['id']] = $taken + $units->count();

                    DB::table('whs_tool_unit')->whereIn('id', $units)->update([
                        'status' => 'ON_LOAN',
                        'holder' => $receiver,
                        'out_det_id' => $detId,
                        'updated_at' => $date,
                    ]);

                    continue;
                }

                $expense += $qty * $item['cost'];
            }

            if ($expense > 0) {
                $this->journal('WHS_OUT', $outId, $date, 'WHS_OUT', [
                    ['6400', $expense, 0, "Pemakaian WHS {$costCenter}"],
                    ['1340', 0, $expense, 'Persediaan WHS'],
                ], sprintf('Pengeluaran WHS WOU/2026/07/%05d', $outId));
            }
        }
    }

    /**
     * Pengembalian alat: sebagian besar utuh, satu rusak.
     *
     * Yang rusak itulah yang membuat modul ini ada gunanya — di situ nilai
     * alatnya benar-benar hilang dan baru saat itu dibebankan.
     */
    private function seedReturns(): void
    {
        $onLoan = DB::table('whs_tool_unit')->where('status', 'ON_LOAN')->orderBy('id')->get();

        if ($onLoan->isEmpty()) {
            return;
        }

        // Sekitar separuh dikembalikan; sisanya tetap di tangan pemakainya,
        // supaya daftar "sedang dipinjam" tidak kosong.
        $returned = $onLoan->take((int) ceil($onLoan->count() / 2));
        $date = DemoCalendar::date(26);
        $detId = 0;
        $loss = 0.0;

        SeedWriter::put('whs_ret_main', [
            'id' => 1,
            'code' => 'WRT/2026/07/00001',
            'date' => $date->toDateString(),
            'returner' => 'Teknisi Shift 1',
            'note' => 'Pengembalian rutin akhir bulan.',
            'status' => 'POSTED',
            'posted_at' => $date,
            'user_id' => 1,
        ], $date);

        foreach ($returned as $i => $unit) {
            $condition = ($i === 0) ? 'DAMAGED' : 'GOOD';

            SeedWriter::put('whs_ret_det', [
                'id' => ++$detId,
                'main_id' => 1,
                'out_det_id' => $unit->out_det_id,
                'tool_unit_id' => $unit->id,
                'condition' => $condition,
                'note' => $condition === 'DAMAGED' ? 'Rahang aus, tidak bisa dikalibrasi ulang.' : null,
            ]);

            DB::table('whs_tool_unit')->where('id', $unit->id)->update([
                'status' => $condition === 'DAMAGED' ? 'DAMAGED' : 'IN_STOCK',
                'holder' => null,
                'out_det_id' => $condition === 'DAMAGED' ? $unit->out_det_id : null,
                'updated_at' => $date,
            ]);

            if ($unit->out_det_id) {
                DB::table('whs_out_det')->where('id', $unit->out_det_id)->increment('qty_returned');
            }

            if ($condition === 'DAMAGED') {
                $loss += (float) $unit->unit_cost;
            }
        }

        if ($loss > 0) {
            $this->journal('WHS_RET', 1, $date, 'WHS_LOSS', [
                ['6410', $loss, 0, 'Alat rusak saat dikembalikan'],
                ['1340', 0, $loss, 'Persediaan WHS'],
            ], 'Pengembalian alat WRT/2026/07/00001');
        }
    }

    /**
     * Tulis satu jurnal beserta barisnya.
     *
     * Tidak lewat JournalEngine karena periode demo sudah ditutup ketika seeder
     * ini berjalan — engine akan menolaknya, dan menutup lalu membuka periode
     * hanya demi seeder justru membuat data periode ikut palsu.
     */
    private function journal(string $refType, int $refId, $date, string $jrnType, array $lines, string $descrip): void
    {
        /*
         * Idempoten pada (dokumen, jenis jurnal) — aturan yang sama dipakai
         * JournalEngine. Tanpa ini, menjalankan seeder ini sendirian tanpa
         * menghapus data lebih dulu akan menabrak nomor jurnal yang sudah ada.
         */
        $existing = DB::table('acc_journal_main')
            ->where('ref_type', $refType)->where('ref_id', $refId)->where('jrn_type', $jrnType)
            ->value('id');

        if ($existing) {
            DB::table('acc_journal_det')->where('main_id', $existing)->delete();
            DB::table('acc_journal_main')->where('id', $existing)->delete();
        }

        $mainId = DB::table('acc_journal_main')->insertGetId([
            'code' => sprintf('JV/WHS/%s/%05d', $jrnType, $refId),
            'date' => $date->toDateString(),
            'period' => $date->format('Ym'),
            'jrn_type' => $jrnType,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'descrip' => $descrip,
            'status' => 'POSTED',
            'user_id' => 1,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        foreach ($lines as [$coa, $debit, $credit, $memo]) {
            $coaId = DB::table('acc_coa')->where('code', $coa)->value('id');
            if (! $coaId) {
                continue;
            }

            DB::table('acc_journal_det')->insert([
                'main_id' => $mainId,
                'coa_id' => $coaId,
                'debit' => round((float) $debit, 2),
                'credit' => round((float) $credit, 2),
                'memo' => $memo,
            ]);
        }
    }
}
