<?php

namespace App\Support;

use App\Exceptions\BizException;
use App\Models\prc_gr_detail;
use App\Models\prc_gr_serial;
use App\Models\prd_wo_serial_rm;
use App\Models\wh_inc_detail;
use App\Models\wh_out_detail;
use App\Models\wh_rem_detail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Centralised serial number lifecycle: generate on GR → actualise on
 * physical check → track stock → consume on WO/MES → remnant return.
 *
 * LLD §4.6
 */
class SerialService
{
    public function __construct(
        protected UomConversionService $uom,
        protected ScrapService $scrap,
    ) {}

    /**
     * Generate serial rows from GR line input.
     */
    public function generateForGrLine(prc_gr_detail $line, array $serials): Collection
    {
        $rows = [];
        foreach ($serials as $s) {
            $rows[] = $line->serials()->create([
                'serial_id' => $s['serial_id'],
                'millsheet' => $s['millsheet'] ?? '-',
                'qty' => (int) $s['qty'],
                'length' => (int) ($s['length'] ?? 0),
                'weight' => (float) ($s['weight'] ?? 0),
                'status' => $s['status'] ?? null,
                'ng_reason' => $s['ng_reason'] ?? null,
            ]);
        }

        $this->recalcDetail($line);

        return $line->serials()->get();
    }

    /**
     * Actualise a serial after physical check — update length/weight/status.
     */
    public function actualize(prc_gr_serial $serial, ?float $mm, ?float $kg, string $status, ?string $reason = null): void
    {
        $update = ['status' => $status];
        if ($mm !== null) {
            $update['length'] = (int) $mm;
        }
        if ($kg !== null) {
            $update['weight'] = round($kg, 4);
        }
        if ($reason !== null) {
            $update['ng_reason'] = $reason;
        }

        $serial->update($update);

        if ($serial->relationLoaded('detail') || $serial->detail) {
            $this->recalcDetail($serial->detail);
        } else {
            $detail = prc_gr_detail::find($serial->det_id);
            if ($detail) {
                $this->recalcDetail($detail);
            }
        }
    }

    /**
     * Consume length from a booked serial (WO issue). Updates remaining length,
     * proportional weight, and evaluates scrap candidacy.
     */
    public function consume(prd_wo_serial_rm $s, float $usedMm, bool $finish = false): prd_wo_serial_rm
    {
        if ($usedMm > $s->length_rem) {
            throw BizException::make('SERIAL_CONSUME', "Konsumsi {$usedMm}mm melebihi sisa {$s->length_rem}mm.");
        }

        // Two terminals can scan the same bar at once. The write is conditional
        // on the version we read, so the loser is told to re-read rather than
        // quietly overwriting the other's remaining length.
        $newRem = round($s->length_rem - $usedMm, 2);
        $affected = DB::table('prd_wo_serial_rm')
            ->where('id', $s->id)
            ->where('version', $s->version ?? 0)
            ->update([
                'length_rem' => $newRem,
                'version' => ($s->version ?? 0) + 1,
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            throw BizException::make(
                'SERIAL_CONFLICT',
                "Serial {$s->serial_id} baru saja diubah terminal lain. Muat ulang data lalu ulangi scan.",
                409,
            );
        }

        $fresh = $s->fresh();

        if ($finish) {
            $this->scrap->evaluate($fresh);
        }

        return $fresh->fresh();
    }

    /**
     * Query on-hand serials available for a WO.
     */
    public function availableForWo(int $itemId, ?int $excludeWoId = null): Collection
    {
        $bookedSerials = DB::table('prd_wo_serial_rm as ws')
            ->join('prd_wo_detail_rm as wd', 'wd.id', '=', 'ws.detail_id')
            ->join('prd_wo_main as w', 'w.id', '=', 'wd.main_id')
            ->where('wd.rm_id', $itemId)
            ->whereIn('w.status', [1, 2])
            ->pluck('ws.serial_id');

        $issuedSerials = wh_out_detail::pluck('serial_id');

        return wh_inc_detail::where('item_id', $itemId)
            ->whereNotIn('serial_id', $bookedSerials)
            ->whereNotIn('serial_id', $issuedSerials)
            ->with('rack')
            ->get();
    }

    /**
     * Trace a serial through its lifecycle.
     */
    public function trace(string $serialId): array
    {
        $grSerial = prc_gr_serial::with('detail.main.ven')->where('serial_id', $serialId)->first();
        $inc = wh_inc_detail::with('rack', 'main')->where('serial_id', $serialId)->first();
        $out = wh_out_detail::with('main.wo.fg')->where('serial_id', $serialId)->first();
        $rem = wh_rem_detail::with('rack', 'main')->where('serial_id', $serialId)->first();
        $woBooked = prd_wo_serial_rm::with('detail.main.fg')->where('serial_id', $serialId)->first();

        $gr = null;
        if ($grSerial) {
            $gr = [
                'id' => $grSerial->id,
                'gr_code' => $grSerial->detail?->main?->code,
                'vendor' => $grSerial->detail?->main?->ven?->company_n,
                'millsheet' => $grSerial->millsheet,
                'qty' => $grSerial->qty,
                'length' => $grSerial->length,
                'weight' => $grSerial->weight,
                'status' => $grSerial->status,
                'ng_reason' => $grSerial->ng_reason,
            ];
        }

        return [
            'serial_id' => $serialId,
            'gr' => $gr,
            'incoming' => $inc ? [
                'id' => $inc->id,
                'doc_code' => $inc->main?->code,
                'rack' => $inc->rack?->code,
                'length' => $inc->length,
            ] : null,
            'wo_booked' => $woBooked ? [
                'id' => $woBooked->id,
                'wo_code' => $woBooked->detail?->main?->code,
                'fg' => $woBooked->detail?->main?->fg?->code,
                'length_book' => $woBooked->length_book,
                'length_rem' => $woBooked->length_rem,
                'scrap' => $woBooked->scrap,
            ] : null,
            'outgoing' => $out ? [
                'id' => $out->id,
                'doc_code' => $out->main?->code,
                'wo_code' => $out->main?->wo?->code,
                'length_used' => $out->length_used,
                'length_rem' => $out->length_rem,
                'weight_used' => $out->weight_used,
            ] : null,
            'remnant' => $rem ? [
                'id' => $rem->id,
                'doc_code' => $rem->main?->code,
                'rack' => $rem->rack?->code,
                'length' => $rem->length,
                'weight' => $rem->weight,
            ] : null,
        ];
    }

    /**
     * Recalc GR detail from its serials (qty, weight, unit weight).
     */
    protected function recalcDetail(prc_gr_detail $line): void
    {
        $serials = $line->serials()->get();
        $qty = $serials->sum('qty');
        $wTotal = $serials->sum('weight');
        $unitW = $qty > 0 ? round($wTotal / $qty, 2) : null;

        $line->update([
            'qty' => $qty,
            'weight' => $unitW,
            'w_total' => $wTotal,
        ]);
    }
}
