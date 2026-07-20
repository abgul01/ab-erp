<?php

namespace App\Http\Controllers\Api\Wms;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stok RM (read-only). On-hand terdiri dari:
 *  - FULL    : serial incoming (wh_inc_detail) yang belum pernah outgoing.
 *  - REMNANT : sisa tankan (wh_rem_detail) terbaru yang belum keluar lagi.
 * Berat FULL diambil dari prc_gr_serial; REMNANT memakai berat sisa tercatat.
 */
class StockRmController extends Controller
{
    private function fullBars()
    {
        return DB::table('wh_inc_detail as wd')
            ->join('wh_inc_main as wm', 'wm.id', '=', 'wd.id_prim')
            ->leftJoin('prc_gr_main as g', 'g.id', '=', 'wm.gr_id')
            ->leftJoin('prc_gr_serial as gs', 'gs.serial_id', '=', 'wd.serial_id')
            ->whereNotIn('wd.serial_id', DB::table('wh_out_detail')->select('serial_id'))
            ->select([
                DB::raw('CONVERT(wd.serial_id USING utf8mb4) COLLATE utf8mb4_general_ci as serial_id'),
                'wd.item_id', 'wd.qty', 'wd.length', 'wd.rack_id',
                DB::raw('gs.weight as weight'),
                DB::raw('CONVERT(gs.millsheet USING utf8mb4) COLLATE utf8mb4_general_ci as millsheet'),
                DB::raw("'FULL' as source"),
                DB::raw('CONVERT(g.code USING utf8mb4) COLLATE utf8mb4_general_ci as ref_code'),
                DB::raw('wm.date as doc_date'),
            ]);
    }

    private function remnants()
    {
        return DB::table('wh_rem_detail as rd')
            ->join('wh_rem_main as rm', 'rm.id', '=', 'rd.id_prim')
            // Belum dikeluarkan lagi: tidak ada outgoing dengan id LEBIH BARU
            // dari outgoing asal remnant ini (rm.out_id). Berbasis id, bukan waktu.
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('wh_out_detail as od')
                    ->join('wh_out_main as om', 'om.id', '=', 'od.id_prim')
                    ->whereColumn('od.serial_id', 'rd.serial_id')
                    ->whereColumn('om.id', '>', 'rm.out_id');
            })
            // Hanya baris tankan terbaru per serial.
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('wh_rem_detail as rd2')
                    ->whereColumn('rd2.serial_id', 'rd.serial_id')
                    ->whereColumn('rd2.id', '>', 'rd.id');
            })
            ->select([
                DB::raw('CONVERT(rd.serial_id USING utf8mb4) COLLATE utf8mb4_general_ci as serial_id'),
                'rm.item_id', DB::raw('rd.rem_count as qty'), 'rd.length', 'rd.rack_id',
                DB::raw('rd.weight as weight'),
                DB::raw('CAST(NULL AS CHAR) COLLATE utf8mb4_general_ci as millsheet'),
                DB::raw("'REMNANT' as source"),
                DB::raw('CONVERT(rm.code USING utf8mb4) COLLATE utf8mb4_general_ci as ref_code'),
                DB::raw('rm.date as doc_date'),
            ]);
    }

    private function onhand()
    {
        return DB::query()->fromSub($this->fullBars()->unionAll($this->remnants()), 's')
            ->join('m_item as i', 'i.id', '=', 's.item_id')
            ->leftJoin('m_rack as r', 'r.id', '=', 's.rack_id');
    }

    public function index(Request $request)
    {
        $query = $this->onhand()->select([
            's.serial_id', 's.qty', 's.length', 's.weight', 's.millsheet', 's.source', 's.ref_code', 's.doc_date',
            'i.id as item_id', 'i.code as item_code', 'i.part_name',
            'r.id as rack_id', 'r.location as rack',
        ]);

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(fn ($s) => $s->where('s.serial_id', 'like', "%{$q}%")
                ->orWhere('i.code', 'like', "%{$q}%")
                ->orWhere('i.part_name', 'like', "%{$q}%"));
        }
        if ($itemId = $request->query('item_id')) {
            $query->where('s.item_id', $itemId);
        }
        if ($rackId = $request->query('rack_id')) {
            $query->where('s.rack_id', $rackId);
        }
        if ($source = $request->query('source')) {
            $query->where('s.source', $source);
        }

        $query->orderBy('s.serial_id');

        return ApiResponse::paginated($query->paginate(min(max((int) $request->query('per_page', 20), 1), 200)));
    }

    /** Agregat per item: serial, pcs, panjang, berat (FULL + REMNANT). */
    public function summary()
    {
        $rows = $this->onhand()
            ->groupBy('i.id', 'i.code', 'i.part_name')
            ->select([
                'i.id as item_id', 'i.code as item_code', 'i.part_name',
                DB::raw('COUNT(*) as serial_count'),
                DB::raw('COALESCE(SUM(s.qty),0) as total_qty'),
                DB::raw('COALESCE(SUM(s.length * s.qty),0) as total_length'),
                DB::raw('COALESCE(SUM(s.weight),0) as total_weight'),
                DB::raw("SUM(CASE WHEN s.source='REMNANT' THEN 1 ELSE 0 END) as remnant_count"),
            ])
            ->orderBy('i.code')
            ->get();

        return ApiResponse::collection($rows);
    }
}
