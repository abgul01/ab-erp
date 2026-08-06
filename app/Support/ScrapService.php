<?php

namespace App\Support;

use App\Exceptions\BizException;
use App\Models\prd_scrap_decision;
use App\Models\prd_wo_serial_rm;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Scrap evaluation for leftover bar length.
 *
 * A booked serial becomes a scrap candidate once its remaining length falls
 * below the shortest cut any active BOM asks of that material — below that it
 * cannot feed any product, so keeping it on the rack only costs space.
 *
 * The flag is a proposal, never a verdict: PRD §5 gives a warehouse supervisor
 * the last word in both directions, and every override is recorded with its
 * reason in prd_scrap_decisions.
 *
 * LLD §5.4
 */
class ScrapService
{
    /** Re-check one serial after its remaining length changed. */
    public function evaluate(prd_wo_serial_rm $serial): bool
    {
        $itemId = $serial->detail?->rm_id;
        if (! $itemId) {
            return false;
        }

        $isCandidate = $this->isCandidate((float) $serial->length_rem, (int) $itemId);

        // Only ever raise the flag automatically. A supervisor who marked a
        // short piece usable should not have that undone by the next issue.
        if ($isCandidate && ! $serial->scrap) {
            $serial->update(['scrap' => 1]);
        }

        return $isCandidate;
    }

    public function isCandidate(float $lengthRem, int $itemId): bool
    {
        $min = $this->minBomLength($itemId);

        return $min !== null && $lengthRem > 0 && $lengthRem < $min;
    }

    /**
     * Serials waiting for a human call: flagged by the rule, or short enough to
     * qualify but not yet flagged.
     */
    public function candidates(?int $woId = null): array
    {
        $rows = DB::table('prd_wo_serial_rm as s')
            ->join('prd_wo_detail_rm as d', 'd.id', '=', 's.detail_id')
            ->join('prd_wo_main as wo', 'wo.id', '=', 'd.main_id')
            ->leftJoin('m_item as i', 'i.id', '=', 'd.rm_id')
            ->where('s.length_rem', '>', 0)
            ->when($woId, fn ($q) => $q->where('wo.id', $woId))
            ->orderBy('s.length_rem')
            ->limit(500)
            ->get([
                's.id', 's.serial_id', 's.length_asal', 's.length_rem', 's.scrap', 's.note',
                'd.rm_id', 'i.code as item_code', 'i.part_name as item_name',
                'wo.id as wo_id', 'wo.code as wo_code',
            ]);

        $lastByserial = prd_scrap_decision::whereIn('wo_serial_rm_id', $rows->pluck('id'))
            ->orderBy('id')
            ->get()
            ->keyBy('wo_serial_rm_id');

        return $rows->map(function ($r) use ($lastByserial) {
            $min = $this->minBomLength((int) $r->rm_id);
            $r->min_bom_length = $min;
            $r->auto_candidate = $min !== null && (float) $r->length_rem < $min;
            $r->last_decision = $lastByserial[$r->id]->decision ?? null;
            $r->last_reason = $lastByserial[$r->id]->reason ?? null;

            return $r;
        })
            ->filter(fn ($r) => $r->auto_candidate || $r->scrap)
            ->values()
            ->all();
    }

    /** Record a supervisor's call, in either direction, with its reason. */
    public function decide(prd_wo_serial_rm $serial, string $to, string $reason, User $user): prd_scrap_decision
    {
        if (! in_array($to, ['SCRAP', 'USABLE'], true)) {
            throw BizException::make('SCRAP_DECISION', 'Keputusan harus SCRAP atau USABLE.');
        }
        if (trim($reason) === '') {
            throw BizException::make('SCRAP_REASON', 'Alasan keputusan wajib diisi.');
        }

        $itemId = (int) ($serial->detail?->rm_id ?? 0);
        $min = $itemId ? $this->minBomLength($itemId) : null;

        return DB::transaction(function () use ($serial, $to, $reason, $user, $itemId, $min) {
            $decision = prd_scrap_decision::create([
                'serial_id' => $serial->serial_id,
                'wo_serial_rm_id' => $serial->id,
                'wo_id' => $serial->detail?->main_id,
                'item_id' => $itemId ?: null,
                'length_rem' => $serial->length_rem,
                'min_bom_length' => $min,
                'decision' => $to,
                'reason' => $reason,
                'auto_flag' => $this->isCandidate((float) $serial->length_rem, $itemId ?: 0),
                'decided_by' => $user->id,
                'created_at' => now(),
            ]);

            $serial->update([
                'scrap' => $to === 'SCRAP' ? 1 : 0,
                'note' => "{$to}: {$reason}",
            ]);

            return $decision;
        });
    }

    /** Decision history for one serial. */
    public function history(int $woSerialRmId): array
    {
        return prd_scrap_decision::where('wo_serial_rm_id', $woSerialRmId)
            ->orderByDesc('id')->get()->all();
    }

    /**
     * Shortest cut any active BOM demands of this material. Null when the
     * material is not on any BOM — nothing to compare against, so nothing is
     * ever proposed as scrap.
     */
    public function minBomLength(int $itemId): ?float
    {
        return Cache::remember("min_bom_len:{$itemId}", 3600, function () use ($itemId) {
            $min = DB::table('m_bom_det_rm as d')
                ->join('m_bom as b', 'b.id', '=', 'd.id_prim')
                ->where('d.mat_id', $itemId)
                ->where('b.active', 1)
                ->selectRaw('MIN(LEAST(COALESCE(NULLIF(d.length_use,0), d.length_cut), COALESCE(NULLIF(d.length_cut,0), d.length_use))) as m')
                ->value('m');

            return $min === null ? null : (float) $min;
        });
    }

    public function invalidateMinLen(int $itemId): void
    {
        Cache::forget("min_bom_len:{$itemId}");
    }
}
