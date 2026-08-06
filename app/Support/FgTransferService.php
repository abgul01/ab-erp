<?php

namespace App\Support;

use App\Exceptions\BizException;
use Illuminate\Support\Facades\DB;

/**
 * Transfer FG items between spec_groups (reclassification).
 *
 * Source/target lots use existing tr_inc_fg_det. Transfer-out creates a
 * tr_out_fg_det entry; transfer-in creates a new tr_inc_fg_det entry.
 *
 * LLD §5.7
 */
class FgTransferService
{
    /**
     * Transfer qty from an existing FG lot to a different item (same spec_group).
     *
     * @return array{from_det_id: int, to_det_id: int}
     */
    public function transfer(int $fromDetId, int $toItemId, int $qty): array
    {
        $fromDet = DB::table('tr_inc_fg_det')->find($fromDetId);
        if (! $fromDet || $fromDet->status !== 'ACTIVE') {
            throw BizException::make('FG_LOT', 'Lot asal tidak ditemukan atau tidak aktif.');
        }

        $fromItem = DB::table('m_item')->find($fromDet->item_id);
        $toItem = DB::table('m_item')->find($toItemId);
        if (! $fromItem || ! $toItem) {
            throw BizException::make('FG_ITEM', 'Item tidak ditemukan.');
        }

        if ((string) $fromItem->spec_group !== (string) $toItem->spec_group) {
            throw BizException::make('FG_SPEC_GROUP', 'Spec group tidak cocok. Transfer hanya antar item spec_group sama.');
        }

        // Compute remaining qty on source lot
        $issued = (int) DB::table('tr_out_fg_det')
            ->where('fg_code', $fromDet->code)
            ->sum('qty');
        $remaining = (int) $fromDet->qty - $issued;
        if ($remaining < $qty) {
            throw BizException::make('FG_QTY', "Sisa lot ({$remaining}) tidak mencukupi.");
        }

        return DB::transaction(function () use ($fromDet, $toItemId, $qty) {
            // 1) Issue from source lot → tr_out_fg_det
            $outMainId = DB::table('tr_out_fg_main')->insertGetId([
                'code' => 'TRF-OUT-'.now()->format('YmdHis'),
                'code_do' => 'TRANSFER',
                'date' => now()->toDateString(),
                'user_id' => (string) auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('tr_out_fg_det')->insert([
                'main_id' => $outMainId,
                'item_id' => $fromDet->item_id,
                'fg_code' => $fromDet->code,
                'code' => 'TRF-OUT-'.$fromDet->code,
                'qty' => $qty,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2) Receive into target item → tr_inc_fg_main + tr_inc_fg_det
            $inMainId = DB::table('tr_inc_fg_main')->insertGetId([
                'code' => 'TRF-IN-'.now()->format('YmdHis'),
                'date' => now()->toDateString(),
                'user_id' => (string) auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $newCode = 'TRF-'.$fromDet->code.'-'.now()->format('YmdHis');
            $toDetId = DB::table('tr_inc_fg_det')->insertGetId([
                'code' => $newCode,
                'main_id' => $inMainId,
                'pal_pro_code' => 'TRANSFER',
                'item_id' => $toItemId,
                'cut_id' => 0,
                'qty' => $qty,
                'wip_id' => $fromDet->wip_id,
                'unit_cost' => $fromDet->unit_cost ?? 0,
                'source' => 'FG_TRANSFER',
                'parent_lot_id' => $fromDet->id,
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['from_det_id' => $fromDet->id, 'to_det_id' => $toDetId];
        });
    }

    /**
     * Lots available for transfer (ACTIVE, remaining > 0).
     */
    public function availableLots(?int $itemId = null): array
    {
        $issued = DB::table('tr_out_fg_det')
            ->select('fg_code', DB::raw('SUM(qty) as used'))
            ->groupBy('fg_code');

        $query = DB::table('tr_inc_fg_det as d')
            ->leftJoinSub($issued, 'o', 'o.fg_code', '=', 'd.code')
            ->where('d.status', 'ACTIVE');
        if ($itemId) {
            $query->where('d.item_id', $itemId);
        }

        return $query->orderBy('d.id')
            ->get(['d.*', DB::raw('COALESCE(o.used, 0) as used')])
            ->filter(fn ($r) => ((int) $r->qty - (int) ($r->used ?? 0)) > 0)
            ->values()
            ->all();
    }
}
