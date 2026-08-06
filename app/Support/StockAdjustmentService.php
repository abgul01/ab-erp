<?php

namespace App\Support;

use App\Models\User;
use App\Models\wh_adj_main;
use Illuminate\Support\Facades\DB;

/**
 * Stock opname and adjustment.
 *
 * Counting is only half the job — the variance has to reach the ledger, or the
 * books keep carrying stock that is not on the rack. Posting writes the
 * inventory variance journal and stamps the sheet; the counted and system
 * figures both stay on the line so the number can be defended months later.
 *
 * Physical serial-level correction for RM still happens through the incoming
 * and outgoing documents — a serialised bar is identified, not merely counted.
 * What this sheet owns is the valuation and the audit record.
 *
 * PRD §4.8
 */
class StockAdjustmentService
{
    /** Inventory account per warehouse, and the variance account they clear to. */
    private const INVENTORY_COA = ['RM' => '1300', 'FG' => '1320', 'GENERAL' => '1340'];

    private const VARIANCE_COA = '6900';

    public function __construct(private FgStockService $fg) {}

    /** What the system believes is on hand, to count against. */
    public function systemStock(string $warehouse = 'RM'): array
    {
        if ($warehouse === 'FG') {
            return collect($this->fg->stockByItem())->map(fn ($r) => (object) $r)->all();
        }

        // RM on hand: received serials that have not been issued.
        return DB::table('wh_inc_detail as wd')
            ->leftJoin('m_item as i', 'i.id', '=', 'wd.item_id')
            ->whereNotIn('wd.serial_id', DB::table('wh_out_detail')->select('serial_id'))
            ->groupBy('wd.item_id', 'i.code', 'i.part_name')
            ->orderBy('i.code')
            ->get([
                'wd.item_id',
                'i.code as item_code',
                'i.part_name',
                DB::raw('SUM(wd.qty) as qty_system'),
            ])
            ->all();
    }

    /** Replace a sheet's lines, computing each variance as it goes. */
    public function syncLines(wh_adj_main $main, array $lines): void
    {
        foreach ($lines as $l) {
            $system = (float) ($l['qty_system'] ?? 0);
            $counted = (float) $l['qty_counted'];

            $main->detail()->create([
                'item_id' => $l['item_id'],
                'serial_id' => $l['serial_id'] ?? null,
                'qty_system' => $system,
                'qty_counted' => $counted,
                'qty_diff' => round($counted - $system, 2),
                'unit_cost' => (float) ($l['unit_cost'] ?? 0),
                'note' => $l['note'] ?? null,
            ]);
        }
    }

    /**
     * Post the sheet: value the variance and write it to the ledger.
     *
     * @return array{variance_qty: float, variance_value: float, journal_id: ?int}
     */
    public function post(wh_adj_main $main, User $user): array
    {
        $lines = $main->detail;

        $varianceQty = (float) $lines->sum('qty_diff');
        $varianceValue = round($lines->sum(fn ($l) => $l->qty_diff * $l->unit_cost), 2);

        return DB::transaction(function () use ($main, $user, $varianceQty, $varianceValue) {
            $journalId = null;

            // A count that matched to the rupiah needs no journal — posting an
            // empty one would only add noise to the ledger.
            if (abs($varianceValue) >= 0.01) {
                $inventory = self::INVENTORY_COA[$main->warehouse] ?? self::INVENTORY_COA['RM'];
                $gain = $varianceValue > 0;

                $journal = JournalEngine::post(
                    'STOCK_ADJ',
                    $main->id,
                    (string) $main->date,
                    'ADJ',
                    [
                        // A surplus raises inventory; a shortfall writes it off.
                        ['coa' => $gain ? $inventory : self::VARIANCE_COA, 'debit' => abs($varianceValue), 'memo' => "Selisih opname {$main->code}"],
                        ['coa' => $gain ? self::VARIANCE_COA : $inventory, 'credit' => abs($varianceValue), 'memo' => "Selisih opname {$main->code}"],
                    ],
                    "Stock adjustment {$main->code}",
                    (int) $user->id,
                );
                $journalId = $journal?->id;
            }

            $main->update([
                'status' => 'POSTED',
                'posted_by' => $user->id,
                'posted_at' => now(),
            ]);

            return [
                'code' => $main->code,
                'variance_qty' => $varianceQty,
                'variance_value' => $varianceValue,
                'journal_id' => $journalId,
            ];
        });
    }
}
