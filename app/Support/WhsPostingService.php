<?php

namespace App\Support;

use App\Exceptions\BizException;
use App\Models\whs_inc_main;
use App\Models\whs_out_main;
use App\Models\whs_ret_main;
use App\Models\whs_tool_unit;
use Illuminate\Support\Facades\DB;

/**
 * Saat dokumen WHS di-post: stok bergerak, unit alat berpindah tangan, jurnal
 * ditulis. Sebelum di-post, dokumen boleh diubah sesuka hati dan tidak
 * berpengaruh apa pun.
 *
 * Pembebanan biayanya sengaja tidak seragam untuk ketiga jenis barang:
 *
 *   PART & CONSUMABLE keluar sekali dan habis, jadi saat keluar langsung jadi
 *   beban cost center yang memakainya.
 *
 *   TOOL keluar tapi masih milik perusahaan. Membebankannya saat dipinjam
 *   berarti kunci momen seharga sepuluh juta hilang dari neraca hanya karena
 *   dibawa ke lantai produksi — lalu muncul lagi sebagai barang gratis waktu
 *   dikembalikan. Jadi alat baru dibebankan ketika benar-benar hilang nilainya:
 *   kembali dalam keadaan rusak, atau tidak kembali sama sekali.
 */
class WhsPostingService
{
    /** Akun: persediaan WHS, beban pemakaian, beban kerugian alat, utang usaha. */
    private const COA_STOCK = '1340';

    private const COA_EXPENSE = '6400';

    private const COA_LOSS = '6410';

    private const COA_AP = '2100';

    public function __construct(private WhsStockService $stock) {}

    /**
     * Terima barang: stok bertambah, dan tiap alat mendapat nomor unitnya sendiri.
     */
    public function postIncoming(whs_inc_main $inc, int $userId): whs_inc_main
    {
        $this->assertDraft($inc->status, 'Penerimaan');

        $inc->load('detail.item');
        if ($inc->detail->isEmpty()) {
            throw BizException::make('WHS_INC_EMPTY', 'Penerimaan tanpa baris barang tidak dapat di-post.');
        }

        return DB::transaction(function () use ($inc, $userId) {
            $amount = 0.0;

            foreach ($inc->detail as $line) {
                $amount += $line->qty * $line->unit_cost;

                if ($line->po_det_id) {
                    DB::table('whs_po_det')->where('id', $line->po_det_id)->increment('qty_received', $line->qty);
                }

                if ($line->item?->isTool()) {
                    $this->createToolUnits($line);
                } else {
                    // Sparepart & barang habis pakai tidak kembali, tapi tetap
                    // butuh identitas batch: dua kiriman bearing yang sama
                    // persis bisa berbeda umur pakainya, dan itu baru ketahuan
                    // saat salah satunya membuat mesin berhenti.
                    $this->assignSerial($line);
                }
            }

            if ($inc->po_id) {
                $this->closePoIfComplete((int) $inc->po_id);
            }

            $inc->update(['status' => 'POSTED', 'posted_at' => now()]);

            // Barang masuk gudang menambah persediaan dan menimbulkan utang ke
            // pemasok — nilainya sama dengan yang benar-benar diterima, bukan
            // yang dipesan.
            if ($amount > 0) {
                JournalEngine::post(
                    'WHS_INC', $inc->id, $inc->date->toDateString(), 'WHS_IN',
                    [
                        ['coa' => self::COA_STOCK, 'debit' => round($amount, 2), 'memo' => "Persediaan WHS {$inc->code}"],
                        ['coa' => self::COA_AP, 'credit' => round($amount, 2), 'memo' => "Utang {$inc->code}"],
                    ],
                    "Penerimaan WHS {$inc->code}",
                    $userId
                );
            }

            return $inc->fresh()->load('detail.item');
        });
    }

    /**
     * Keluarkan barang.
     *
     * Stok dicek per barang sebelum apa pun ditulis: setengah dokumen yang
     * berhasil dan setengah yang gagal adalah keadaan yang tidak bisa
     * dipertanggungjawabkan oleh siapa pun di gudang.
     */
    public function postOutgoing(whs_out_main $out, int $userId): whs_out_main
    {
        $this->assertDraft($out->status, 'Pengeluaran');

        $out->load('detail.item');
        if ($out->detail->isEmpty()) {
            throw BizException::make('WHS_OUT_EMPTY', 'Pengeluaran tanpa baris barang tidak dapat di-post.');
        }

        $stock = $this->stock->stockByItem();
        $costs = $this->stock->avgCosts();

        $need = [];
        foreach ($out->detail as $line) {
            $need[$line->item_id] = ($need[$line->item_id] ?? 0) + (int) $line->qty;
        }
        foreach ($need as $itemId => $qty) {
            $have = (int) ($stock[$itemId] ?? 0);
            if ($qty > $have) {
                $name = $out->detail->firstWhere('item_id', $itemId)?->item?->name ?? "#{$itemId}";
                throw BizException::make(
                    'WHS_STOCK',
                    "Stok {$name} tinggal {$have}, diminta {$qty}."
                );
            }
        }

        // Saldo per batch dibaca sekali, lalu dikurangi di memori seiring baris
        // diproses — dua baris yang menunjuk batch sama tidak boleh sama-sama
        // merasa batch itu masih penuh.
        $balances = $this->stock->serialBalances();

        return DB::transaction(function () use ($out, $userId, $costs, &$balances) {
            $expense = 0.0;

            foreach ($out->detail as $line) {
                // Harga rata-rata dibekukan di baris supaya nilai pengeluaran
                // bulan lalu tidak ikut berubah saat pembelian baru datang.
                $cost = (float) ($line->unit_cost ?: ($costs[$line->item_id] ?? 0));
                $line->update(['unit_cost' => $cost]);

                if ($line->item?->isTool()) {
                    $this->lendToolUnits($line, $out);

                    continue;   // alat belum jadi beban
                }

                $serial = $this->resolveSerial($line, $balances);
                $line->update(['serial_code' => $serial]);

                foreach ($balances as $i => $b) {
                    if ($b['serial_code'] === $serial) {
                        $balances[$i]['remaining'] -= (int) $line->qty;
                    }
                }

                $expense += $line->qty * $cost;
            }

            $out->update(['status' => 'POSTED', 'posted_at' => now()]);

            if ($expense > 0) {
                JournalEngine::post(
                    'WHS_OUT', $out->id, $out->date->toDateString(), 'WHS_OUT',
                    [
                        ['coa' => self::COA_EXPENSE, 'debit' => round($expense, 2),
                            'memo' => 'Pemakaian WHS '.($out->cost_center ?: $out->dept ?: $out->code)],
                        ['coa' => self::COA_STOCK, 'credit' => round($expense, 2), 'memo' => "Persediaan WHS {$out->code}"],
                    ],
                    "Pengeluaran WHS {$out->code}",
                    $userId
                );
            }

            return $out->fresh()->load('detail.item');
        });
    }

    /**
     * Terima kembali alat.
     *
     * Yang kembali baik masuk stok lagi. Yang rusak atau hilang keluar dari
     * stok untuk selamanya dan baru pada saat itulah nilainya dibebankan —
     * inilah momen kerugiannya benar-benar terjadi.
     */
    public function postReturn(whs_ret_main $ret, int $userId): whs_ret_main
    {
        $this->assertDraft($ret->status, 'Pengembalian');

        $ret->load('detail.unit');
        if ($ret->detail->isEmpty()) {
            throw BizException::make('WHS_RET_EMPTY', 'Pengembalian tanpa baris alat tidak dapat di-post.');
        }

        return DB::transaction(function () use ($ret, $userId) {
            $loss = 0.0;

            foreach ($ret->detail as $line) {
                $unit = $line->unit;

                if (! $unit || $unit->status !== whs_tool_unit::ON_LOAN) {
                    throw BizException::make(
                        'WHS_RET_UNIT',
                        'Unit '.($unit->code ?? "#{$line->tool_unit_id}").' tidak sedang dipinjam.'
                    );
                }

                $status = match ($line->condition) {
                    'DAMAGED' => 'DAMAGED',
                    'LOST' => 'LOST',
                    default => whs_tool_unit::IN_STOCK,
                };

                $unit->update([
                    'status' => $status,
                    // Alat yang hilang tetap mencatat siapa yang terakhir
                    // memegangnya; itu justru informasi yang paling dicari.
                    'holder' => $status === 'LOST' ? $unit->holder : null,
                    'out_det_id' => $status === whs_tool_unit::IN_STOCK ? null : $unit->out_det_id,
                ]);

                if ($line->out_det_id) {
                    DB::table('whs_out_det')->where('id', $line->out_det_id)->increment('qty_returned');
                }

                if ($status !== whs_tool_unit::IN_STOCK) {
                    $loss += (float) $unit->unit_cost;
                }
            }

            $ret->update(['status' => 'POSTED', 'posted_at' => now()]);

            if ($loss > 0) {
                JournalEngine::post(
                    'WHS_RET', $ret->id, $ret->date->toDateString(), 'WHS_LOSS',
                    [
                        ['coa' => self::COA_LOSS, 'debit' => round($loss, 2), 'memo' => "Alat rusak/hilang {$ret->code}"],
                        ['coa' => self::COA_STOCK, 'credit' => round($loss, 2), 'memo' => "Persediaan WHS {$ret->code}"],
                    ],
                    "Pengembalian alat {$ret->code}",
                    $userId
                );
            }

            return $ret->fresh()->load('detail.unit');
        });
    }

    /* ---------------- bagian dalam ---------------- */

    private function assertDraft(string $status, string $label): void
    {
        if ($status !== 'DRAFT') {
            throw BizException::make('WHS_POSTED', "{$label} ini sudah di-post dan tidak dapat diproses ulang.");
        }
    }

    /**
     * Tiap alat yang diterima jadi satu unit bernomor.
     *
     * Nomornya diteruskan dari unit terakhir milik barang yang sama, jadi nomor
     * yang pernah dipakai tidak dipakai lagi walau unit lamanya sudah dihapus
     * dari peredaran.
     */
    private function createToolUnits($line): void
    {
        $item = $line->item;
        $last = (int) DB::table('whs_tool_unit')->where('item_id', $item->id)->count();

        for ($n = 1; $n <= (int) $line->qty; $n++) {
            whs_tool_unit::create([
                'item_id' => $item->id,
                'code' => sprintf('%s#%03d', $item->code, $last + $n),
                'inc_det_id' => $line->id,
                'status' => whs_tool_unit::IN_STOCK,
                'unit_cost' => $line->unit_cost,
            ]);
        }
    }

    /**
     * Beri satu serial pada baris penerimaan.
     *
     * Bentuknya `KODEBARANG/YYMM/NNN` supaya bisa dibaca orang di rak tanpa
     * membuka layar: kode barangnya kelihatan, bulan terimanya kelihatan, dan
     * urutannya cuma perlu unik dalam bulan itu.
     *
     * Serial yang sudah ada dipertahankan — mem-post ulang dokumen yang sama
     * tidak boleh mengganti identitas barang yang sudah terlanjur ditempel di
     * raknya.
     */
    private function assignSerial($line): void
    {
        if ($line->serial_code) {
            return;
        }

        $item = $line->item;
        $prefix = sprintf('%s/%s/', $item->code, now()->format('ym'));
        $used = DB::table('whs_inc_det')->where('serial_code', 'like', $prefix.'%')->count();

        $line->update(['serial_code' => $prefix.sprintf('%03d', $used + 1)]);
    }

    /**
     * Tentukan batch mana yang diambil untuk satu baris pengeluaran.
     *
     * Kalau petugas sudah memilih serialnya, pilihan itu yang dipakai dan
     * divalidasi. Kalau tidak, diambil batch tertua yang isinya cukup — dan
     * kalau tidak ada satu batch pun yang cukup, dokumennya ditolak dengan
     * saran memecah baris. Mengambil diam-diam dari dua batch sekaligus akan
     * membuat satu baris pengeluaran menunjuk dua asal barang, dan telusur ke
     * downtime jadi tidak berarti apa-apa.
     */
    private function resolveSerial($line, array $balances): string
    {
        $forItem = array_values(array_filter($balances, fn ($b) => $b['item_id'] === (int) $line->item_id && $b['remaining'] > 0));

        if ($line->serial_code) {
            $picked = collect($forItem)->firstWhere('serial_code', $line->serial_code);

            if (! $picked) {
                throw BizException::make(
                    'WHS_SERIAL',
                    "Serial {$line->serial_code} bukan milik barang ini atau isinya sudah habis."
                );
            }
            if ($picked['remaining'] < (int) $line->qty) {
                throw BizException::make(
                    'WHS_SERIAL_QTY',
                    "Serial {$line->serial_code} sisa {$picked['remaining']}, diminta {$line->qty}."
                );
            }

            return $line->serial_code;
        }

        foreach ($forItem as $b) {          // sudah urut dari yang paling lama
            if ($b['remaining'] >= (int) $line->qty) {
                return $b['serial_code'];
            }
        }

        $biggest = collect($forItem)->max('remaining') ?? 0;

        throw BizException::make(
            'WHS_SERIAL_SPLIT',
            "Tidak ada satu batch {$line->item->name} yang berisi {$line->qty} pcs (terbesar {$biggest} pcs). "
            .'Pecah jadi beberapa baris, satu baris per serial.'
        );
    }

    /** Pinjamkan unit yang ada di gudang, paling lama menganggur lebih dulu. */
    private function lendToolUnits($line, whs_out_main $out): void
    {
        $units = whs_tool_unit::where('item_id', $line->item_id)
            ->where('status', whs_tool_unit::IN_STOCK)
            ->orderBy('id')
            ->limit((int) $line->qty)
            ->get();

        if ($units->count() < (int) $line->qty) {
            throw BizException::make(
                'WHS_TOOL_UNIT',
                "Unit {$line->item->name} yang ada di gudang tinggal {$units->count()}, diminta {$line->qty}."
            );
        }

        foreach ($units as $unit) {
            $unit->update([
                'status' => whs_tool_unit::ON_LOAN,
                'holder' => $out->receiver ?: $out->dept,
                'out_det_id' => $line->id,
            ]);
        }
    }

    /** PO ditutup begitu semua barisnya diterima penuh. */
    private function closePoIfComplete(int $poId): void
    {
        $outstanding = DB::table('whs_po_det')
            ->where('main_id', $poId)
            ->whereColumn('qty_received', '<', 'qty')
            ->exists();

        if (! $outstanding) {
            DB::table('whs_po_main')->where('id', $poId)->update(['status' => 'CLOSE', 'updated_at' => now()]);
        }
    }
}
