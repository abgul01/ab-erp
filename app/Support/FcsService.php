<?php

namespace App\Support;

use App\Exceptions\BizException;
use Illuminate\Support\Facades\DB;

/**
 * Final Check Sheet (FCS) — gate before RFG (Receiving Finished Goods).
 *
 * LLD §4.9, §5.6
 */
class FcsService
{
    /**
     * Create an FCS for a completed WO.
     */
    public function create(int $woId): int
    {
        $wo = DB::table('prd_wo_main')->find($woId);
        if (! $wo) {
            throw BizException::make('WO_NOT_FOUND', 'Work Order tidak ditemukan.');
        }

        $openCut = DB::table('tr_cut_main as cm')
            ->join('prd_wip as w', 'w.id', '=', 'cm.wip_id')
            ->where('w.wo_id', $woId)
            ->where('cm.status', '<>', 'COMPLETED')
            ->exists();
        if ($openCut) {
            throw BizException::make('FCS_OPEN_CUT', 'Masih ada cutting yang belum COMPLETED.');
        }

        $openPro = DB::table('tr_pro_main as pm')
            ->join('prd_wip as w', 'w.id', '=', 'pm.wip_id')
            ->where('w.wo_id', $woId)
            ->where('pm.status', '<>', 'COMPLETED')
            ->exists();
        if ($openPro) {
            throw BizException::make('FCS_OPEN_PRO', 'Masih ada processing yang belum COMPLETED.');
        }

        $openNG = DB::table('tr_ab_main as ab')
            ->join('prd_wip as w', 'w.id', '=', 'ab.wip_id')
            ->where('w.wo_id', $woId)
            ->where('ab.status', 'OPEN')
            ->exists();
        if ($openNG) {
            throw BizException::make('FCS_OPEN_NG', 'Masih ada NG item yang belum didisposisi.');
        }

        $serials = DB::table('wh_serial as s')
            ->join('prd_wip as w', 'w.id', '=', 's.wip_id')
            ->where('w.wo_id', $woId)
            ->whereIn('s.status', ['USED', 'PARTIAL', 'WIP'])
            ->get(['s.serial_no', 's.used_mm', 's.used_kg', 's.state']);

        $operations = DB::table('prd_route_time as rt')
            ->where('rt.wo_id', $woId)
            ->get(['rt.seq', 'rt.process_name', 'rt.user_id', 'rt.machine_id', 'rt.qty', 'rt.cycle_time_sec']);

        $traceability = json_encode([
            'serials' => $serials,
            'operations' => $operations,
            'wo_id' => $woId,
            'fg_item_id' => $wo->fg_id,
            'qty_planned' => $wo->qty,
            'generated_at' => now()->toIso8601String(),
        ]);

        $existing = DB::table('prd_fcs_main')->where('wo_id', $woId)->first();
        if ($existing) {
            throw BizException::make('FCS_EXISTS', "FCS untuk WO #{$woId} sudah ada (status: {$existing->status}).");
        }

        $id = DB::table('prd_fcs_main')->insertGetId([
            'wo_id' => $woId,
            'fg_item_id' => $wo->fg_id,
            'qty_planned' => $wo->qty,
            'qty_good' => $wo->qty,
            'status' => 'PENDING',
            'traceability' => $traceability,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * QC approve FCS → triggers FG receiving.
     */
    public function approve(int $fcsId): array
    {
        $fcs = DB::table('prd_fcs_main')->find($fcsId);
        if (! $fcs || $fcs->status !== 'PENDING') {
            throw BizException::make('FCS_STATE', 'FCS tidak ditemukan atau sudah diproses.');
        }

        $wo = DB::table('prd_wo_main')->find($fcs->wo_id);
        if (! $wo) {
            throw BizException::make('WO_NOT_FOUND', 'WO tidak ditemukan.');
        }

        return DB::transaction(function () use ($fcs, $wo) {
            DB::table('prd_fcs_main')->where('id', $fcs->id)->update([
                'status' => 'APPROVED',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'updated_at' => now(),
            ]);

            $lotCode = $wo->code.'-'.now()->format('YmdHis');

            // Create tr_inc_fg_main (RFG receipt document)
            $inMainId = DB::table('tr_inc_fg_main')->insertGetId([
                'code' => 'RFG-'.$lotCode,
                'date' => now()->toDateString(),
                'user_id' => (string) auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create tr_inc_fg_det (the actual lot)
            $fgLotId = DB::table('tr_inc_fg_det')->insertGetId([
                'code' => $lotCode,
                'main_id' => $inMainId,
                'pal_pro_code' => 'FCS',
                'item_id' => $fcs->fg_item_id,
                'cut_id' => 0,
                'qty' => $fcs->qty_good,
                'wip_id' => null,
                'unit_cost' => 0,
                'source' => 'FCS',
                'parent_lot_id' => null,
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('prd_wo_main')->where('id', $wo->id)->update([
                'status' => 'COMPLETED',
                'updated_at' => now(),
            ]);

            $trace = json_decode($fcs->traceability, true);
            $serialNos = collect($trace['serials'] ?? [])->pluck('serial_no');
            if ($serialNos->isNotEmpty()) {
                DB::table('wh_serial')
                    ->whereIn('serial_no', $serialNos->toArray())
                    ->where('status', 'USED')
                    ->update(['status' => 'CONSUMED', 'updated_at' => now()]);
            }

            return [
                'fcs_id' => $fcs->id,
                'fg_lot_id' => $fgLotId,
                'lot_code' => $lotCode,
                'wo_id' => $wo->id,
                'status' => 'APPROVED',
            ];
        });
    }

    /**
     * Reject FCS (back to WO for rework).
     */
    public function reject(int $fcsId, string $reason): void
    {
        $fcs = DB::table('prd_fcs_main')->find($fcsId);
        if (! $fcs || $fcs->status !== 'PENDING') {
            throw BizException::make('FCS_STATE', 'FCS tidak ditemukan atau sudah diproses.');
        }

        DB::table('prd_fcs_main')->where('id', $fcsId)->update([
            'status' => 'REJECTED',
            'notes' => $reason,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * List FCS records.
     */
    public function list(?string $status = null): array
    {
        $q = DB::table('prd_fcs_main as f')
            ->join('prd_wo_main as wo', 'wo.id', '=', 'f.wo_id')
            ->join('m_item as i', 'i.id', '=', 'f.fg_item_id')
            ->select('f.*', 'wo.code as wo_code', 'i.code as fg_code', 'i.part_name as fg_name');
        if ($status) {
            $q->where('f.status', $status);
        }

        return $q->orderByDesc('f.id')->get()->all();
    }

    /**
     * Get single FCS with traceability.
     */
    public function get(int $id): ?object
    {
        $fcs = DB::table('prd_fcs_main as f')
            ->join('prd_wo_main as wo', 'wo.id', '=', 'f.wo_id')
            ->join('m_item as i', 'i.id', '=', 'f.fg_item_id')
            ->select('f.*', 'wo.code as wo_code', 'i.code as fg_code', 'i.part_name as fg_name')
            ->where('f.id', $id)
            ->first();
        if ($fcs && $fcs->traceability) {
            $fcs->traceability_data = json_decode($fcs->traceability, true);
        }

        return $fcs;
    }

    /**
     * Completed WOs eligible for FCS (no existing FCS).
     */
    public function eligibleWOs(): array
    {
        return DB::table('prd_wo_main as wo')
            ->join('m_item as i', 'i.id', '=', 'wo.fg_id')
            ->leftJoin('prd_fcs_main as f', 'f.wo_id', '=', 'wo.id')
            ->whereNull('f.id')
            ->whereIn('wo.status', ['RELEASED', 'IN_PROGRESS', 'COMPLETED'])
            ->select('wo.id', 'wo.code', 'wo.qty', 'i.code as fg_code', 'i.part_name as fg_name', 'wo.status')
            ->get()->all();
    }
}
