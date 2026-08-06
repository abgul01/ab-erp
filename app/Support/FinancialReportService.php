<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Statement-level reporting off the posted ledger.
 *
 * Everything here is cumulative to the end of a period, except the income
 * statement, which is a movement within one — mixing the two is the classic way
 * to produce a balance sheet that does not balance.
 *
 * Profit for the year is folded into equity on the balance sheet rather than
 * being carried in a separate account, so the statement balances even before
 * anyone runs a year-end close.
 *
 * PRD §4.14
 */
class FinancialReportService
{
    private const DEBIT_GROUPS = ['ASSET', 'COGS', 'EXPENSE'];

    /** Cumulative balance per account up to and including $period. */
    private function balances(string $period, ?string $fromPeriod = null)
    {
        return DB::table('acc_journal_det as d')
            ->join('acc_journal_main as m', 'm.id', '=', 'd.main_id')
            ->join('acc_coa as c', 'c.id', '=', 'd.coa_id')
            ->where('m.status', 'POSTED')
            ->where('m.period', '<=', $period)
            ->when($fromPeriod, fn ($q) => $q->where('m.period', '>=', $fromPeriod))
            ->groupBy('c.id', 'c.code', 'c.name', 'c.acc_group')
            ->orderBy('c.code')
            ->get([
                'c.code', 'c.name', 'c.acc_group',
                DB::raw('SUM(d.debit) as debit'),
                DB::raw('SUM(d.credit) as credit'),
            ]);
    }

    /** Natural-sign balance: positive means the account sits on its normal side. */
    private function natural(object $row): float
    {
        $raw = (float) $row->debit - (float) $row->credit;

        return in_array($row->acc_group, self::DEBIT_GROUPS, true) ? $raw : -$raw;
    }

    /** Income statement for one period. */
    public function incomeStatement(string $period, ?string $fromPeriod = null): array
    {
        $rows = $this->balances($period, $fromPeriod ?: $period);

        $sections = ['REVENUE' => [], 'COGS' => [], 'EXPENSE' => []];
        foreach ($rows as $r) {
            if (! isset($sections[$r->acc_group])) {
                continue;
            }
            $amount = $this->natural($r);
            if (abs($amount) < 0.01) {
                continue;
            }
            $sections[$r->acc_group][] = ['code' => $r->code, 'name' => $r->name, 'amount' => round($amount, 2)];
        }

        $revenue = array_sum(array_column($sections['REVENUE'], 'amount'));
        $cogs = array_sum(array_column($sections['COGS'], 'amount'));
        $expense = array_sum(array_column($sections['EXPENSE'], 'amount'));

        return [
            'period' => $period,
            'from_period' => $fromPeriod ?: $period,
            'revenue' => $sections['REVENUE'],
            'cogs' => $sections['COGS'],
            'expense' => $sections['EXPENSE'],
            'total_revenue' => round($revenue, 2),
            'total_cogs' => round($cogs, 2),
            'gross_profit' => round($revenue - $cogs, 2),
            'total_expense' => round($expense, 2),
            'net_income' => round($revenue - $cogs - $expense, 2),
        ];
    }

    /** Balance sheet as at the end of $period. */
    public function balanceSheet(string $period): array
    {
        $rows = $this->balances($period);

        $sections = ['ASSET' => [], 'LIABILITY' => [], 'EQUITY' => []];
        $revenue = 0.0;
        $cost = 0.0;

        foreach ($rows as $r) {
            $amount = $this->natural($r);

            if ($r->acc_group === 'REVENUE') {
                $revenue += $amount;

                continue;
            }
            if (in_array($r->acc_group, ['COGS', 'EXPENSE'], true)) {
                $cost += $amount;

                continue;
            }
            if (! isset($sections[$r->acc_group]) || abs($amount) < 0.01) {
                continue;
            }
            $sections[$r->acc_group][] = ['code' => $r->code, 'name' => $r->name, 'amount' => round($amount, 2)];
        }

        // Undistributed profit keeps the two sides equal before year-end close.
        $earnings = round($revenue - $cost, 2);
        $sections['EQUITY'][] = ['code' => '—', 'name' => 'Laba (Rugi) Berjalan', 'amount' => $earnings];

        $assets = round(array_sum(array_column($sections['ASSET'], 'amount')), 2);
        $liabilities = round(array_sum(array_column($sections['LIABILITY'], 'amount')), 2);
        $equity = round(array_sum(array_column($sections['EQUITY'], 'amount')), 2);

        return [
            'period' => $period,
            'assets' => $sections['ASSET'],
            'liabilities' => $sections['LIABILITY'],
            'equity' => $sections['EQUITY'],
            'total_assets' => $assets,
            'total_liabilities' => $liabilities,
            'total_equity' => $equity,
            'balanced' => abs($assets - ($liabilities + $equity)) < 0.01,
            'difference' => round($assets - ($liabilities + $equity), 2),
        ];
    }

    /**
     * Payables ageing.
     *
     * Buckets are by days past the due date, not past the invoice date — an
     * invoice on 60-day terms is not overdue in its second month.
     */
    public function apAging(?string $asOf = null): array
    {
        return $this->aging(
            'prc_inv_main', 'ven_id', 'acc_ap_pay_det', 'inv_id', $asOf
        );
    }

    /** Receivables ageing, same rule. */
    public function arAging(?string $asOf = null): array
    {
        return $this->aging(
            'sls_inv_main', 'cus_id', 'acc_ar_rec_det', 'inv_id', $asOf
        );
    }

    private function aging(string $invTable, string $partyCol, string $payTable, string $payFk, ?string $asOf): array
    {
        $asOf = $asOf ?: now()->toDateString();

        $paid = DB::table($payTable)
            ->select($payFk.' as inv_id', DB::raw('SUM(amount) as paid'))
            ->groupBy($payFk);

        $rows = DB::table("{$invTable} as i")
            ->leftJoinSub($paid, 'p', 'p.inv_id', '=', 'i.id')
            ->leftJoin('m_contacts as c', 'c.id', '=', "i.{$partyCol}")
            ->where('i.status', '!=', 'CANCELLED')
            ->whereDate('i.date', '<=', $asOf)
            ->orderBy('i.due_date')
            ->get([
                'i.id', 'i.code', 'i.date', 'i.due_date', 'i.total',
                'c.company_n as party',
                DB::raw('COALESCE(p.paid, 0) as paid'),
            ]);

        $buckets = ['current' => 0.0, 'd1_30' => 0.0, 'd31_60' => 0.0, 'd61_90' => 0.0, 'over_90' => 0.0];
        $items = [];

        foreach ($rows as $r) {
            $outstanding = round((float) $r->total - (float) $r->paid, 2);
            if ($outstanding < 0.01) {
                continue;
            }

            $due = $r->due_date ?: $r->date;
            $daysLate = $due ? (int) floor((strtotime($asOf) - strtotime((string) $due)) / 86400) : 0;

            $bucket = match (true) {
                $daysLate <= 0 => 'current',
                $daysLate <= 30 => 'd1_30',
                $daysLate <= 60 => 'd31_60',
                $daysLate <= 90 => 'd61_90',
                default => 'over_90',
            };
            $buckets[$bucket] += $outstanding;

            $items[] = [
                'id' => $r->id, 'code' => $r->code, 'party' => $r->party,
                'date' => $r->date, 'due_date' => $r->due_date,
                'total' => round((float) $r->total, 2), 'paid' => round((float) $r->paid, 2),
                'outstanding' => $outstanding, 'days_late' => max(0, $daysLate), 'bucket' => $bucket,
            ];
        }

        return [
            'as_of' => $asOf,
            'buckets' => array_map(fn ($v) => round($v, 2), $buckets),
            'total' => round(array_sum($buckets), 2),
            'items' => $items,
        ];
    }
}
