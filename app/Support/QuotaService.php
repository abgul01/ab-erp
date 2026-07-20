<?php

namespace App\Support;

use App\Models\m_quota;
use App\Models\prc_quota_txn;

/**
 * Import quota ledger (LLD / PRD 5.x). Balance is derived from a signed
 * transaction log so every reservation/receipt is auditable:
 *
 *   available_ton = m_quota.total_ton + Σ(prc_quota_txn.ton × sign)
 *
 * ref_type ∈ PO_RESERVE (−) | GR_ACTUAL (−) | RELEASE (+) | ADJUST (±).
 * `ton` is stored as a positive magnitude; `sign` carries the direction.
 */
class QuotaService
{
    /** Available tonnage left on a quota. */
    public function balance(int $quotaId): float
    {
        $quota = m_quota::findOrFail($quotaId);
        $delta = (float) prc_quota_txn::where('quota_id', $quotaId)
            ->selectRaw('COALESCE(SUM(ton * sign), 0) AS d')->value('d');

        return round((float) $quota->total_ton + $delta, 3);
    }

    /** Open (not-yet-released) reservation tonnage for a given PO. */
    public function openReserve(int $quotaId, int $poId): float
    {
        $reserved = (float) prc_quota_txn::where('quota_id', $quotaId)
            ->where('ref_type', 'PO_RESERVE')->where('ref_id', $poId)->sum('ton');
        $released = (float) prc_quota_txn::where('quota_id', $quotaId)
            ->where('ref_type', 'RELEASE')->where('ref_id', $poId)->sum('ton');

        return round($reserved - $released, 3);
    }

    public function record(int $quotaId, string $refType, int $refId, float $ton, int $sign, ?int $userId, ?string $note = null): void
    {
        prc_quota_txn::create([
            'quota_id' => $quotaId,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'ton' => round(abs($ton), 3),
            'sign' => $sign < 0 ? -1 : 1,
            'note' => $note,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }

    /** Reserve estimated tonnage when an import RM PO is approved. */
    public function reservePo(int $quotaId, int $poId, float $ton, ?int $userId): void
    {
        if ($ton > 0) {
            $this->record($quotaId, 'PO_RESERVE', $poId, $ton, -1, $userId, 'PO reserve');
        }
    }

    /** Release any still-open reservation for a PO (cancel / supersede). */
    public function releasePo(int $quotaId, int $poId, ?int $userId): void
    {
        $open = $this->openReserve($quotaId, $poId);
        if ($open > 0) {
            $this->record($quotaId, 'RELEASE', $poId, $open, 1, $userId, 'PO reserve released');
        }
    }

    /**
     * Book actual received tonnage at GR time (ref = GR id, so a GR reversal
     * simply deletes its GR_ACTUAL rows). The PO's estimate reservation stays
     * open until the PO is closed/cancelled (conservative: never over-issues).
     */
    public function bookActual(int $quotaId, int $grId, float $ton, ?int $userId): void
    {
        if ($ton > 0) {
            $this->record($quotaId, 'GR_ACTUAL', $grId, $ton, -1, $userId, 'GR actual');
        }
    }

    /** Remove the actual-consumption rows booked by a GR (reversal). */
    public function reverseActual(int $grId): void
    {
        prc_quota_txn::where('ref_type', 'GR_ACTUAL')->where('ref_id', $grId)->delete();
    }
}
