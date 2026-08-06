<?php

namespace App\Support;

use App\Exceptions\BizException;
use App\Models\whs_tool_unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Stok gudang WHS: berapa yang ada, berapa nilainya, dan alat mana di siapa.
 *
 * Stok tidak disimpan sebagai angka yang di-update — ia dihitung dari dokumen
 * yang sudah di-post. Kolom saldo yang di-update di banyak tempat adalah cara
 * paling umum stok gudang jadi berbeda dengan kenyataan; di sini satu-satunya
 * sumber kebenaran adalah penerimaan, pengeluaran, dan pengembalian.
 *
 * Alat dihitung berbeda dari dua jenis lain, dan memang harus: stok alat adalah
 * jumlah unit yang sedang ada di gudang, bukan jumlah yang pernah dibeli
 * dikurangi yang pernah keluar — sebagian di antaranya akan kembali.
 */
class WhsStockService
{
    /**
     * Stok per barang.
     *
     * @return array<int, int> item_id => qty
     */
    public function stockByItem(): array
    {
        $in = DB::table('whs_inc_det as d')
            ->join('whs_inc_main as m', 'm.id', '=', 'd.main_id')
            ->where('m.status', 'POSTED')
            ->groupBy('d.item_id')
            ->selectRaw('d.item_id, SUM(d.qty) as qty')
            ->pluck('qty', 'item_id');

        $out = DB::table('whs_out_det as d')
            ->join('whs_out_main as m', 'm.id', '=', 'd.main_id')
            ->where('m.status', 'POSTED')
            ->groupBy('d.item_id')
            // Yang sudah dikembalikan tidak lagi terhitung keluar.
            ->selectRaw('d.item_id, SUM(d.qty - d.qty_returned) as qty')
            ->pluck('qty', 'item_id');

        // Alat yang kembali dalam keadaan rusak atau hilang tidak masuk stok
        // lagi, jadi jumlahnya dikurangkan tersendiri.
        $written = DB::table('whs_tool_unit')
            ->whereIn('status', ['DAMAGED', 'LOST', 'SCRAPPED'])
            ->groupBy('item_id')
            ->selectRaw('item_id, COUNT(*) as qty')
            ->pluck('qty', 'item_id');

        $stock = [];
        foreach (array_unique([...$in->keys()->all(), ...$out->keys()->all(), ...$written->keys()->all()]) as $itemId) {
            $stock[(int) $itemId] = (int) ($in[$itemId] ?? 0)
                - (int) ($out[$itemId] ?? 0)
                - (int) ($written[$itemId] ?? 0);
        }

        return $stock;
    }

    public function stock(int $itemId): int
    {
        return $this->stockByItem()[$itemId] ?? 0;
    }

    /**
     * Harga rata-rata tertimbang dari penerimaan yang sudah di-post.
     *
     * Barang yang belum pernah diterima memakai harga acuan di master — supaya
     * barang baru tidak muncul bernilai nol di laporan persediaan.
     *
     * @return array<int, float>
     */
    public function avgCosts(): array
    {
        $rows = DB::table('whs_inc_det as d')
            ->join('whs_inc_main as m', 'm.id', '=', 'd.main_id')
            ->where('m.status', 'POSTED')
            ->groupBy('d.item_id')
            ->selectRaw('d.item_id, SUM(d.qty * d.unit_cost) as amount, SUM(d.qty) as qty')
            ->get();

        $costs = [];
        foreach ($rows as $r) {
            if ((int) $r->qty > 0) {
                $costs[(int) $r->item_id] = round((float) $r->amount / (int) $r->qty, 2);
            }
        }

        foreach (DB::table('m_whs_item')->where('standard_cost', '>', 0)->get(['id', 'standard_cost']) as $i) {
            $costs[(int) $i->id] ??= (float) $i->standard_cost;
        }

        return $costs;
    }

    public function unitCost(int $itemId): float
    {
        return $this->avgCosts()[$itemId] ?? 0.0;
    }

    /**
     * Daftar stok lengkap dengan nilai dan penanda di bawah minimum.
     *
     * @return array<int, array<string, mixed>>
     */
    public function stockList(?string $type = null, bool $onlyBelowMin = false): array
    {
        $stock = $this->stockByItem();
        $costs = $this->avgCosts();
        $onLoan = $this->onLoanCountByItem();

        $items = DB::table('m_whs_item')
            ->when($type, fn ($q) => $q->where('whs_type', $type))
            ->where('active', 1)
            ->orderBy('code')
            ->get();

        $rows = [];
        foreach ($items as $i) {
            $qty = (int) ($stock[$i->id] ?? 0);
            $cost = (float) ($costs[$i->id] ?? 0);
            $below = (int) $i->min_stock > 0 && $qty < (int) $i->min_stock;

            if ($onlyBelowMin && ! $below) {
                continue;
            }

            $rows[] = [
                'item_id' => (int) $i->id,
                'code' => $i->code,
                'name' => $i->name,
                'whs_type' => $i->whs_type,
                'categ' => $i->categ,
                'rack_loc' => $i->rack_loc,
                'qty' => $qty,
                'on_loan' => (int) ($onLoan[$i->id] ?? 0),
                'min_stock' => (int) $i->min_stock,
                'below_min' => $below,
                'unit_cost' => $cost,
                'value' => round($qty * $cost, 2),
            ];
        }

        return $rows;
    }

    /**
     * Saldo tiap serial penerimaan, tertua dulu.
     *
     * Satu serial adalah satu batch yang pernah diterima; sisanya adalah yang
     * diterima dikurangi yang sudah pernah diambil dari batch itu. Urutan tua ke
     * muda dipakai untuk memilih otomatis saat petugas tidak menentukan batch.
     *
     * @return array<int, array<string, mixed>>
     */
    public function serialBalances(?int $itemId = null, bool $onlyAvailable = false): array
    {
        $issued = DB::table('whs_out_det as d')
            ->join('whs_out_main as m', 'm.id', '=', 'd.main_id')
            ->where('m.status', 'POSTED')
            ->whereNotNull('d.serial_code')
            ->groupBy('d.serial_code')
            ->selectRaw('d.serial_code, SUM(d.qty) as qty')
            ->pluck('qty', 'serial_code');

        $rows = DB::table('whs_inc_det as d')
            ->join('whs_inc_main as m', 'm.id', '=', 'd.main_id')
            ->join('m_whs_item as i', 'i.id', '=', 'd.item_id')
            ->where('m.status', 'POSTED')
            ->whereNotNull('d.serial_code')
            ->when($itemId, fn ($q) => $q->where('d.item_id', $itemId))
            ->orderBy('m.date')->orderBy('d.id')
            ->get([
                'd.serial_code', 'd.item_id', 'd.qty', 'd.unit_cost',
                'i.code as item_code', 'i.name as item_name', 'i.whs_type',
                'm.code as receipt_code', 'm.date as receipt_date', 'm.do_no',
            ]);

        $out = [];
        foreach ($rows as $r) {
            $used = (int) ($issued[$r->serial_code] ?? 0);
            $remaining = (int) $r->qty - $used;

            if ($onlyAvailable && $remaining <= 0) {
                continue;
            }

            $out[] = [
                'serial_code' => $r->serial_code,
                'item_id' => (int) $r->item_id,
                'item_code' => $r->item_code,
                'item_name' => $r->item_name,
                'whs_type' => $r->whs_type,
                'received' => (int) $r->qty,
                'issued' => $used,
                'remaining' => $remaining,
                'unit_cost' => (float) $r->unit_cost,
                'receipt_code' => $r->receipt_code,
                'receipt_date' => $r->receipt_date,
                'do_no' => $r->do_no,
            ];
        }

        return $out;
    }

    /**
     * Cari satu kode WHS, apa pun bentuknya.
     *
     * Downtime mesin menyebut "serial" untuk dua hal yang berbeda asalnya:
     * batch sparepart dari penerimaan, dan unit alat yang dipinjam. Keduanya
     * kode sah milik gudang ini, jadi pencariannya satu pintu — layar lantai
     * produksi tidak perlu tahu bedanya.
     */
    public function findCode(string $code): ?array
    {
        $serial = collect($this->serialBalances())->firstWhere('serial_code', $code);

        if ($serial) {
            return $serial + ['kind' => 'SERIAL'];
        }

        $unit = DB::table('whs_tool_unit as u')
            ->join('m_whs_item as i', 'i.id', '=', 'u.item_id')
            ->where('u.code', $code)
            ->first(['u.code', 'u.item_id', 'u.status', 'u.holder', 'i.code as item_code', 'i.name as item_name', 'i.whs_type']);

        if (! $unit) {
            return null;
        }

        return [
            'kind' => 'TOOL_UNIT',
            'serial_code' => $unit->code,
            'item_id' => (int) $unit->item_id,
            'item_code' => $unit->item_code,
            'item_name' => $unit->item_name,
            'whs_type' => $unit->whs_type,
            'status' => $unit->status,
            'holder' => $unit->holder,
        ];
    }

    /**
     * Kode yang bisa dipilih layar downtime: batch sparepart yang masih ada
     * isinya, ditambah unit alat yang sedang dipakai orang.
     */
    public function usableCodes(?string $q = null): array
    {
        $serials = collect($this->serialBalances(null, true))
            ->map(fn ($s) => $s + ['kind' => 'SERIAL', 'label' => "{$s['serial_code']} — {$s['item_name']} (sisa {$s['remaining']})"]);

        $units = collect(DB::table('whs_tool_unit as u')
            ->join('m_whs_item as i', 'i.id', '=', 'u.item_id')
            ->whereIn('u.status', [whs_tool_unit::IN_STOCK, whs_tool_unit::ON_LOAN])
            ->orderBy('u.code')
            ->get(['u.code as serial_code', 'u.item_id', 'u.status', 'u.holder', 'i.code as item_code', 'i.name as item_name', 'i.whs_type']))
            ->map(fn ($u) => (array) $u + [
                'kind' => 'TOOL_UNIT',
                'label' => "{$u->serial_code} — {$u->item_name}".($u->holder ? " (di {$u->holder})" : ' (di gudang)'),
            ]);

        return $serials->concat($units)
            ->when($q, fn ($c) => $c->filter(fn ($r) => stripos($r['serial_code'].' '.$r['item_name'], $q) !== false))
            ->values()
            ->all();
    }

    /**
     * Terjemahkan kode yang diketik/dipindai di lantai produksi menjadi barang
     * WHS yang sesungguhnya.
     *
     * Kode yang tidak dikenal ditolak, bukan disimpan apa adanya: catatan
     * downtime yang menyebut serial karangan tidak bisa dipakai menjawab
     * "sparepart ini sudah berapa kali diganti", dan itulah satu-satunya alasan
     * kolomnya ada.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  string  $field  nama kolom serial pada baris masukan
     * @return array<int, array{serial: string, item_id: int, kind: string}>
     */
    public function resolveCodes(array $rows, string $field): array
    {
        $out = [];
        $unknown = [];

        foreach ($rows as $row) {
            $code = trim((string) ($row[$field] ?? ''));
            if ($code === '') {
                continue;
            }

            $found = $this->findCode($code);

            if (! $found) {
                $unknown[] = $code;

                continue;
            }

            $out[] = ['serial' => $code, 'item_id' => $found['item_id'], 'kind' => $found['kind']];
        }

        if ($unknown) {
            throw BizException::make(
                'WHS_CODE_UNKNOWN',
                'Serial berikut tidak dikenal gudang WHS: '.implode(', ', $unknown)
                .'. Pakai serial penerimaan sparepart atau nomor unit alat.'
            );
        }

        return $out;
    }

    /** @return array<int, int> item_id => jumlah unit sedang dipinjam */
    public function onLoanCountByItem(): array
    {
        return DB::table('whs_tool_unit')
            ->where('status', whs_tool_unit::ON_LOAN)
            ->groupBy('item_id')
            ->selectRaw('item_id, COUNT(*) as qty')
            ->pluck('qty', 'item_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Alat yang sedang di luar gudang: unit mana, dipegang siapa, sejak kapan.
     *
     * Ini pertanyaan yang paling sering diajukan ke gudang alat, dan yang paling
     * tidak bisa dijawab kalau alat hanya dicatat sebagai jumlah.
     */
    public function onLoan(): array
    {
        return DB::table('whs_tool_unit as u')
            ->join('m_whs_item as i', 'i.id', '=', 'u.item_id')
            ->leftJoin('whs_out_det as d', 'd.id', '=', 'u.out_det_id')
            ->leftJoin('whs_out_main as m', 'm.id', '=', 'd.main_id')
            ->where('u.status', 'ON_LOAN')
            ->orderBy('m.date')
            ->get([
                'u.id', 'u.code as unit_code', 'u.holder', 'u.item_id',
                'i.code as item_code', 'i.name as item_name',
                'd.id as out_det_id', 'm.code as out_code', 'm.date as out_date',
                'm.dept', 'm.cost_center',
            ])
            ->map(function ($r) {
                // Lama di luar selalu dihitung sebagai selisih hari yang positif;
                // tanda arah selisih berbeda antar versi Carbon, dan "alat ini
                // sudah di luar minus 16 hari" bukan kalimat yang berarti apa pun.
                $r->days_out = $r->out_date
                    ? (int) abs(Carbon::parse($r->out_date)->diffInDays(now()->startOfDay()))
                    : null;

                return $r;
            })
            ->all();
    }

    /** Ringkasan untuk kepala laporan. */
    public function summary(): array
    {
        $rows = $this->stockList();
        $byType = [];
        foreach (['PART', 'CONSUMABLE', 'TOOL'] as $t) {
            $sel = array_filter($rows, fn ($r) => $r['whs_type'] === $t);
            $byType[$t] = [
                'items' => count($sel),
                'qty' => array_sum(array_column($sel, 'qty')),
                'value' => round(array_sum(array_column($sel, 'value')), 2),
                'below_min' => count(array_filter($sel, fn ($r) => $r['below_min'])),
            ];
        }

        return [
            'as_of' => now()->toDateString(),
            'by_type' => $byType,
            'total_value' => round(array_sum(array_column($rows, 'value')), 2),
            'below_min' => count(array_filter($rows, fn ($r) => $r['below_min'])),
            'on_loan' => count($this->onLoan()),
        ];
    }
}
