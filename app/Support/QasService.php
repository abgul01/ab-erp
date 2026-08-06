<?php

namespace App\Support;

use App\Exceptions\BizException;
use App\Models\prc_gr_main;
use App\Models\prc_gr_serial;
use App\Models\qc_incoming_det;
use App\Models\qc_incoming_main;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Quality Assurance / incoming inspection service.
 * Wraps serial physical check (length/weight/OK/NG) before GR is confirmed
 * for warehouse putaway and AP invoicing.
 *
 * LLD §5.2, PRD §4.6
 */
class QasService
{
    public function __construct(
        protected SerialService $serialSvc,
    ) {}

    /**
     * GRs whose serials have not yet been fully inspected.
     */
    public function pendingGrs(): Collection
    {
        return prc_gr_main::with(['ven', 'detail.item'])
            ->whereHas('detail.serials', function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', '')
                    ->orWhere('status', 'GENERATED');
            })
            ->withCount(['detail as pending_serials' => function ($q) {
                $q->whereHas('serials', fn ($s) => $s->whereNull('status')->orWhere('status', '')->orWhere('status', 'GENERATED'));
            }])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Serials pending inspection for a given GR.
     */
    public function pendingSerials(int $grId): Collection
    {
        $gr = prc_gr_main::with('detail.serials', 'ven')->findOrFail($grId);

        $serials = collect();
        foreach ($gr->detail as $det) {
            foreach ($det->serials as $s) {
                if (in_array($s->status, [null, '', 'GENERATED'], true)) {
                    $serials->push((object) [
                        'serial_id' => $s->serial_id,
                        'gr_detail_id' => $det->id,
                        'serial_db_id' => $s->id,
                        'item_code' => $det->item?->code,
                        'item_name' => $det->item?->part_name,
                        'millsheet' => $s->millsheet,
                        'qty' => $s->qty,
                        'length_current' => $s->length,
                        'weight_current' => $s->weight,
                        'ng_reason' => $s->ng_reason,
                    ]);
                }
            }
        }

        return $serials;
    }

    /**
     * Inspect (actualize) a single serial.
     */
    public function inspect(int $serialId, float $length, float $weight, string $status, ?string $reason = null): prc_gr_serial
    {
        $serial = prc_gr_serial::findOrFail($serialId);

        $this->serialSvc->actualize($serial, $length, $weight, $status, $reason);

        return $serial->fresh();
    }

    /**
     * Parameters that must be measured for an item, with their tolerances.
     * Empty when the item has no inspection plan — length and weight alone
     * remain a valid check for plain bar stock.
     */
    public function planFor(int $itemId): Collection
    {
        return DB::table('m_item_inspection as ii')
            ->join('m_inspection_param as p', 'p.id', '=', 'ii.param_id')
            ->where('ii.item_id', $itemId)
            ->where('p.active', 1)
            ->orderBy('p.code')
            ->get([
                'ii.id', 'ii.param_id', 'ii.nominal', 'ii.min_value', 'ii.max_value', 'ii.mandatory',
                'p.code as param_code', 'p.name as param_name', 'p.uom', 'p.method',
            ]);
    }

    /**
     * Record the readings behind a verdict.
     *
     * A judgement without its measurements cannot be re-checked later, which is
     * the whole point of incoming inspection records — so each parameter is
     * stored with the standard it was compared against, not just pass or fail.
     */
    public function recordReadings(int $grId, int $inspectorId, array $readings): qc_incoming_main
    {
        return DB::transaction(function () use ($grId, $inspectorId, $readings) {
            $main = qc_incoming_main::firstOrCreate(
                ['gr_id' => $grId],
                ['code' => 'QC-'.$grId, 'date' => now()->toDateString(), 'inspector_id' => $inspectorId, 'status' => 'DRAFT']
            );

            foreach ($readings as $r) {
                qc_incoming_det::updateOrCreate(
                    ['main_id' => $main->id, 'param' => $r['param']],
                    [
                        'standard' => $r['standard'] ?? null,
                        'actual' => $r['actual'] ?? null,
                        'judge' => $r['judge'] ?? null,
                    ]
                );
            }

            return $main->fresh();
        });
    }

    /** Readings already recorded for a GR. */
    public function readings(int $grId): Collection
    {
        $main = qc_incoming_main::where('gr_id', $grId)->first();

        return $main
            ? qc_incoming_det::where('main_id', $main->id)->orderBy('param')->get()
            : collect();
    }

    /**
     * Confirm inspection for a GR — creates QAS record if not exists,
     * marks remaining unchecked serials as GENERATED (excluded from posting).
     */
    public function confirm(int $grId, int $inspectorId): void
    {
        DB::transaction(function () use ($grId, $inspectorId) {
            $gr = prc_gr_main::with('detail.serials')->findOrFail($grId);

            $checked = 0;
            $total = 0;
            foreach ($gr->detail as $det) {
                foreach ($det->serials as $s) {
                    $total++;
                    if (in_array($s->status, ['OK', 'NG'], true)) {
                        $checked++;
                    }
                }
            }

            if ($checked === 0) {
                throw BizException::make('QAS_NO_INSPECT', 'Belum ada serial yang diperiksa.');
            }

            // Create QAS record
            qc_incoming_main::updateOrCreate(
                ['gr_id' => $grId],
                ['inspector_id' => $inspectorId]
            );

            // Mark remaining unchecked as GENERATED
            foreach ($gr->detail as $det) {
                foreach ($det->serials as $s) {
                    if (! in_array($s->status, ['OK', 'NG'], true)) {
                        $s->update(['status' => 'VOID', 'ng_reason' => 'Tidak diperiksa']);
                    }
                }
            }
        });
    }
}
