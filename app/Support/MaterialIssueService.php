<?php

namespace App\Support;

use App\Exceptions\BizException;
use App\Models\prd_kanban;
use App\Models\prd_wo_serial_rm;
use App\Models\User;
use App\Models\wh_inc_detail;
use App\Models\wh_out_detail;
use App\Models\wh_out_main;
use App\Models\wh_rem_detail;
use App\Models\wh_rem_main;
use Illuminate\Support\Facades\DB;

/**
 * Material issue against a Kanban / Work Order.
 * Scans RM serials, consumes length, updates kanban + warehouse + booking.
 *
 * LLD §5.3, PRD §4.8
 */
class MaterialIssueService
{
    public function __construct(
        protected NumberingService $num,
        protected SerialService $serialSvc,
        protected ScrapService $scrap,
        protected UomConversionService $uom,
    ) {}

    /**
     * Issue a serial against a kanban.
     *
     * @return array{out_detail: wh_out_detail, kanban: prd_kanban, remnant: wh_rem_detail|null}
     */
    public function issue(int $kanbanId, string $serialId, float $usedMm, bool $returnRemnant, ?int $remRackId, User $user): array
    {
        $kanban = prd_kanban::with('wo')->findOrFail($kanbanId);

        if ($kanban->status !== 'OPEN') {
            throw BizException::make('KANBAN_STATE', 'Kanban harus berstatus OPEN.');
        }

        // Validate serial is on-hand and matches the item
        $inc = wh_inc_detail::where('serial_id', $serialId)
            ->where('item_id', $kanban->item_id)
            ->first();
        if (! $inc) {
            throw BizException::make('KANBAN_SERIAL', "Serial {$serialId} tidak ditemukan atau bukan item kanban.");
        }

        // Validate serial is booked to the WO
        $woSerial = prd_wo_serial_rm::whereHas('detail', fn ($q) => $q->where('main_id', $kanban->wo_id))
            ->where('serial_id', $serialId)
            ->first();
        if (! $woSerial) {
            throw BizException::make('KANBAN_BOOKING', "Serial {$serialId} belum dibooking ke WO ini.");
        }

        $lengthSerial = (float) $inc->length;
        if ($usedMm > $lengthSerial) {
            throw BizException::make('KANBAN_LEN', "Used mm ({$usedMm}) exceeds serial length ({$lengthSerial}).");
        }

        $weight = (float) ($inc->weight ?? 0);
        $remLen = round($lengthSerial - $usedMm, 2);

        return DB::transaction(function () use ($kanban, $serialId, $usedMm, $lengthSerial, $weight, $remLen, $returnRemnant, $remRackId, $user, $woSerial) {
            // Create outgoing document
            $out = wh_out_main::create([
                'code' => $this->num->next('WH_OUT', 'OUT'),
                'wo_id' => $kanban->wo_id,
                'item_id' => $kanban->item_id,
                'user_id' => $user->id,
                'date' => now()->toDateString(),
            ]);

            $weightUsed = $this->uom->usedKg($usedMm, $weight, $lengthSerial);
            $outDetail = $out->detail()->create([
                'serial_id' => $serialId,
                'qty' => 1,
                'pm' => 0,
                'length_serial' => $lengthSerial,
                'length_used' => $usedMm,
                'length_rem' => $remLen,
                'weight_used' => $weightUsed,
                'rem_data' => $returnRemnant && $remLen > 0 ? 1 : 0,
            ]);

            // Consume the booked serial, then re-check whether what is left is
            // still long enough for any product. The flag it may raise is only a
            // proposal — a supervisor decides on the Scrap RM screen.
            $this->serialSvc->consume($woSerial, $usedMm, finish: true);
            $scrapCandidate = $this->scrap->evaluate($woSerial->fresh());

            // Create remnant if requested
            $remnant = null;
            if ($returnRemnant && $remLen > 0 && $remRackId) {
                $rem = wh_rem_main::create([
                    'out_id' => $out->id,
                    'date' => now()->toDateString(),
                    'user_id' => $user->id,
                    'shift_id' => DB::table('m_shift')->orderBy('id')->value('id'),
                ]);
                $remnant = wh_rem_detail::create([
                    'id_prim' => $rem->id,
                    'serial_id' => $serialId,
                    'length' => $remLen,
                    'weight' => $this->uom->remKg($remLen, $weight, $lengthSerial),
                    'rack_id' => $remRackId,
                ]);
            }

            // Update kanban
            $kanban->increment('qty_issued');
            $kanban->update([
                'issued_by' => $user->id,
                'issued_at' => now(),
                'status' => $kanban->qty_issued >= $kanban->qty_planned ? 'ISSUED' : 'OPEN',
            ]);

            return [
                'out_detail' => $outDetail,
                'kanban' => $kanban->fresh(),
                'remnant' => $remnant,
                'scrap_candidate' => $scrapCandidate,
            ];
        });
    }

    /**
     * Return remnant from a previously issued kanban (inline return).
     */
    public function returnRemnant(int $outDetailId, float $lengthRem, int $remRackId, User $user): wh_rem_detail
    {
        $detail = wh_out_detail::findOrFail($outDetailId);

        return DB::transaction(function () use ($detail, $lengthRem, $remRackId, $user) {
            $rem = wh_rem_main::create([
                'out_id' => DB::raw($detail->id_prim),
                'date' => now()->toDateString(),
                'user_id' => $user->id,
                'shift_id' => DB::table('m_shift')->orderBy('id')->value('id'),
            ]);

            $remnant = wh_rem_detail::create([
                'id_prim' => $rem->id,
                'serial_id' => $detail->serial_id,
                'length' => $lengthRem,
                'weight' => $this->uom->remKg($lengthRem, (float) $detail->weight_used, (float) $detail->length_used),
                'rack_id' => $remRackId,
            ]);

            $detail->update(['rem_data' => 1]);

            return $remnant;
        });
    }
}
