<?php

namespace App\Support;

use App\Exceptions\BizException;
use Illuminate\Support\Facades\DB;

/**
 * Downgrade FG to raw material via m_fg_downgrade_map.
 *
 * Issues source lot via tr_out_fg_det. No new FG lot created —
 * the material side records via a serial service entry separately.
 *
 * LLD §5.7
 */
class FgDowngradeService
{
    /**
     * Downgrade qty from an FG lot to its mapped material item.
     *
     * @return array{material_item_id: int, qty: int, value_diff: float}
     */
    public function downgrade(int $detId, int $qty): array
    {
        $det = DB::table('tr_inc_fg_det')->find($detId);
        if (! $det || $det->status !== 'ACTIVE') {
            throw BizException::make('FG_LOT', 'Lot tidak ditemukan atau tidak aktif.');
        }

        $map = DB::table('m_fg_downgrade_map')
            ->where('fg_item_id', $det->item_id)
            ->first();
        if (! $map) {
            throw BizException::make('FG_NO_MAP', "Mapping downgrade untuk item #{$det->item_id} tidak ditemukan.");
        }

        // Remaining qty on source lot
        $issued = (int) DB::table('tr_out_fg_det')
            ->where('fg_code', $det->code)
            ->sum('qty');
        $remaining = (int) $det->qty - $issued;
        if ($remaining < $qty) {
            throw BizException::make('FG_QTY', "Sisa lot ({$remaining}) tidak mencukupi.");
        }

        $materialItem = DB::table('m_item')->find($map->material_item_id);
        if (! $materialItem) {
            throw BizException::make('FG_MAT_ITEM', "Material item #{$map->material_item_id} tidak ditemukan.");
        }

        $materialCostKg = (float) DB::table('prc_po_detail')
            ->where('item_id', $map->material_item_id)
            ->where('price_kg', '>', 0)
            ->orderByDesc('id')
            ->value('price_kg') ?: 15000;

        $materialWeight = $materialItem->weight ? (float) $materialItem->weight : 1;
        $fgUnitCost = (float) ($det->unit_cost ?? 0);
        $fgValue = $fgUnitCost * $qty;
        $materialValue = $materialCostKg * $materialWeight * $qty;
        $valueDiff = round($fgValue - $materialValue, 2);

        return DB::transaction(function () use ($det, $qty, $map, $valueDiff) {
            $outMainId = DB::table('tr_out_fg_main')->insertGetId([
                'code' => 'DNG-OUT-'.now()->format('YmdHis'),
                'code_do' => 'DOWNGRADE',
                'date' => now()->toDateString(),
                'user_id' => (string) auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('tr_out_fg_det')->insert([
                'main_id' => $outMainId,
                'item_id' => $det->item_id,
                'fg_code' => $det->code,
                'code' => 'DNG-'.$det->code,
                'qty' => $qty,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Mark source lot as CONSUMED if fully issued
            $newTotalIssued = $issued + $qty;
            if ((int) $det->qty <= $newTotalIssued) {
                DB::table('tr_inc_fg_det')->where('id', $det->id)->update([
                    'status' => 'CONSUMED',
                    'updated_at' => now(),
                ]);
            }

            return [
                'material_item_id' => (int) $map->material_item_id,
                'qty' => $qty,
                'value_diff' => $valueDiff,
            ];
        });
    }

    /** Active downgrade mappings. */
    public function mappings(): array
    {
        return DB::table('m_fg_downgrade_map as m')
            ->join('m_item as fg', 'fg.id', '=', 'm.fg_item_id')
            ->join('m_item as mat', 'mat.id', '=', 'm.material_item_id')
            ->get(['m.*', 'fg.code as fg_code', 'fg.part_name as fg_name',
                'mat.code as mat_code', 'mat.part_name as mat_name'])
            ->all();
    }
}
