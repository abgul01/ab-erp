<?php

namespace App\Support;

use App\Exceptions\BizException;
use App\Models\m_bom;
use App\Models\m_bom_det_pm;
use App\Models\m_bom_det_rm;
use Illuminate\Support\Facades\DB;

/**
 * BOM utilities: WhereUsed, Copy, Compare.
 *
 * LLD §4.3
 */
class BomToolService
{
    /**
     * WhereUsed — find all BOMs that reference a given material (RM or PM).
     */
    public function whereUsed(int $itemId): array
    {
        $asRM = DB::table('m_bom_det_rm as d')
            ->join('m_bom as b', 'b.id', '=', 'd.id_prim')
            ->join('m_item as i', 'i.id', '=', 'b.item_id')
            ->where('d.mat_id', $itemId)
            ->select('b.id as bom_id', 'i.id as item_id', 'i.code as item_code', 'i.part_name', DB::raw("'RM' as role"))
            ->get();

        $asPM = DB::table('m_bom_det_pm as d')
            ->join('m_bom as b', 'b.id', '=', 'd.id_prim')
            ->join('m_item as i', 'i.id', '=', 'b.item_id')
            ->where('d.pm_id', $itemId)
            ->select('b.id as bom_id', 'i.id as item_id', 'i.code as item_code', 'i.part_name', DB::raw("'PM' as role"))
            ->get();

        return $asRM->merge($asPM)->sortBy('item_code')->values()->all();
    }

    /**
     * Copy a BOM to a new item.
     */
    public function copy(int $sourceBomId, int $targetItemId): m_bom
    {
        $source = m_bom::with('rmLines', 'pmLines')->findOrFail($sourceBomId);

        if (m_bom::where('item_id', $targetItemId)->where('active', 1)->exists()) {
            throw BizException::make('BOM_EXISTS', 'Target item sudah punya BOM aktif.');
        }

        return DB::transaction(function () use ($source, $targetItemId) {
            $newBom = m_bom::create([
                'item_id' => $targetItemId,
                'active' => 1,
            ]);

            foreach ($source->rmLines as $rm) {
                m_bom_det_rm::create([
                    'id_prim' => $newBom->id,
                    'mat_id' => $rm->mat_id,
                    'length_cut' => $rm->length_cut,
                    'length_use' => $rm->length_use,
                    'priority' => $rm->priority,
                ]);
            }

            foreach ($source->pmLines as $pm) {
                m_bom_det_pm::create([
                    'id_prim' => $newBom->id,
                    'pm_id' => $pm->pm_id,
                    'priority' => $pm->priority,
                ]);
            }

            return $newBom->load('rmLines.material', 'pmLines');
        });
    }

    /**
     * Compare two BOMs — show differences in RM and PM lines.
     */
    public function compare(int $bomIdA, int $bomIdB): array
    {
        $a = m_bom::with('rmLines.material', 'pmLines')->findOrFail($bomIdA);
        $b = m_bom::with('rmLines.material', 'pmLines')->findOrFail($bomIdB);

        $rmA = $a->rmLines->keyBy('mat_id');
        $rmB = $b->rmLines->keyBy('mat_id');

        $rmDiff = [];
        foreach ($rmA as $matId => $line) {
            if (! isset($rmB[$matId])) {
                $rmDiff[] = ['type' => 'REMOVED', 'mat_id' => $matId, 'material' => $line->material?->code, 'length_cut' => $line->length_cut, 'length_use' => $line->length_use];
            } elseif ($line->length_cut !== $rmB[$matId]->length_cut || $line->length_use !== $rmB[$matId]->length_use) {
                $rmDiff[] = ['type' => 'CHANGED', 'mat_id' => $matId, 'material' => $line->material?->code, 'a' => ['length_cut' => $line->length_cut, 'length_use' => $line->length_use], 'b' => ['length_cut' => $rmB[$matId]->length_cut, 'length_use' => $rmB[$matId]->length_use]];
            }
        }
        foreach ($rmB as $matId => $line) {
            if (! isset($rmA[$matId])) {
                $rmDiff[] = ['type' => 'ADDED', 'mat_id' => $matId, 'material' => $line->material?->code, 'length_cut' => $line->length_cut, 'length_use' => $line->length_use];
            }
        }

        $pmA = $a->pmLines->keyBy('pm_id');
        $pmB = $b->pmLines->keyBy('pm_id');
        $pmDiff = [];
        foreach ($pmA as $pmId => $line) {
            if (! isset($pmB[$pmId])) {
                $pmDiff[] = ['type' => 'REMOVED', 'pm_id' => $pmId];
            }
        }
        foreach ($pmB as $pmId => $line) {
            if (! isset($pmA[$pmId])) {
                $pmDiff[] = ['type' => 'ADDED', 'pm_id' => $pmId];
            }
        }

        return [
            'bom_a' => ['id' => $a->id, 'item_id' => $a->item_id],
            'bom_b' => ['id' => $b->id, 'item_id' => $b->item_id],
            'rm_differences' => $rmDiff,
            'pm_differences' => $pmDiff,
        ];
    }
}
