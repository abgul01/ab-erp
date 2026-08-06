<?php

namespace App\Support;

use App\Exceptions\BizException;
use App\Models\m_supplier_item;
use App\Models\prc_contract_main;
use App\Models\prc_quot_det;
use App\Models\prc_quot_main;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dari penawaran vendor menjadi syarat beli yang dipakai MRP.
 *
 * Sebelum ini harga di `m_supplier_item` muncul tanpa riwayat: tidak ada
 * penawaran yang bisa ditunjuk, tidak ada pembanding, dan tidak ada masa
 * berlaku. Yang dikerjakan di sini adalah menutup jarak itu — memilih satu
 * baris penawaran menuliskannya ke master **beserta tautan balik ke penawaran
 * asalnya**, sehingga pertanyaan "kenapa harganya segini, dan kenapa vendor
 * ini" selalu punya jawaban yang bisa dibuka.
 *
 * PRD §4.7
 */
class VendorQuotationService
{
    /**
     * Perbandingan penawaran per material.
     *
     * Inilah alasan modul ini ada: harga satu material dari beberapa vendor,
     * berdampingan, lengkap dengan MOQ dan lead time-nya. Termurah belum tentu
     * terbaik — mill yang lebih murah dengan lead time 45 hari bisa membuat
     * lini berhenti — jadi ketiganya ditampilkan, bukan hanya harganya.
     *
     * @return array<int, array<string, mixed>>
     */
    public function comparison(?int $itemId = null): array
    {
        $rows = DB::table('prc_quot_det as d')
            ->join('prc_quot_main as m', 'm.id', '=', 'd.main_id')
            ->join('m_item as i', 'i.id', '=', 'd.item_id')
            ->join('m_contacts as v', 'v.id', '=', 'm.ven_id')
            ->whereIn('m.status', ['RECEIVED', 'SELECTED'])
            ->when($itemId, fn ($q) => $q->where('d.item_id', $itemId))
            ->orderBy('d.item_id')->orderBy('d.price')
            ->get([
                'd.id as quot_det_id', 'd.item_id', 'd.price', 'd.moq', 'd.order_lot',
                'd.lead_time_days', 'd.selected',
                'm.id as quot_id', 'm.code as quot_code', 'm.date', 'm.valid_to', 'm.status',
                'v.id as ven_id', 'v.company_n as vendor',
                'i.code as item_code', 'i.part_name',
            ]);

        $today = now()->startOfDay();

        return $rows->groupBy('item_id')->map(function ($lines, $itemId) use ($today) {
            $live = $lines->filter(fn ($l) => ! $l->valid_to || $today->lte(Carbon::parse($l->valid_to)));
            $cheapest = $live->sortBy('price')->first();
            $fastest = $live->sortBy('lead_time_days')->first();

            return [
                'item_id' => (int) $itemId,
                'item_code' => $lines->first()->item_code,
                'part_name' => $lines->first()->part_name,
                'quotes' => $lines->map(fn ($l) => [
                    'quot_det_id' => (int) $l->quot_det_id,
                    'quot_id' => (int) $l->quot_id,
                    'quot_code' => $l->quot_code,
                    'ven_id' => (int) $l->ven_id,
                    'vendor' => $l->vendor,
                    'price' => (float) $l->price,
                    'moq' => (int) $l->moq,
                    'order_lot' => (int) $l->order_lot,
                    'lead_time_days' => (int) $l->lead_time_days,
                    'valid_to' => $l->valid_to,
                    'expired' => $l->valid_to && $today->gt(Carbon::parse($l->valid_to)),
                    'selected' => (bool) $l->selected,
                    'is_cheapest' => $cheapest && (int) $l->quot_det_id === (int) $cheapest->quot_det_id,
                    'is_fastest' => $fastest && (int) $l->quot_det_id === (int) $fastest->quot_det_id,
                ])->values(),
            ];
        })->values()->all();
    }

    /**
     * Pilih satu penawaran menjadi syarat beli material itu.
     *
     * Satu material satu pemenang: penawaran vendor lain untuk material yang
     * sama otomatis kehilangan tandanya, dan master syarat beli untuk vendor
     * yang kalah diturunkan prioritasnya — bukan dihapus, karena ia tetap
     * cadangan yang sah bila yang menang kehabisan stok.
     */
    public function select(prc_quot_det $line, int $userId): m_supplier_item
    {
        $quot = prc_quot_main::findOrFail($line->main_id);

        if ($quot->status === 'REJECTED') {
            throw BizException::make('QUOT_REJECTED', 'Penawaran ini sudah ditolak.');
        }

        if ($quot->isExpired()) {
            throw BizException::make(
                'QUOT_EXPIRED',
                "Penawaran {$quot->code} sudah lewat masa berlaku ({$quot->valid_to->toDateString()})."
            );
        }

        // Kontrak yang sedang berjalan mengunci harganya; penawaran lepas tidak
        // boleh menggantikan kesepakatan yang sudah ditandatangani.
        if ($contract = $this->runningContractFor((int) $line->item_id)) {
            throw BizException::make(
                'QUOT_UNDER_CONTRACT',
                "Material ini terikat kontrak {$contract->code} sampai {$contract->valid_to->toDateString()}. "
                .'Batalkan atau tunggu kontraknya berakhir sebelum memakai penawaran lain.'
            );
        }

        return DB::transaction(function () use ($line, $quot) {
            // Pemenang sebelumnya untuk material yang sama kehilangan tandanya.
            prc_quot_det::where('item_id', $line->item_id)
                ->where('id', '<>', $line->id)
                ->update(['selected' => false, 'selected_at' => null]);

            $line->update(['selected' => true, 'selected_at' => now()]);
            $quot->update(['status' => 'SELECTED']);

            // Vendor lain turun ke prioritas cadangan.
            m_supplier_item::where('item_id', $line->item_id)
                ->where('ven_id', '<>', $quot->ven_id)
                ->update(['priority' => 2]);

            $terms = m_supplier_item::updateOrCreate(
                ['ven_id' => $quot->ven_id, 'item_id' => $line->item_id],
                [
                    'priority' => 1,
                    'price' => $line->price,
                    'currency_id' => $quot->currency_id,
                    'moq' => $line->moq,
                    'order_lot' => $line->order_lot,
                    'lead_time_days' => $line->lead_time_days,
                    'valid_from' => $quot->valid_from,
                    'valid_to' => $quot->valid_to,
                    'active' => true,
                    'quot_det_id' => $line->id,
                    'contract_id' => null,
                ]
            );

            AuditLogger::record(
                request(),
                "Pilih penawaran {$quot->code} untuk item #{$line->item_id}: harga {$line->price}, lead time {$line->lead_time_days} hari",
                $quot->code
            );

            return $terms;
        });
    }

    /**
     * Aktifkan kontrak: harganya menjadi syarat beli selama masa berlakunya.
     *
     * Kontrak menang atas penawaran lepas — itulah gunanya ditandatangani.
     * Karena itu master syarat beli yang dihasilkannya membawa `contract_id`,
     * dan pemilihan penawaran untuk material yang sama ditolak selama kontrak
     * masih berjalan.
     */
    public function activateContract(prc_contract_main $contract, int $userId): int
    {
        if ($contract->status !== 'DRAFT') {
            throw BizException::make('CONTRACT_STATE', 'Hanya kontrak berstatus DRAFT yang dapat diaktifkan.');
        }

        $contract->load('detail');

        if ($contract->detail->isEmpty()) {
            throw BizException::make('CONTRACT_EMPTY', 'Kontrak tanpa baris material tidak dapat diaktifkan.');
        }

        if ($contract->valid_to->lt($contract->valid_from)) {
            throw BizException::make('CONTRACT_PERIOD', 'Masa berlaku kontrak terbalik: tanggal akhir mendahului tanggal mulai.');
        }

        // Satu material tidak boleh terikat dua kontrak berjalan sekaligus —
        // harga mana yang berlaku menjadi pertanyaan tanpa jawaban.
        $clash = DB::table('prc_contract_det as d')
            ->join('prc_contract_main as m', 'm.id', '=', 'd.main_id')
            ->where('m.status', 'ACTIVE')
            ->where('m.id', '<>', $contract->id)
            ->whereIn('d.item_id', $contract->detail->pluck('item_id'))
            ->where('m.valid_from', '<=', $contract->valid_to->toDateString())
            ->where('m.valid_to', '>=', $contract->valid_from->toDateString())
            ->join('m_item as i', 'i.id', '=', 'd.item_id')
            ->pluck('i.code');

        if ($clash->isNotEmpty()) {
            throw BizException::make(
                'CONTRACT_OVERLAP',
                'Material berikut sudah terikat kontrak lain pada periode yang sama: '.$clash->implode(', ').'.'
            );
        }

        return DB::transaction(function () use ($contract) {
            $n = 0;

            foreach ($contract->detail as $line) {
                m_supplier_item::where('item_id', $line->item_id)
                    ->where('ven_id', '<>', $contract->ven_id)
                    ->update(['priority' => 2]);

                m_supplier_item::updateOrCreate(
                    ['ven_id' => $contract->ven_id, 'item_id' => $line->item_id],
                    [
                        'priority' => 1,
                        'price' => $line->price,
                        'moq' => $line->moq,
                        'order_lot' => $line->order_lot,
                        'lead_time_days' => $line->lead_time_days,
                        'valid_from' => $contract->valid_from,
                        'valid_to' => $contract->valid_to,
                        'active' => true,
                        'contract_id' => $contract->id,
                    ]
                );
                $n++;
            }

            $contract->update(['status' => 'ACTIVE']);
            AuditLogger::record(request(), "Aktifkan kontrak {$contract->code}: {$n} material", $contract->code);

            return $n;
        });
    }

    /**
     * Tutup kontrak yang masa berlakunya habis.
     *
     * Dijalankan harian. Syarat beli yang lahir darinya tidak dihapus — harga
     * terakhir tetap yang paling masuk akal dipakai sampai ada penawaran baru —
     * tetapi tautan kontraknya dilepas supaya penawaran lepas boleh menggantikan.
     */
    public function expireContracts(): int
    {
        $expired = prc_contract_main::where('status', 'ACTIVE')
            ->whereDate('valid_to', '<', now()->toDateString())
            ->get();

        foreach ($expired as $contract) {
            DB::transaction(function () use ($contract) {
                m_supplier_item::where('contract_id', $contract->id)->update(['contract_id' => null]);
                $contract->update(['status' => 'EXPIRED']);
            });
        }

        return $expired->count();
    }

    /** Kontrak aktif yang sedang mengikat sebuah material. */
    public function runningContractFor(int $itemId): ?prc_contract_main
    {
        $id = DB::table('prc_contract_det as d')
            ->join('prc_contract_main as m', 'm.id', '=', 'd.main_id')
            ->where('d.item_id', $itemId)
            ->where('m.status', 'ACTIVE')
            ->whereDate('m.valid_from', '<=', now()->toDateString())
            ->whereDate('m.valid_to', '>=', now()->toDateString())
            ->value('m.id');

        return $id ? prc_contract_main::find($id) : null;
    }
}
