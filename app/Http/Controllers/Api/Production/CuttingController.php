<?php

namespace App\Http\Controllers\Api\Production;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\prd_wip;
use App\Models\prd_wo_main;
use App\Models\prd_wo_serial_rm;
use App\Models\tr_cut_detail;
use App\Models\tr_cut_main;
use App\Models\tr_cut_pal_pr;
use App\Models\tr_cut_serial;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use App\Support\PlanningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cutting Transaction (MES, first routing step) on the original schema:
 *   tr_cut_main   — the transaction header (WIP + Denpyou + item + process)
 *   tr_cut_detail — one row per MACHINE working it (start/end time = the timer)
 *   tr_cut_serial — the scanned RM bars under a machine (qty act, length rem)
 *   tr_cut_pal_pr — pallets produced, handed to the Processing screen
 *
 * A WIP is the shop-floor handle on a released Work Order (prd_wip.wo_id).
 */
class CuttingController extends Controller
{
    /** Machines that may work one cutting transaction at the same time. */
    public const MAX_MACHINE = 4;

    /**
     * Seconds between two "H:i:s" clock marks (up to now when $end is null).
     * Sent to the UI so the on-screen timers follow the server clock instead of
     * whatever time the shop-floor tablet happens to think it is.
     */
    private function elapsedSec(?string $start, ?string $end = null): ?int
    {
        if (! $start) {
            return null;
        }
        $toSec = function (string $t): int {
            [$h, $m, $s] = array_pad(explode(':', substr($t, 0, 8)), 3, '0');

            return ((int) $h) * 3600 + ((int) $m) * 60 + (int) $s;
        };
        $d = $toSec($end ?: now()->format('H:i:s')) - $toSec($start);

        return $d < 0 ? $d + 86400 : $d;
    }

    /**
     * Repair still owed on this lot, per serial: what the decision desk judged
     * repairable, minus what has already been re-cut in a repair transaction
     * (tr_cut_main.repair = 1). Those pieces must go through cutting again.
     *
     * @return array<string, int> serial_id => qty still to redo
     */
    private function outstandingRepair(int $wipId): array
    {
        $cutIds = tr_cut_main::where('wip_id', $wipId)->pluck('id');
        $abIds = DB::table('tr_ab_cut_main')->whereIn('cut_id', $cutIds ?: [0])->pluck('id');
        $decided = DB::table('tr_repair_cut')->whereIn('main_id', $abIds ?: [0])
            ->selectRaw('serial_id, SUM(qty) q')->groupBy('serial_id')->pluck('q', 'serial_id');

        $repairDetIds = tr_cut_detail::whereIn('main_id', tr_cut_main::where('wip_id', $wipId)->where('repair', 1)->pluck('id') ?: [0])->pluck('id');
        $redone = tr_cut_serial::whereIn('detail_id', $repairDetIds ?: [0])
            ->selectRaw('serial_id, SUM(qty) q')->groupBy('serial_id')->pluck('q', 'serial_id');

        $out = [];
        foreach ($decided as $sn => $q) {
            $left = (int) $q - (int) ($redone[$sn] ?? 0);
            if ($left > 0) {
                $out[$sn] = $left;
            }
        }

        return $out;
    }

    /** Shop-floor user code for the legacy varchar(5) user_id columns. */
    private function userCode(Request $request): string
    {
        return substr(sprintf('U%04d', $request->user()->id), 0, 5);
    }

    /**
     * Cutting Transactions list — what is still running vs already finished.
     * A transaction is FINISHED when it has machines and every one of them has
     * been closed (tr_cut_detail.finish); anything else is still on the floor.
     */
    public function index(Request $request)
    {
        $q = tr_cut_main::with(['item'])->orderByDesc('id');
        if ($s = trim((string) $request->query('q', ''))) {
            $q->where('code', 'like', "%{$s}%")->orWhere('no_dp', 'like', "%{$s}%");
        }
        $page = $q->paginate(min(max((int) $request->query('per_page', 25), 1), 200));

        $ids = $page->getCollection()->pluck('id')->all();
        $wipCodes = prd_wip::whereIn('id', $page->getCollection()->pluck('wip_id'))->pluck('code', 'id');
        $stats = tr_cut_detail::whereIn('main_id', $ids ?: [0])
            ->selectRaw('main_id, COUNT(*) n, SUM(finish) done')->groupBy('main_id')->get()->keyBy('main_id');

        $page->getCollection()->transform(function ($m) use ($wipCodes, $stats) {
            $st = $stats->get($m->id);
            $n = (int) ($st->n ?? 0); $done = (int) ($st->done ?? 0);
            $m->wip_code = $wipCodes[$m->wip_id] ?? null;
            $m->machines = $n;
            $m->status = $n > 0 && $done >= $n ? 'FINISHED' : 'RUNNING';
            $m->item_code = $m->item?->code;

            return $m;
        });

        return ApiResponse::paginated($page);
    }

    /**
     * Read-only "View Cutting Data": the header, then each machine with its
     * running time, its downtime, and the bars it cut (including how much was
     * found abnormal on each).
     */
    public function view(int $id)
    {
        $main = tr_cut_main::with(['item', 'process'])->findOrFail($id);
        $wip = prd_wip::find($main->wip_id);
        $wo = $wip ? prd_wo_main::with('customer')->find($wip->wo_id) : null;

        $booked = $wo
            ? prd_wo_serial_rm::whereHas('detail', fn ($q) => $q->where('main_id', $wo->id))->get()->keyBy('serial_id')
            : collect();
        $bomRm = DB::table('m_bom as b')->join('m_bom_det_rm as d', 'd.id_prim', '=', 'b.id')
            ->where('b.item_id', $main->item_id)->orderBy('d.priority')->first(['d.length_cut', 'd.length_use']);
        $sizeLength = (float) ($bomRm->length_cut ?? $bomRm->length_use ?? 0);

        $abIds = DB::table('tr_ab_cut_main')->where('cut_id', $main->id)->pluck('id');
        $abnormal = DB::table('tr_ab_cut_det')->whereIn('main_id', $abIds ?: [0])
            ->selectRaw('serial_id, SUM(qty) q')->groupBy('serial_id')->pluck('q', 'serial_id');

        $downtimes = DB::table('tr_dt_cut_main as d')->leftJoin('tr_dt_category as c', 'c.id', '=', 'd.cat_id')
            ->where('d.cut_id', $main->id)
            ->get(['d.id', 'd.det_cut_id', 'd.start_time', 'd.end_time', 'd.descriptions', DB::raw('c.name_c_dt as category')])
            ->groupBy('det_cut_id');

        $machines = tr_cut_detail::with('machine')->where('main_id', $main->id)->orderBy('id')->get()
            ->map(function ($d) use ($booked, $sizeLength, $abnormal, $downtimes) {
                $secs = $this->elapsedSec($d->start_time, $d->end_time);

                return [
                    'machine' => $d->machine ? $d->machine->code . ' — ' . $d->machine->name : '—',
                    'start_time' => $d->start_time, 'end_time' => $d->end_time,
                    'duration' => $secs === null ? null : $this->hm($secs),
                    'downtimes' => ($downtimes[$d->id] ?? collect())->map(fn ($x) => [
                        'category' => $x->category, 'note' => $x->descriptions,
                        'start_time' => $x->start_time, 'end_time' => $x->end_time,
                        'duration' => $this->hm($this->elapsedSec($x->start_time, $x->end_time) ?? 0),
                    ])->values(),
                    'serials' => tr_cut_serial::where('detail_id', $d->id)->orderBy('id')->get()->map(function ($s) use ($booked, $sizeLength, $abnormal) {
                        $b = $booked->get($s->serial_id);

                        return [
                            'serial_id' => $s->serial_id,
                            'qty' => (int) $s->qty,
                            'qty_req' => (int) ($b->qty_per_serial ?? 0),
                            'length_req_dp' => round((int) ($b->qty_per_serial ?? 0) * $sizeLength, 2),
                            'length_wh' => (float) ($b->length_asal ?? 0),
                            'length_rem' => (float) $s->length_rem,
                            'finish' => (int) $s->finish,
                            'abnormal' => (int) ($abnormal[$s->serial_id] ?? 0),
                        ];
                    })->values(),
                ];
            });

        return ApiResponse::item([
            'main' => [
                'user' => $main->user_id, 'wip_code' => $wip?->code,
                'customer' => $wo?->customer?->company_n,
                'date' => $main->date, 'shift' => DB::table('m_shift')->where('id', $main->shift_id)->value('name'),
                'item_code' => $main->item?->code, 'no_dp' => $main->no_dp, 'code' => $main->code,
            ],
            'machines' => $machines,
            'pallets' => tr_cut_pal_pr::where('cut_id', $main->id)->orderBy('id')->get(['id', 'code', 'qty']),
        ]);
    }

    /** "3h 56m" from a number of seconds. */
    private function hm(int $sec): string
    {
        return intdiv($sec, 3600) . 'h ' . intdiv($sec % 3600, 60) . 'm';
    }

    /**
     * Scan Denpyou — the single entry point. Production scans the slip; on the
     * FIRST scan the WIP for that Work Order is created here (it is never
     * picked from a list). Accepts the WO code itself or the "OP-{woId}" form.
     */
    public function checkDenpyou(Request $request)
    {
        $data = $request->validate(['no_dp' => ['required', 'string', 'max:40']]);
        $noDp = trim($data['no_dp']);

        // already scanned before? reuse that WIP
        $wip = prd_wip::where('no_dp', $noDp)->first();
        $wo = $wip ? prd_wo_main::with('fg')->find($wip->wo_id) : $this->resolveWo($noDp);

        if (! $wo) {
            throw BizException::make('CUT_DP', "Denpyou '{$noDp}' tidak dikenali. Scan nomor WO atau OP-<id WO>.");
        }
        if ((int) $wo->status !== 2) {
            throw BizException::make('CUT_WO_STATE', "Work Order {$wo->code} belum/tidak lagi berstatus RELEASED.");
        }

        if (! $wip) {
            $wip = prd_wip::firstOrCreate(
                ['wo_id' => $wo->id],
                ['code' => substr('WIP-' . $noDp, 0, 50), 'item_id' => $wo->fg_id]
            );
        }
        // stamp the scan on first use
        if (! $wip->no_dp) {
            $wip->update(['no_dp' => $noDp, 'user_id' => $this->userCode($request), 'date' => now()->toDateString()]);
        }

        return ApiResponse::item($this->wipContext($wip, $wo, $noDp));
    }

    /** Denpyou → Work Order: the WO code itself, or OP-{woId}. */
    private function resolveWo(string $noDp): ?prd_wo_main
    {
        $wo = prd_wo_main::with('fg')->where('code', $noDp)->first();
        if ($wo) {
            return $wo;
        }
        if (preg_match('/(\d+)\s*$/', $noDp, $m)) {
            return prd_wo_main::with('fg')->find((int) ltrim($m[1], '0'));
        }

        return null;
    }

    /** Resolve a WIP code → everything the cutting header needs. */
    public function wip(string $code)
    {
        $wip = prd_wip::where('code', $code)->first();
        if (! $wip) {
            throw BizException::make('CUT_WIP', "WIP '{$code}' tidak ditemukan.");
        }
        $wo = prd_wo_main::with('fg')->find($wip->wo_id);
        if (! $wo) {
            throw BizException::make('CUT_WO', "Work Order untuk WIP '{$code}' sudah tidak ada.");
        }
        if ((int) $wo->status !== 2) {
            throw BizException::make('CUT_WO_STATE', 'Work Order untuk WIP ini belum/ tidak lagi berstatus RELEASED.');
        }

        return ApiResponse::item($this->wipContext($wip, $wo, (string) $wip->no_dp));
    }

    /** Header payload shared by the Denpyou scan and the WIP lookup. */
    private function wipContext(prd_wip $wip, prd_wo_main $wo, string $noDp): array
    {
        $steps = (new PlanningService)->routing((int) $wo->fg_id, $wo->process_main_id ? (int) $wo->process_main_id : null);
        if (! $steps) {
            throw BizException::make('CUT_NO_ROUTE', 'FG belum punya routing proses.');
        }
        $cutProc = (int) $steps[0]['proc_id'];
        $nextProc = isset($steps[1]) ? (int) $steps[1]['proc_id'] : $cutProc;

        // cut length per piece comes from the FG BOM (length_cut includes kerf)
        $bomRm = DB::table('m_bom as b')->join('m_bom_det_rm as d', 'd.id_prim', '=', 'b.id')
            ->where('b.item_id', $wo->fg_id)->orderBy('d.priority')->first(['d.length_cut', 'd.length_use', 'd.mat_id']);
        $sizeLength = (float) ($bomRm->length_cut ?? $bomRm->length_use ?? 0);

        $serials = prd_wo_serial_rm::whereHas('detail', fn ($q) => $q->where('main_id', $wo->id))->get()
            ->map(fn ($s) => [
                'serial_id' => $s->serial_id,
                'qty_dp' => (int) $s->qty_per_serial,
                'length_serial' => (float) $s->length_asal,
                'length_request' => round((int) $s->qty_per_serial * $sizeLength, 2),
                'length_rem' => (float) $s->length_rem,
            ])->values();

        $open = tr_cut_main::where('wip_id', $wip->id)
            ->whereHas('detail', fn ($q) => $q->where('finish', 0))->latest('id')->first();

        return [
            'wip' => ['id' => $wip->id, 'code' => $wip->code],
            'wo' => ['id' => $wo->id, 'code' => $wo->code, 'qty' => (int) $wo->qty],
            'item' => $wo->fg?->only(['id', 'code', 'part_name']),
            'size_length' => $sizeLength,
            'process_id' => $cutProc,
            'next_process_id' => $nextProc,
            'no_dp' => $noDp,
            'serials' => $serials,
            'repair_outstanding' => collect($this->outstandingRepair((int) $wip->id))
                ->map(fn ($q, $sn) => ['serial_id' => $sn, 'qty' => $q])->values(),
            'open_transaction_id' => $open?->id,
        ];
    }

    /** Start (header "Start"): open the cutting transaction. */
    public function start(Request $request)
    {
        $data = $request->validate([
            'wip_id' => ['required', 'integer', 'exists:prd_wip,id'],
            'no_dp' => ['required', 'string', 'max:40'],
            'date' => ['required', 'date'],
            'shift_id' => ['required', 'integer', 'exists:m_shift,id'],
            'subcont' => ['nullable', 'boolean'],
            'sub_code' => ['nullable', 'string', 'max:20'],
            'repair' => ['nullable', 'boolean'],
        ]);

        $wip = prd_wip::findOrFail($data['wip_id']);
        $wo = prd_wo_main::findOrFail($wip->wo_id);
        $steps = (new PlanningService)->routing((int) $wo->fg_id, $wo->process_main_id ? (int) $wo->process_main_id : null);
        if (! $steps) {
            throw BizException::make('CUT_NO_ROUTE', 'FG belum punya routing proses.');
        }

        $main = tr_cut_main::create([
            'code' => (new NumberingService)->next('CUT', 'CUT'),
            'user_id' => $this->userCode($request),
            'wip_id' => $wip->id,
            'no_dp' => $data['no_dp'],
            'item_id' => $wo->fg_id,
            'process_id' => (int) $steps[0]['proc_id'],
            'date' => $data['date'],
            'shift_id' => $data['shift_id'],
            'subcont' => (int) ($data['subcont'] ?? 0),
            'sub_code' => $data['sub_code'] ?? null,
            'repair' => (int) ($data['repair'] ?? 0),
        ]);
        AuditLogger::record($request, "Start cutting {$main->code} (WIP {$wip->code})", $main->code);

        return $this->show($main->id)->setStatusCode(201);
    }

    /** Full transaction state for the screen. */
    public function show(int $id)
    {
        $main = tr_cut_main::with(['item', 'process'])->findOrFail($id);
        $wip = prd_wip::find($main->wip_id);
        $wo = $wip ? prd_wo_main::find($wip->wo_id) : null;

        $bomRm = DB::table('m_bom as b')->join('m_bom_det_rm as d', 'd.id_prim', '=', 'b.id')
            ->where('b.item_id', $main->item_id)->orderBy('d.priority')->first(['d.length_cut', 'd.length_use']);
        $sizeLength = (float) ($bomRm->length_cut ?? $bomRm->length_use ?? 0);

        $booked = $wo
            ? prd_wo_serial_rm::whereHas('detail', fn ($q) => $q->where('main_id', $wo->id))->get()->keyBy('serial_id')
            : collect();

        // a machine is stopped while it has an unfinished downtime — the screen
        // must be able to resume and close it after the modal was shut.
        $dtRun = DB::table('tr_dt_cut_main as d')->leftJoin('tr_dt_category as c', 'c.id', '=', 'd.cat_id')
            ->where('d.cut_id', $main->id)->where('d.finish', 0)
            ->get(['d.id', 'd.det_cut_id', 'd.start_time', 'd.cat_id', 'd.descriptions', 'c.name_c_dt as category'])
            ->keyBy('det_cut_id');

        $machines = tr_cut_detail::with('machine')->where('main_id', $main->id)->orderBy('id')->get()
            ->map(function ($d) use ($booked, $sizeLength, $dtRun) {
                $serials = tr_cut_serial::where('detail_id', $d->id)->orderBy('id')->get()->map(function ($s) use ($booked, $sizeLength) {
                    $b = $booked->get($s->serial_id);

                    return [
                        'id' => $s->id, 'serial_id' => $s->serial_id,
                        'qty_dp' => (int) ($b->qty_per_serial ?? 0),
                        'qty' => (int) $s->qty,
                        'length_serial' => (float) ($b->length_asal ?? 0),
                        'length_request' => round((int) ($b->qty_per_serial ?? 0) * $sizeLength, 2),
                        'length_rem' => (float) $s->length_rem,
                        'finish' => (int) $s->finish,
                    ];
                })->values();

                return [
                    'id' => $d->id, 'machine_id' => $d->machine_id,
                    'machine' => $d->machine?->only(['id', 'code', 'name']),
                    'start_time' => $d->start_time, 'end_time' => $d->end_time, 'finish' => (int) $d->finish,
                    'elapsed_sec' => $this->elapsedSec($d->start_time, $d->end_time),
                    'downtime' => $dtRun->get($d->id) ? [
                        'id' => $dtRun[$d->id]->id,
                        'start_time' => $dtRun[$d->id]->start_time,
                        'elapsed_sec' => $this->elapsedSec($dtRun[$d->id]->start_time),
                        'cat_id' => $dtRun[$d->id]->cat_id,
                        'descriptions' => $dtRun[$d->id]->descriptions,
                        'category' => $dtRun[$d->id]->category,
                    ] : null,
                    'serials' => $serials,
                    'qty_total' => $serials->sum('qty'),
                ];
            });

        return ApiResponse::item([
            'id' => $main->id, 'code' => $main->code, 'no_dp' => $main->no_dp, 'date' => $main->date,
            'user_id' => $main->user_id, 'subcont' => (int) $main->subcont, 'sub_code' => $main->sub_code,
            'repair' => (int) $main->repair,
            'wip' => $wip ? ['id' => $wip->id, 'code' => $wip->code] : null,
            'wo' => $wo ? ['id' => $wo->id, 'code' => $wo->code, 'qty' => (int) $wo->qty] : null,
            'item' => $main->item?->only(['id', 'code', 'part_name']),
            'process' => $main->process?->only(['id', 'code', 'name_p']),
            'size_length' => $sizeLength,
            'machines' => $machines,
            'repair_outstanding' => $wip ? collect($this->outstandingRepair((int) $wip->id))
                ->map(fn ($q, $sn) => ['serial_id' => $sn, 'qty' => $q])->values() : collect(),
            'pallets' => tr_cut_pal_pr::where('cut_id', $main->id)->get(['id', 'code', 'qty', 'process_id']),
            'available_serials' => $booked->values()->map(fn ($b) => ['serial_id' => $b->serial_id, 'qty_dp' => (int) $b->qty_per_serial, 'length_serial' => (float) $b->length_asal, 'length_rem' => (float) $b->length_rem]),
        ]);
    }

    /** "Scan machine code" — a machine starts working this transaction (timer starts). */
    public function addMachine(Request $request, int $id)
    {
        $data = $request->validate([
            'machine_id' => ['nullable', 'integer', 'exists:m_machine,id'],
            'mach_code' => ['nullable', 'string', 'max:50'],
        ]);
        $machineId = $data['machine_id'] ?? null;
        if (! $machineId && ! empty($data['mach_code'])) {
            $machineId = DB::table('m_machine')->where('code', trim($data['mach_code']))->value('id');
            if (! $machineId) {
                throw BizException::make('CUT_MACHINE', "Mesin '{$data['mach_code']}' tidak ditemukan.");
            }
        }
        if (! $machineId) {
            throw BizException::make('CUT_MACHINE', 'Scan kode mesin terlebih dahulu.');
        }

        $main = tr_cut_main::findOrFail($id);
        if (tr_cut_detail::where('main_id', $main->id)->where('machine_id', $machineId)->where('finish', 0)->exists()) {
            throw BizException::make('CUT_MACHINE_DUP', 'Mesin ini sudah aktif pada transaksi ini.');
        }
        if (tr_cut_detail::where('main_id', $main->id)->count() >= self::MAX_MACHINE) {
            throw BizException::make('CUT_MACHINE_MAX', 'Maksimal ' . self::MAX_MACHINE . ' mesin per transaksi cutting.');
        }
        tr_cut_detail::create([
            'main_id' => $main->id, 'machine_id' => $machineId,
            'start_time' => now()->format('H:i:s'), 'finish' => 0,
        ]);

        return $this->show($id);
    }

    public function removeMachine(Request $request, int $detailId)
    {
        $det = tr_cut_detail::findOrFail($detailId);
        DB::transaction(function () use ($det) {
            tr_cut_serial::where('detail_id', $det->id)->delete();
            $det->delete();
        });

        return $this->show((int) $det->main_id);
    }

    /** "Scan Serial Number" — attach a booked RM bar to this machine. */
    public function addSerial(Request $request, int $detailId)
    {
        $data = $request->validate(['serial_id' => ['required', 'string', 'max:50']]);
        $det = tr_cut_detail::findOrFail($detailId);
        $main = tr_cut_main::findOrFail($det->main_id);
        $wip = prd_wip::findOrFail($main->wip_id);

        $booked = prd_wo_serial_rm::whereHas('detail', fn ($q) => $q->where('main_id', $wip->wo_id))
            ->where('serial_id', $data['serial_id'])->first();
        if (! $booked) {
            throw BizException::make('CUT_SERIAL', "Serial '{$data['serial_id']}' tidak dibooking untuk WO ini.");
        }
        if (tr_cut_serial::whereIn('detail_id', tr_cut_detail::where('main_id', $main->id)->pluck('id'))
            ->where('serial_id', $data['serial_id'])->exists()) {
            throw BizException::make('CUT_SERIAL_DUP', 'Serial ini sudah discan pada transaksi ini.');
        }
        // a repair run may only redo serials that were judged repairable
        if ((int) $main->repair === 1 && ! isset($this->outstandingRepair((int) $wip->id)[$data['serial_id']])) {
            throw BizException::make('CUT_REPAIR_NONE', "Serial '{$data['serial_id']}' tidak punya sisa repair yang harus dikerjakan ulang.");
        }

        tr_cut_serial::create([
            'detail_id' => $det->id, 'serial_id' => $data['serial_id'],
            'qty' => 0, 'length_rem' => (float) $booked->length_rem, 'finish' => 0,
        ]);

        return $this->show((int) $main->id);
    }

    /**
     * "View Serials" — every RM bar booked to this Denpyou's WO with its
     * dimensions, what was requested and how much has already been cut.
     */
    public function serialList(string $noDp)
    {
        $wip = prd_wip::where('no_dp', $noDp)->first();
        if (! $wip) {
            throw BizException::make('CUT_DP', "Denpyou '{$noDp}' belum discan.");
        }
        $cutDetIds = tr_cut_detail::whereIn('main_id', tr_cut_main::where('wip_id', $wip->id)->pluck('id'))->pluck('id');
        $cutByserial = tr_cut_serial::whereIn('detail_id', $cutDetIds)
            ->selectRaw('serial_id, SUM(qty) q')->groupBy('serial_id')->pluck('q', 'serial_id');

        $rows = prd_wo_serial_rm::with('detail.rm')
            ->whereHas('detail', fn ($q) => $q->where('main_id', $wip->wo_id))->get()
            ->map(function ($s) use ($cutByserial) {
                $rm = $s->detail?->rm;
                $wasCut = (int) ($cutByserial[$s->serial_id] ?? 0);

                return [
                    'serial_id' => $s->serial_id,
                    'od' => $rm?->o_d, 'id_dim' => $rm?->i_d, 'thickness' => $rm?->thick,
                    'length' => (float) $s->length_asal,
                    'length_use' => (float) $s->length_book,
                    'request' => (int) $s->qty_per_serial,
                    'was_cut' => $wasCut,
                    'fulfilled' => $wasCut >= (int) $s->qty_per_serial,
                ];
            })->values();

        return ApiResponse::collection($rows);
    }

    /** "Add Selected to Machine" — attach several scanned bars at once. */
    public function addSerials(Request $request, int $detailId)
    {
        $data = $request->validate([
            'serials' => ['required', 'array', 'min:1'],
            'serials.*' => ['string', 'max:50'],
        ]);
        $added = 0; $skipped = [];
        foreach ($data['serials'] as $sn) {
            try {
                $this->addSerial(new Request(['serial_id' => $sn]), $detailId);
                $added++;
            } catch (BizException $e) {
                $skipped[] = $sn;
            }
        }
        $det = tr_cut_detail::findOrFail($detailId);
        $res = $this->show((int) $det->main_id);

        return $res->header('X-Added', $added)->header('X-Skipped', count($skipped));
    }

    /** Downtime categories for the Input Downtime modal. */
    public function dtCategories()
    {
        return ApiResponse::collection(DB::table('tr_dt_category')->orderBy('name_c_dt')->get(['id', 'name_c_dt', 'descriptions']));
    }

    /** Input Downtime → Start: the machine stops, the clock on the stop begins. */
    public function downtimeStart(Request $request)
    {
        $data = $request->validate([
            'det_cut_id' => ['required', 'integer', 'exists:tr_cut_detail,id'],
            'cat_id' => ['required', 'integer', 'exists:tr_dt_category,id'],
            'descriptions' => ['nullable', 'string', 'max:100'],
        ]);
        $det = tr_cut_detail::findOrFail($data['det_cut_id']);
        $main = tr_cut_main::findOrFail($det->main_id);
        if (DB::table('tr_dt_cut_main')->where('det_cut_id', $det->id)->where('finish', 0)->exists()) {
            throw BizException::make('DT_RUNNING', 'Masih ada downtime berjalan pada mesin ini.');
        }

        $id = DB::table('tr_dt_cut_main')->insertGetId([
            'code' => substr('DT-' . $main->code . '-' . $det->id, 0, 20),
            'user_id' => $this->userCode($request),
            'cut_id' => $main->id, 'det_cut_id' => $det->id, 'machine_id' => $det->machine_id,
            'no_dp' => $main->no_dp, 'cat_id' => $data['cat_id'],
            'descriptions' => $data['descriptions'] ?? null,
            'start_time' => now()->format('H:i:s'), 'finish' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        AuditLogger::record($request, "Start downtime cutting #{$id} ({$main->code})", $main->code);

        return ApiResponse::item(['id' => $id, 'start_time' => now()->format('H:i:s')], 201);
    }

    /** Input Downtime → Save: record the tools used and stop the downtime. */
    public function downtimeSave(Request $request, int $id)
    {
        $data = $request->validate([
            'tools' => ['array'],
            'tools.*.serial_item' => ['required', 'string', 'max:50'],
            'tools.*.tools_id' => ['nullable', 'integer'],
        ]);
        $dt = DB::table('tr_dt_cut_main')->where('id', $id)->first();
        if (! $dt) {
            throw BizException::make('DT_404', 'Downtime tidak ditemukan.');
        }
        DB::transaction(function () use ($data, $id) {
            foreach ($data['tools'] ?? [] as $t) {
                DB::table('tr_dt_cut_detail')->insert([
                    'main_id' => $id, 'serial_item' => $t['serial_item'], 'tools_id' => $t['tools_id'] ?? 0,
                ]);
            }
            DB::table('tr_dt_cut_main')->where('id', $id)
                ->update(['end_time' => now()->format('H:i:s'), 'finish' => 1, 'updated_at' => now()]);
        });
        AuditLogger::record($request, "Stop downtime cutting #{$id}");

        return ApiResponse::item(['id' => $id, 'finish' => 1]);
    }

    /** Downtime rows of a cutting transaction (to show a running stop). */
    public function downtimes(int $id)
    {
        $rows = DB::table('tr_dt_cut_main as d')
            ->leftJoin('tr_dt_category as c', 'c.id', '=', 'd.cat_id')
            ->where('d.cut_id', $id)->orderByDesc('d.id')
            ->get(['d.id', 'd.det_cut_id', 'd.machine_id', 'd.cat_id', 'c.name_c_dt as category', 'd.descriptions', 'd.start_time', 'd.end_time', 'd.finish']);

        return ApiResponse::collection($rows);
    }

    /**
     * Input Abnormality — scrap/NG found on scanned bars. Writes the
     * abnormality header + its serial rows, and the NG quantities that the
     * KPL "Qty (NG)" column reads.
     */
    public function abnormalSave(Request $request)
    {
        $data = $request->validate([
            'det_cut_id' => ['required', 'integer', 'exists:tr_cut_detail,id'],
            'note' => ['nullable', 'string', 'max:150'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.serial_id' => ['required', 'string', 'max:50'],
            'rows.*.qty' => ['required', 'integer', 'min:1'],
        ]);
        $det = tr_cut_detail::findOrFail($data['det_cut_id']);
        $main = tr_cut_main::findOrFail($det->main_id);
        $user = $this->userCode($request);

        $abId = DB::transaction(function () use ($data, $det, $main, $user) {
            $abId = DB::table('tr_ab_cut_main')->insertGetId([
                'code' => substr('AB-' . $main->code . '-' . $det->id, 0, 20),
                'wip_id' => $main->wip_id, 'user_id' => $user,
                'cut_id' => $main->id, 'det_cut_id' => $det->id,
                'process_id' => $main->process_id, 'date' => $main->date,
                'machine_id' => $det->machine_id, 'item_id' => $main->item_id,
                'note' => $data['note'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            // only the finding is recorded here — NG vs repair is decided later
            foreach ($data['rows'] as $r) {
                DB::table('tr_ab_cut_det')->insert([
                    'main_id' => $abId, 'serial_id' => $r['serial_id'], 'qty' => (int) $r['qty'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $abId;
        });
        AuditLogger::record($request, "Abnormality cutting #{$abId} ({$main->code})", $main->code);

        return ApiResponse::item(['id' => $abId], 201);
    }

    /** Qty Act / Length Remaining / Finish on a scanned bar. */
    public function updateSerial(Request $request, int $serialId)
    {
        $data = $request->validate([
            'qty' => ['nullable', 'integer', 'min:0'],
            'length_rem' => ['nullable', 'numeric', 'min:0'],
            'finish' => ['nullable', 'boolean'],
        ]);
        $s = tr_cut_serial::findOrFail($serialId);

        // on a repair run the redone qty may not exceed what was judged repairable
        $detOf = tr_cut_detail::findOrFail($s->detail_id);
        $mainOf = tr_cut_main::findOrFail($detOf->main_id);
        if ((int) $mainOf->repair === 1 && isset($data['qty'])) {
            $wipOf = prd_wip::findOrFail($mainOf->wip_id);
            $cap = ($this->outstandingRepair((int) $wipOf->id)[$s->serial_id] ?? 0) + (int) $s->qty;
            if ((int) $data['qty'] > $cap) {
                throw BizException::make('CUT_REPAIR_OVER', "Qty repair melebihi sisa yang harus dikerjakan ulang (maks {$cap}).");
            }
        }

        $s->update(array_filter([
            'qty' => $data['qty'] ?? null,
            'length_rem' => $data['length_rem'] ?? null,
            'finish' => isset($data['finish']) ? (int) $data['finish'] : null,
        ], fn ($v) => $v !== null));

        $det = tr_cut_detail::findOrFail($s->detail_id);

        return $this->show((int) $det->main_id);
    }

    public function removeSerial(Request $request, int $serialId)
    {
        $s = tr_cut_serial::findOrFail($serialId);
        $det = tr_cut_detail::findOrFail($s->detail_id);
        $s->delete();

        return $this->show((int) $det->main_id);
    }

    /**
     * "Finish Cutting" — stop the machines, consume the bars and drop the cut
     * pieces onto a pallet that the Processing screen can scan.
     */
    public function finish(Request $request, int $id)
    {
        $main = tr_cut_main::findOrFail($id);
        $wip = prd_wip::findOrFail($main->wip_id);
        $wo = prd_wo_main::findOrFail($wip->wo_id);

        $details = tr_cut_detail::where('main_id', $main->id)->get();
        if ($details->isEmpty()) {
            throw BizException::make('CUT_NO_MACHINE', 'Belum ada mesin pada transaksi ini.');
        }
        $serials = tr_cut_serial::whereIn('detail_id', $details->pluck('id'))->get();
        $total = (int) $serials->sum('qty');
        if ($total <= 0) {
            throw BizException::make('CUT_NO_QTY', 'Belum ada hasil potong (Qty Act masih 0).');
        }

        $steps = (new PlanningService)->routing((int) $wo->fg_id, $wo->process_main_id ? (int) $wo->process_main_id : null);
        $nextProc = isset($steps[1]) ? (int) $steps[1]['proc_id'] : (int) $main->process_id;

        $pallet = DB::transaction(function () use ($main, $details, $serials, $total, $nextProc, $wip, $wo) {
            foreach ($details as $d) {
                $d->update(['end_time' => now()->format('H:i:s'), 'finish' => 1]);
            }
            // consume the booked bars by what the operator reported as remaining
            foreach ($serials as $s) {
                prd_wo_serial_rm::whereHas('detail', fn ($q) => $q->where('main_id', $wo->id))
                    ->where('serial_id', $s->serial_id)
                    ->update(['length_rem' => (float) $s->length_rem]);
            }

            $n = tr_cut_pal_pr::where('cut_id', $main->id)->count() + 1;
            return tr_cut_pal_pr::create([
                'code' => substr(sprintf('%s-%d-1', $wip->code, $n), 0, 20),
                'cut_id' => $main->id, 'qty' => $total, 'process_id' => $nextProc,
            ]);
        });

        AuditLogger::record($request, "Finish cutting {$main->code}: {$total} pcs → pallet {$pallet->code}", $main->code);

        return $this->show($id);
    }
}
