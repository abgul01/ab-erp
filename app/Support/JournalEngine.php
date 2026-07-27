<?php

namespace App\Support;

use App\Exceptions\BizException;
use App\Models\acc_journal_det;
use App\Models\acc_journal_main;
use Illuminate\Support\Facades\DB;

/**
 * The single door for double-entry posting (LLD 4.4). post() validates the
 * period is OPEN, asserts the lines balance (ΣDebit = ΣCredit), and is
 * idempotent on (ref_type, ref_id, jrn_type) so a document can never be
 * journaled twice. Accounts are addressed by COA code.
 */
class JournalEngine
{
    /**
     * @param  array<int, array{coa:string, debit?:float, credit?:float, memo?:string}>  $lines
     * @return acc_journal_main|null  the journal (existing one if already posted)
     */
    public static function post(string $refType, ?int $refId, string $date, string $jrnType, array $lines, string $descrip, int $userId): ?acc_journal_main
    {
        $period = substr(str_replace('-', '', $date), 0, 6);

        // idempotency: same document + journal type is posted once
        if ($refId !== null) {
            $existing = acc_journal_main::where('ref_type', $refType)->where('ref_id', $refId)
                ->where('jrn_type', $jrnType)->first();
            if ($existing) {
                return $existing;
            }
        }

        // resolve COA codes → ids then delegate to the id-based poster
        $resolved = [];
        foreach ($lines as $l) {
            $resolved[] = ['coa_id' => self::coaId($l['coa']), 'debit' => $l['debit'] ?? 0, 'credit' => $l['credit'] ?? 0, 'memo' => $l['memo'] ?? null];
        }

        return self::postRaw($refType, $refId, $date, $jrnType, $resolved, $descrip, $userId);
    }

    /**
     * Post from already-resolved COA ids (used by manual journal entry).
     *
     * @param  array<int, array{coa_id:int, debit?:float, credit?:float, memo?:string}>  $lines
     */
    public static function postRaw(string $refType, ?int $refId, string $date, string $jrnType, array $lines, string $descrip, int $userId): ?acc_journal_main
    {
        $period = substr(str_replace('-', '', $date), 0, 6);

        if ($refId !== null) {
            $existing = acc_journal_main::where('ref_type', $refType)->where('ref_id', $refId)
                ->where('jrn_type', $jrnType)->first();
            if ($existing) {
                return $existing;
            }
        }

        self::assertPeriodOpen($period);

        $resolved = [];
        $debit = 0.0;
        $credit = 0.0;
        foreach ($lines as $l) {
            $d = round((float) ($l['debit'] ?? 0), 2);
            $c = round((float) ($l['credit'] ?? 0), 2);
            if ($d == 0.0 && $c == 0.0) {
                continue;
            }
            $resolved[] = ['coa_id' => (int) $l['coa_id'], 'debit' => $d, 'credit' => $c, 'memo' => $l['memo'] ?? null];
            $debit += $d;
            $credit += $c;
        }
        if (empty($resolved)) {
            throw BizException::make('JRN_EMPTY', 'Jurnal tidak punya baris bernilai.');
        }
        if (round($debit - $credit, 2) !== 0.0) {
            throw BizException::make('JRN_UNBALANCED', 'Jurnal tidak seimbang (Debit ' . number_format($debit, 2) . ' ≠ Kredit ' . number_format($credit, 2) . ').');
        }

        return DB::transaction(function () use ($refType, $refId, $date, $period, $jrnType, $resolved, $descrip, $userId) {
            $main = acc_journal_main::create([
                'code' => (new NumberingService)->next('JRN', 'JV'),
                'date' => $date, 'period' => $period, 'jrn_type' => $jrnType,
                'ref_type' => $refType, 'ref_id' => $refId, 'descrip' => $descrip,
                'status' => 'POSTED', 'user_id' => $userId,
            ]);
            foreach ($resolved as $r) {
                acc_journal_det::create(['main_id' => $main->id] + $r);
            }

            return $main;
        });
    }

    /** Reverse a posted journal (only its own period must be open). */
    public static function reverse(acc_journal_main $jrn, int $userId): acc_journal_main
    {
        self::assertPeriodOpen($jrn->period);
        $rev = DB::transaction(function () use ($jrn, $userId) {
            $main = acc_journal_main::create([
                'code' => (new NumberingService)->next('JRN', 'JV'),
                'date' => now()->toDateString(),
                'period' => now()->format('Ym'),
                'jrn_type' => 'REV',
                'ref_type' => 'REVERSAL',
                'ref_id' => $jrn->id,
                'descrip' => "Reversal of {$jrn->code}",
                'status' => 'POSTED',
                'user_id' => $userId,
            ]);
            foreach ($jrn->detail as $d) {
                acc_journal_det::create([
                    'main_id' => $main->id, 'coa_id' => $d->coa_id,
                    'debit' => $d->credit, 'credit' => $d->debit, 'memo' => 'reversal',
                ]);
            }
            $jrn->update(['status' => 'REVERSED']);

            return $main;
        });

        return $rev;
    }

    private static function assertPeriodOpen(string $period): void
    {
        $status = DB::table('acc_period')->where('period', $period)->value('status');
        // a period with no row is treated as open (auto-opened on first posting)
        if ($status && $status !== 'OPEN') {
            throw BizException::make('PERIOD_LOCKED', "Periode {$period} sudah {$status} — posting ditolak.", 422);
        }
        if (! $status) {
            DB::table('acc_period')->insert(['period' => $period, 'status' => 'OPEN']);
        }
    }

    private static function coaId(string $code): int
    {
        $id = DB::table('acc_coa')->where('code', $code)->value('id');
        if (! $id) {
            throw BizException::make('COA_MISSING', "Akun COA {$code} belum ada. Lengkapi Chart of Accounts.");
        }

        return (int) $id;
    }
}
