<?php

namespace App\Support;

use App\Models\sys_alert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Operational warnings that need someone to act.
 *
 * Raising is idempotent per condition: the nightly check updates the existing
 * open alert rather than adding another. An inbox that grows by one row an hour
 * stops being read, and then the warning is worthless.
 *
 * A condition that has cleared resolves itself — nobody should have to tick off
 * a shortage that a delivery already fixed.
 */
class AlertService
{
    public const QUOTA = 'QUOTA';

    public const MIN_STOCK = 'MIN_STOCK';

    public const NPD_TASK = 'NPD_TASK';

    public const NPD_GATE = 'NPD_GATE';

    public const NPD_SOP = 'NPD_SOP';

    /** Warn below this share of quota remaining. */
    public const QUOTA_WARN_PCT = 20.0;

    /**
     * Gate yang menunggu lebih lama dari ini dianggap menggantung.
     *
     * Proyek pengembangan berhenti total selama gate belum diputuskan — dan yang
     * paling sering terjadi bukan penolakan, melainkan tidak ada yang membuka
     * inbox approval.
     */
    public const GATE_STALE_DAYS = 3;

    public function raise(
        string $type,
        string $refType,
        ?int $refId,
        string $title,
        string $message,
        string $severity = 'WARNING',
        ?float $value = null,
        ?float $threshold = null,
    ): sys_alert {
        return sys_alert::updateOrCreate(
            ['type' => $type, 'ref_type' => $refType, 'ref_id' => $refId],
            [
                'title' => $title,
                'message' => $message,
                'severity' => $severity,
                'value' => $value,
                'threshold' => $threshold,
                // Re-raising a condition that had been resolved reopens it.
                'resolved_at' => null,
                'resolved_by' => null,
            ]
        );
    }

    /** The condition no longer holds — close it quietly. */
    public function clear(string $type, string $refType, ?int $refId): void
    {
        sys_alert::where('type', $type)
            ->where('ref_type', $refType)
            ->where('ref_id', $refId)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }

    public function open(?string $type = null)
    {
        return sys_alert::whereNull('resolved_at')
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderByRaw("FIELD(severity, 'CRITICAL', 'WARNING', 'INFO')")
            ->orderByDesc('updated_at')
            ->get();
    }

    /**
     * Import quota balance.
     *
     * Balance is the allocation less what open POs have reserved and what
     * receipts have actually consumed — the same arithmetic the PO hard-block
     * uses, so the warning and the refusal never disagree.
     */
    public function checkQuota(): int
    {
        $raised = 0;

        $quotas = DB::table('m_quota')->where('active', 1)->get(['id', 'code', 'descrip', 'total_ton']);

        foreach ($quotas as $q) {
            $usedTon = (float) DB::table('prc_quota_txn')
                ->where('quota_id', $q->id)
                ->sum(DB::raw('ton * sign * -1'));

            $total = (float) $q->total_ton;
            $remaining = round($total - $usedTon, 3);
            $pct = $total > 0 ? round($remaining / $total * 100, 1) : 0.0;

            if ($total <= 0 || $pct > self::QUOTA_WARN_PCT) {
                $this->clear(self::QUOTA, 'm_quota', (int) $q->id);

                continue;
            }

            $this->raise(
                self::QUOTA,
                'm_quota',
                (int) $q->id,
                "Kuota impor {$q->code} tinggal {$pct}%",
                sprintf(
                    'Sisa %s ton dari %s ton. PO impor akan ditolak begitu saldo habis — atur tambahan kuota atau alihkan ke pembelian lokal.',
                    number_format($remaining, 3),
                    number_format($total, 3)
                ),
                $pct <= 5 ? 'CRITICAL' : 'WARNING',
                $remaining,
                $total,
            );
            $raised++;
        }

        return $raised;
    }

    /**
     * Proyek NPD yang perlu didorong.
     *
     * Tiga hal yang membuat proyek pengembangan diam tanpa ada yang menyadari:
     * task yang lewat tanggal, gate yang menunggu tanda tangan berhari-hari, dan
     * target SOP yang sudah terlampaui. Ketiganya tidak akan pernah muncul
     * sendiri di layar siapa pun — proyek NPD berjalan berbulan-bulan, dan tidak
     * ada yang membuka daftarnya setiap hari.
     *
     * Satu peringatan per proyek per jenis, bukan per task: PM yang punya enam
     * task telat butuh satu baris yang menyebut enam, bukan enam baris.
     */
    public function checkNpd(): int
    {
        $raised = 0;

        $projects = DB::table('npd_project')
            ->whereIn('status', ['RUNNING', 'ON_HOLD'])
            ->get(['id', 'code', 'name', 'current_phase_no', 'target_sop']);

        foreach ($projects as $p) {
            $raised += $this->checkNpdTasks($p);
            $raised += $this->checkNpdGate($p);
            $raised += $this->checkNpdSop($p);
        }

        // Proyek yang sudah selesai atau dibatalkan tidak boleh meninggalkan
        // peringatan yang menggantung selamanya.
        $this->clearNpdForClosedProjects();

        return $raised;
    }

    private function checkNpdTasks(object $p): int
    {
        $late = DB::table('npd_task as t')
            ->join('npd_project_phase as ph', 'ph.id', '=', 't.main_id')
            ->where('ph.main_id', $p->id)
            ->where('t.status', '<>', 'DONE')
            ->whereNotNull('t.planned_end')
            ->whereDate('t.planned_end', '<', now()->toDateString())
            ->get(['t.name', 't.planned_end']);

        if ($late->isEmpty()) {
            $this->clear(self::NPD_TASK, 'npd_project', (int) $p->id);

            return 0;
        }

        $oldest = $late->min('planned_end');
        $days = (int) Carbon::parse($oldest)->diffInDays(now()->startOfDay());

        $this->raise(
            self::NPD_TASK,
            'npd_project',
            (int) $p->id,
            "{$p->code}: {$late->count()} task NPD lewat tanggal",
            sprintf(
                '%s — paling lama "%s" sudah %d hari lewat rencana. Task yang menggantung menahan gate fase %d.',
                $p->name,
                $late->firstWhere('planned_end', $oldest)->name ?? $late->first()->name,
                $days,
                $p->current_phase_no
            ),
            $days >= 14 ? 'CRITICAL' : 'WARNING',
            $late->count(),
            0,
        );

        return 1;
    }

    private function checkNpdGate(object $p): int
    {
        /*
         * Gate yang menunggu diukur dari approval tertua yang belum diputuskan,
         * bukan dari tanggal pengajuan fase — pada gate dua level, level kedua
         * bisa saja baru menunggu sejak kemarin.
         */
        $pending = DB::table('approvals as a')
            ->join('npd_project_phase as ph', 'ph.id', '=', 'a.doc_id')
            ->where('a.doc_type', 'npd_project_phase')
            ->where('a.status', 'PENDING')
            ->where('ph.main_id', $p->id)
            ->orderBy('a.created_at')
            ->first(['a.created_at', 'a.required_role', 'ph.phase_no']);

        if (! $pending) {
            $this->clear(self::NPD_GATE, 'npd_project', (int) $p->id);

            return 0;
        }

        $days = (int) Carbon::parse($pending->created_at)->diffInDays(now());

        if ($days < self::GATE_STALE_DAYS) {
            $this->clear(self::NPD_GATE, 'npd_project', (int) $p->id);

            return 0;
        }

        $this->raise(
            self::NPD_GATE,
            'npd_project',
            (int) $p->id,
            "{$p->code}: gate fase {$pending->phase_no} menunggu {$days} hari",
            sprintf(
                '%s — persetujuan tertahan di pemegang menu "%s". Selama gate belum diputuskan, seluruh proyek berhenti.',
                $p->name,
                $pending->required_role
            ),
            $days >= 7 ? 'CRITICAL' : 'WARNING',
            $days,
            self::GATE_STALE_DAYS,
        );

        return 1;
    }

    private function checkNpdSop(object $p): int
    {
        if (! $p->target_sop || Carbon::parse($p->target_sop)->isAfter(now()->startOfDay())) {
            $this->clear(self::NPD_SOP, 'npd_project', (int) $p->id);

            return 0;
        }

        $days = (int) Carbon::parse($p->target_sop)->diffInDays(now()->startOfDay());

        $this->raise(
            self::NPD_SOP,
            'npd_project',
            (int) $p->id,
            "{$p->code}: target SOP terlewat {$days} hari",
            sprintf(
                '%s masih di fase %d padahal target mulai produksi %s. Pelanggan biasanya sudah menjadwalkan pesanannya.',
                $p->name,
                $p->current_phase_no,
                Carbon::parse($p->target_sop)->toDateString()
            ),
            'CRITICAL',
            $days,
            0,
        );

        return 1;
    }

    /** Proyek yang berhenti berjalan tidak lagi perlu didorong. */
    private function clearNpdForClosedProjects(): void
    {
        $closed = DB::table('npd_project')
            ->whereIn('status', ['CLOSED', 'CANCELLED', 'HANDOVER'])
            ->pluck('id');

        if ($closed->isEmpty()) {
            return;
        }

        sys_alert::whereIn('type', [self::NPD_TASK, self::NPD_GATE, self::NPD_SOP])
            ->where('ref_type', 'npd_project')
            ->whereIn('ref_id', $closed)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }

    /**
     * Raw material below its minimum.
     *
     * Counted against stock plus what is already on order, because a shortage
     * that a purchase order already covers is not a shortage worth waking
     * anyone for.
     */
    public function checkMinStock(): int
    {
        $raised = 0;

        $items = DB::table('m_item')
            ->where('active', 1)
            ->where('min_stock', '>', 0)
            ->get(['id', 'code', 'part_name', 'min_stock']);

        foreach ($items as $item) {
            $onHand = (int) DB::table('wh_inc_detail')
                ->where('item_id', $item->id)
                ->whereNotIn('serial_id', DB::table('wh_out_detail')->select('serial_id'))
                ->sum('qty');

            $onOrder = (int) DB::table('prc_po_detail as d')
                ->join('prc_po_main as m', 'm.id', '=', 'd.main_id')
                ->where('d.item_id', $item->id)
                ->whereIn('m.status', ['OPEN', 'INPROGRESS'])
                ->sum(DB::raw('GREATEST(d.qty - d.qty_received, 0)'));

            $available = $onHand + $onOrder;

            if ($available >= (int) $item->min_stock) {
                $this->clear(self::MIN_STOCK, 'm_item', (int) $item->id);

                continue;
            }

            $this->raise(
                self::MIN_STOCK,
                'm_item',
                (int) $item->id,
                "Stok {$item->code} di bawah minimum",
                sprintf(
                    '%s: tersedia %s pcs (stok %s + PO berjalan %s), minimum %s pcs.',
                    $item->part_name,
                    number_format($available),
                    number_format($onHand),
                    number_format($onOrder),
                    number_format((int) $item->min_stock)
                ),
                $onHand <= 0 ? 'CRITICAL' : 'WARNING',
                $available,
                (float) $item->min_stock,
            );
            $raised++;
        }

        return $raised;
    }
}
