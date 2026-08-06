<?php

namespace App\Http\Controllers\Api\Production;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\prd_wip;
use App\Models\prd_wo_main;
use App\Models\tr_cut_main;
use App\Models\tr_cut_pal_pr;
use App\Models\tr_pro_detail;
use App\Models\tr_pro_main;
use App\Models\tr_pro_pal_pr;
use App\Models\tr_pro_pallet;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\PlanningService;
use App\Support\WhsStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Processing Transaction (MES, every routing step after cutting) on the
 * original schema:
 *   tr_pro_main    — header (WIP + Denpyou + process + qty half/full + timer)
 *   tr_pro_detail  — one row per MACHINE working it
 *   tr_pro_pallet  — the pallets scanned into a machine (qty half/full act)
 *   tr_pro_pal_pr  — the pallet produced, carrying pal_code_bf (source pallet)
 *
 * Half = one side finished, Full = both sides finished. A piece therefore goes
 * raw → half → full, which is what "Continue Process" (cont_pro) completes.
 */
class ProcessingController extends Controller
{
    /** Machines that may work one processing transaction at the same time. */
    public const MAX_MACHINE = 2;

    /**
     * Seconds between two "H:i:s" clock marks (up to now when $end is null), so
     * the on-screen timers follow the server clock, not the tablet's.
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
     * Repair still owed on this lot, per pallet: judged repairable minus what a
     * repair run (tr_pro_main.repair = 1) has already redone. Those pieces must
     * pass this process again.
     *
     * @return array<string, int> pallet_code => qty still to redo
     */
    private function outstandingRepair(int $wipId): array
    {
        $proIds = tr_pro_main::where('wip_id', $wipId)->pluck('id');
        $abIds = DB::table('tr_ab_pro')->whereIn('pro_id', $proIds ?: [0])->pluck('id');
        $decided = DB::table('tr_repair_pro')->whereIn('main_id', $abIds ?: [0])
            ->selectRaw('pallet_code, SUM(qty) q')->groupBy('pallet_code')->pluck('q', 'pallet_code');

        $repairDetIds = tr_pro_detail::whereIn('main_id', tr_pro_main::where('wip_id', $wipId)->where('repair', 1)->pluck('id') ?: [0])->pluck('id');
        $redone = tr_pro_pallet::whereIn('detail_id', $repairDetIds ?: [0])
            ->selectRaw('pallet_code, SUM(qty_half + qty_full) q')->groupBy('pallet_code')->pluck('q', 'pallet_code');

        $out = [];
        foreach ($decided as $code => $q) {
            $left = (int) $q - (int) ($redone[$code] ?? 0);
            if ($left > 0) {
                $out[$code] = $left;
            }
        }

        return $out;
    }

    private function userCode(Request $request): string
    {
        return substr(sprintf('U%04d', $request->user()->id), 0, 5);
    }

    /** Every source pallet of a WIP with what still needs half / full work. */
    private function sourcePallets(prd_wip $wip): array
    {
        $cutIds = tr_cut_main::where('wip_id', $wip->id)->pluck('id');
        $fromCut = tr_cut_pal_pr::whereIn('cut_id', $cutIds)->get()
            ->map(fn ($p) => ['code' => $p->code, 'qty' => (int) $p->qty, 'cut_id' => (int) $p->cut_id, 'process_id' => (int) $p->process_id]);

        $proIds = tr_pro_main::where('wip_id', $wip->id)->pluck('id');
        $fromPro = tr_pro_pal_pr::whereIn('pro_id', $proIds)->get()
            ->map(fn ($p) => ['code' => $p->code, 'qty' => (int) $p->qty, 'cut_id' => (int) $p->cut_id, 'process_id' => (int) $p->process_id]);

        $all = $fromCut->concat($fromPro)->values();

        return $all->map(function ($p) {
            $doneHalf = (int) tr_pro_pallet::where('pallet_code', $p['code'])->sum('qty_half');
            $doneFull = (int) tr_pro_pallet::where('pallet_code', $p['code'])->sum('qty_full');
            $p['qty_half_need'] = max(0, $p['qty'] - $doneHalf);   // pieces still raw
            $p['qty_full_need'] = max(0, $doneHalf - $doneFull);   // half-done awaiting 2nd side
            $p['done_half'] = $doneHalf;
            $p['done_full'] = $doneFull;

            return $p;
        })->all();
    }

    /**
     * Read-only "View Processing Data": the header with the lot quantities,
     * then each machine with its operator, running time and the pallets it took
     * in (how many went to half and how many were finished).
     */
    public function view(int $id)
    {
        $main = tr_pro_main::with(['item', 'process'])->findOrFail($id);
        $wip = prd_wip::find($main->wip_id);
        $wo = $wip ? prd_wo_main::with('customer')->find($wip->wo_id) : null;

        $downtimes = DB::table('tr_dt_pro_main as d')->leftJoin('tr_dt_category as c', 'c.id', '=', 'd.cat_id')
            ->where('d.pro_id', $main->id)
            ->get(['d.det_pro_id', 'd.start_time', 'd.end_time', 'd.note', DB::raw('c.name_c_dt as category')])
            ->groupBy('det_pro_id');

        $machines = tr_pro_detail::with('machine')->where('main_id', $main->id)->orderBy('id')->get()
            ->map(function ($d) use ($downtimes) {
                $secs = $this->elapsedSec($d->start_time, $d->end_time);

                return [
                    'machine' => $d->machine ? $d->machine->code.' — '.$d->machine->name : '—',
                    'operator' => $d->user_id,
                    'start_time' => $d->start_time, 'end_time' => $d->end_time,
                    'duration' => $secs === null ? null : intdiv($secs, 3600).'h '.intdiv($secs % 3600, 60).'m',
                    'downtimes' => ($downtimes[$d->id] ?? collect())->map(fn ($x) => [
                        'category' => $x->category, 'note' => $x->note,
                        'start_time' => $x->start_time, 'end_time' => $x->end_time,
                    ])->values(),
                    'pallets' => tr_pro_pallet::where('detail_id', $d->id)->orderBy('id')->get()
                        ->map(fn ($p) => [
                            'pallet_code' => $p->pallet_code,
                            'qty_half' => (int) $p->qty_half,
                            'qty_finish' => (int) $p->qty_full,
                        ])->values(),
                ];
            });

        return ApiResponse::item([
            'main' => [
                'wip_code' => $wip?->code, 'date' => $main->date,
                'fg_code' => $main->item?->code, 'customer' => $wo?->customer?->company_n,
                'qty_wip' => (int) ($wo->qty ?? 0), 'process' => $main->process?->name_p ?? $main->process?->code,
                'qty_half' => (int) $main->qty_half, 'qty_full' => (int) $main->qty_full,
                'code' => $main->code, 'no_dp' => $main->no_dp,
                'shift' => DB::table('m_shift')->where('id', $main->shift_id)->value('name'),
            ],
            'machines' => $machines,
            'pallets' => tr_pro_pal_pr::where('pro_id', $main->id)->orderBy('id')->get(['id', 'code', 'qty', 'status', 'pal_code_bf']),
        ]);
    }

    /** Processing Transactions list — running vs finished (tr_pro_main.finish). */
    public function index(Request $request)
    {
        $q = tr_pro_main::with(['item', 'process'])->orderByDesc('id');
        if ($s = trim((string) $request->query('q', ''))) {
            $q->where('code', 'like', "%{$s}%")->orWhere('no_dp', 'like', "%{$s}%");
        }
        $page = $q->paginate(min(max((int) $request->query('per_page', 25), 1), 200));

        $wipCodes = prd_wip::whereIn('id', $page->getCollection()->pluck('wip_id'))->pluck('code', 'id');
        $page->getCollection()->transform(function ($m) use ($wipCodes) {
            $m->wip_code = $wipCodes[$m->wip_id] ?? null;
            $m->item_code = $m->item?->code;
            $m->process_code = $m->process?->code;
            $m->status = (int) $m->finish === 1 ? 'FINISHED' : 'RUNNING';

            return $m;
        });

        return ApiResponse::paginated($page);
    }

    /**
     * Scan Pallet Code — the entry point of the Processing screen. The pallet
     * tells us the lot (WIP), the item, what still needs half/full work, and
     * where it came from: a pallet left HALF may only continue on the SAME
     * process (finishing its second side); one that is FULL or straight off
     * cutting may go to any later routing step.
     */
    public function checkPallet(Request $request)
    {
        $data = $request->validate(['pallet_code' => ['required', 'string', 'max:50']]);
        $code = trim($data['pallet_code']);

        $fromPro = tr_pro_pal_pr::where('code', $code)->first();
        $fromCut = $fromPro ? null : tr_cut_pal_pr::where('code', $code)->first();
        if (! $fromPro && ! $fromCut) {
            throw BizException::make('PRO_PALLET', "Pallet '{$code}' tidak ditemukan.");
        }

        $cutId = (int) ($fromPro->cut_id ?? $fromCut->cut_id);
        $cut = tr_cut_main::find($cutId);
        $wip = $cut ? prd_wip::find($cut->wip_id) : null;
        if (! $wip) {
            throw BizException::make('PRO_WIP', 'WIP untuk pallet ini tidak ditemukan.');
        }
        $wo = prd_wo_main::with('fg')->find($wip->wo_id);

        $src = collect($this->sourcePallets($wip))->firstWhere('code', $code);
        if (! $src) {
            throw BizException::make('PRO_PALLET_SRC', 'Pallet ini bukan bagian dari lot yang aktif.');
        }

        $oldStatus = $fromPro ? strtolower((string) $fromPro->status) : 'cut';

        return ApiResponse::item([
            'pallet_code' => $code,
            'cut_id' => $cutId,
            'pro_id_before' => $fromPro->pro_id ?? null,
            'wip_id' => $wip->id, 'wip_code' => $wip->code, 'no_lot' => substr($wip->code, 4),
            'no_dp' => $wip->no_dp,
            'item_id' => $wo?->fg_id, 'item_code' => $wo?->fg?->code, 'item_name' => $wo?->fg?->part_name,
            'size' => (float) ($wo?->fg?->length ?? 0),
            'qty_half_needs' => (int) $src['qty_half_need'],
            'qty_full_needs' => (int) $src['qty_full_need'],
            'old_status' => $oldStatus,                                   // half · full · cut
            'old_process_id' => $fromPro ? (int) $fromPro->process_id : null,
            'suggest_code' => substr(sprintf('%s-P%03d', $wip->code, tr_pro_main::where('wip_id', $wip->id)->count() + 1), 0, 20),
        ]);
    }

    /** Header context for a WIP: item, process to run, needs and pallets. */
    public function wip(string $code)
    {
        $wip = prd_wip::where('code', $code)->first();
        if (! $wip) {
            throw BizException::make('PRO_WIP', "WIP '{$code}' tidak ditemukan.");
        }
        $wo = prd_wo_main::with('fg')->find($wip->wo_id);
        if (! $wo) {
            throw BizException::make('PRO_WO', 'Work Order untuk WIP ini sudah tidak ada.');
        }

        $steps = (new PlanningService)->routing((int) $wo->fg_id, $wo->process_main_id ? (int) $wo->process_main_id : null);
        if (count($steps) < 2) {
            throw BizException::make('PRO_NO_STEP', 'FG ini tidak punya proses setelah cutting.');
        }
        // the first step after cutting that is not yet complete
        $procNames = DB::table('m_process')->pluck('name_p', 'id');
        $procCodes = DB::table('m_process')->pluck('code', 'id');
        $seq = 2;
        for ($i = 1; $i < count($steps); $i++) {
            $pid = (int) $steps[$i]['proc_id'];
            $done = (int) tr_pro_detail::whereIn('main_id', tr_pro_main::where('wip_id', $wip->id)->where('process_id', $pid)->pluck('id'))->sum('qty_full');
            if ($done < (int) $wo->qty) {
                $seq = $i + 1;
                break;
            }
        }
        $procId = (int) $steps[$seq - 1]['proc_id'];

        $pallets = $this->sourcePallets($wip);
        $n = tr_pro_main::where('wip_id', $wip->id)->count() + 1;

        return ApiResponse::item([
            'wip' => ['id' => $wip->id, 'code' => $wip->code],
            'wo' => ['id' => $wo->id, 'code' => $wo->code, 'qty' => (int) $wo->qty],
            'item' => $wo->fg?->only(['id', 'code', 'part_name', 'length']),
            'size_fg' => (float) ($wo->fg->length ?? 0),
            'process_id' => $procId,
            'process' => ['id' => $procId, 'code' => $procCodes[$procId] ?? '-', 'name' => $procNames[$procId] ?? '-'],
            'sq_process' => $seq,
            'suggest_code' => substr(sprintf('%s-P%d-%03d', $wip->code, $seq, $n), 0, 20),
            'suggest_dp' => 'OP-'.substr($wip->code, 4),
            'qty_half_needs' => array_sum(array_column($pallets, 'qty_half_need')),
            'qty_full_needs' => array_sum(array_column($pallets, 'qty_full_need')),
            'pallets' => $pallets,
        ]);
    }

    /** Kartu Pengawasan Lot (KPL) — the lot's flow across every process. */
    public function kpl(string $code)
    {
        $wip = prd_wip::where('code', $code)->first();
        if (! $wip) {
            throw BizException::make('KPL_WIP', "WIP '{$code}' tidak ditemukan.");
        }
        $wo = prd_wo_main::with('fg')->find($wip->wo_id);
        if (! $wo) {
            throw BizException::make('KPL_WO', 'Work Order untuk WIP ini sudah tidak ada.');
        }
        $steps = (new PlanningService)->routing((int) $wo->fg_id, $wo->process_main_id ? (int) $wo->process_main_id : null);
        $procNames = DB::table('m_process')->pluck('name_p', 'id');
        $procCodes = DB::table('m_process')->pluck('code', 'id');

        $cutIds = tr_cut_main::where('wip_id', $wip->id)->pluck('id');
        $cutDetIds = DB::table('tr_cut_detail')->whereIn('main_id', $cutIds)->pluck('id');
        $cutQty = (int) DB::table('tr_cut_serial')->whereIn('detail_id', $cutDetIds)->sum('qty');
        $cutNg = (int) DB::table('tr_ng_cut')
            ->whereIn('main_id', DB::table('tr_ab_cut_main')->whereIn('cut_id', $cutIds)->select('id'))->sum('qty');

        $flow = [];
        foreach ($steps as $i => $s) {
            $pid = (int) $s['proc_id'];
            $seq = $i + 1;
            if ($seq === 1) {
                $trx = $cutQty;
                $ng = $cutNg;
            } else {
                $proIds = tr_pro_main::where('wip_id', $wip->id)->where('process_id', $pid)->pluck('id');
                $trx = (int) tr_pro_detail::whereIn('main_id', $proIds)->sum('qty_full');
                $ng = (int) DB::table('tr_ng_pro')
                    ->whereIn('main_id', DB::table('tr_ab_pro')->whereIn('pro_id', $proIds)->select('id'))->sum('qty');
            }
            $flow[] = [
                'op' => 'OP'.$seq.($seq === 1 ? ' (Cutting)' : ''),
                'process' => $procNames[$pid] ?? ($procCodes[$pid] ?? '-'),
                'process_id' => $pid,
                'sequence' => $seq,
                'qty_denpyou' => (int) $wo->qty,
                'qty_transaksi' => $trx,
                'qty_ng' => $ng,
            ];
        }

        return ApiResponse::item([
            'nomor_lot' => substr($wip->code, 4),
            'qr_code' => 'OP-'.substr($wip->code, 4),
            'qty' => (int) $wo->qty,
            'nomor_barang' => $wo->fg?->code,
            'nama_barang' => $wo->fg?->part_name,
            'flow' => $flow,
        ]);
    }

    public function start(Request $request)
    {
        $data = $request->validate([
            'wip_id' => ['required', 'integer', 'exists:prd_wip,id'],
            'no_dp' => ['required', 'string', 'max:20'],
            'date' => ['required', 'date'],
            'process_id' => ['required', 'integer', 'exists:m_process,id'],
            'sq_process' => ['required', 'integer', 'min:2'],
            'code' => ['required', 'string', 'max:20'],
            'pallet_code' => ['required', 'string', 'max:30'],
            'cut_id' => ['nullable', 'integer'],
            'shift_id' => ['nullable', 'integer', 'exists:m_shift,id'],
            'cont_pro' => ['nullable', 'boolean'],
            'subcont' => ['nullable', 'boolean'],
            'subcon_code' => ['nullable', 'string', 'max:50'],
            'repair' => ['nullable', 'boolean'],
        ]);
        $wip = prd_wip::findOrFail($data['wip_id']);
        $wo = prd_wo_main::findOrFail($wip->wo_id);
        $cutId = $data['cut_id'] ?? tr_cut_main::where('wip_id', $wip->id)->value('id');

        $main = tr_pro_main::create([
            'code' => $data['code'],
            'wip_id' => $wip->id,
            'cut_id' => $cutId,
            'item_id' => $wo->fg_id,
            'user_id' => $this->userCode($request),
            'process_id' => $data['process_id'],
            'pallet_code' => $data['pallet_code'],
            'no_dp' => $data['no_dp'],
            'date' => $data['date'],
            'start_time' => now()->format('H:i:s'),
            'shift_id' => $data['shift_id'] ?? null,
            'sq_process' => $data['sq_process'],
            'cont_pro' => (int) ($data['cont_pro'] ?? 0),
            'subcont' => (int) ($data['subcont'] ?? 0),
            'subcon_code' => $data['subcon_code'] ?? null,
            'repair' => (int) ($data['repair'] ?? 0),
            'finish' => 0,
            'client_uuid' => $request->input('client_uuid'),
        ]);
        AuditLogger::record($request, "Start processing {$main->code} (WIP {$wip->code})", $main->code);

        return $this->show($main->id)->setStatusCode(201);
    }

    public function show(int $id)
    {
        $main = tr_pro_main::with(['item', 'process'])->findOrFail($id);
        $wip = prd_wip::find($main->wip_id);
        $wo = $wip ? prd_wo_main::find($wip->wo_id) : null;
        $src = $wip ? collect($this->sourcePallets($wip))->keyBy('code') : collect();

        // a machine is stopped while its downtime has no end_time yet
        $dtRun = DB::table('tr_dt_pro_main as d')->leftJoin('tr_dt_category as c', 'c.id', '=', 'd.cat_id')
            ->where('d.pro_id', $main->id)->whereNull('d.end_time')
            ->get(['d.id', 'd.det_pro_id', 'd.start_time', 'c.name_c_dt as category'])->keyBy('det_pro_id');

        $machines = tr_pro_detail::with('machine')->where('main_id', $main->id)->orderBy('id')->get()
            ->map(function ($d) use ($src, $dtRun) {
                $rows = tr_pro_pallet::where('detail_id', $d->id)->orderBy('id')->get()->map(function ($p) use ($src) {
                    $s = $src->get($p->pallet_code);

                    return [
                        'id' => $p->id, 'pallet_code' => $p->pallet_code,
                        'qty_half_need' => (int) ($s['qty_half_need'] ?? 0) + (int) $p->qty_half,
                        'qty_full_need' => (int) ($s['qty_full_need'] ?? 0) + (int) $p->qty_full,
                        'qty_half' => (int) $p->qty_half, 'qty_full' => (int) $p->qty_full,
                        'finish' => (int) $p->finish,
                    ];
                })->values();

                return [
                    'id' => $d->id, 'machine_id' => $d->machine_id,
                    'machine' => $d->machine?->only(['id', 'code', 'name']),
                    'user_id' => $d->user_id, 'finish' => (int) $d->finish,
                    'start_time' => $d->start_time, 'end_time' => $d->end_time,
                    'elapsed_sec' => $this->elapsedSec($d->start_time, $d->end_time),
                    'downtime' => $dtRun->get($d->id) ? [
                        'id' => $dtRun[$d->id]->id,
                        'start_time' => $dtRun[$d->id]->start_time,
                        'elapsed_sec' => $this->elapsedSec($dtRun[$d->id]->start_time),
                        'category' => $dtRun[$d->id]->category,
                    ] : null,
                    'qty_half' => (int) $rows->sum('qty_half'), 'qty_full' => (int) $rows->sum('qty_full'),
                    'pallets' => $rows,
                ];
            });

        return ApiResponse::item([
            'id' => $main->id, 'code' => $main->code, 'no_dp' => $main->no_dp, 'date' => $main->date,
            'pallet_code' => $main->pallet_code,
            'user_id' => $main->user_id, 'finish' => (int) $main->finish, 'cont_pro' => (int) $main->cont_pro,
            'sq_process' => (int) $main->sq_process, 'subcont' => (int) $main->subcont, 'subcon_code' => $main->subcon_code,
            'start_time' => $main->start_time, 'end_time' => $main->end_time,
            'wip' => $wip ? ['id' => $wip->id, 'code' => $wip->code] : null,
            'wo' => $wo ? ['id' => $wo->id, 'code' => $wo->code, 'qty' => (int) $wo->qty] : null,
            'item' => $main->item?->only(['id', 'code', 'part_name', 'length']),
            'process' => $main->process?->only(['id', 'code', 'name_p']),
            'repair' => (int) $main->repair,
            'machines' => $machines,
            'repair_outstanding' => $wip ? collect($this->outstandingRepair((int) $wip->id))
                ->map(fn ($q, $code) => ['pallet_code' => $code, 'qty' => $q])->values() : collect(),
            'available_pallets' => $src->values(),
            'out_pallets' => tr_pro_pal_pr::where('pro_id', $main->id)->get(['id', 'code', 'qty', 'status', 'pal_code_bf']),
        ]);
    }

    /**
     * Input Downtime → Start. tr_dt_pro_main has no finish flag: a stop is
     * running for as long as its end_time is null.
     */
    public function dtStart(Request $request)
    {
        $data = $request->validate([
            'det_pro_id' => ['required', 'integer', 'exists:tr_pro_detail,id'],
            'cat_id' => ['required', 'integer', 'exists:tr_dt_category,id'],
            'note' => ['nullable', 'string', 'max:50'],
        ]);
        $det = tr_pro_detail::findOrFail($data['det_pro_id']);
        $main = tr_pro_main::findOrFail($det->main_id);
        if (DB::table('tr_dt_pro_main')->where('det_pro_id', $det->id)->whereNull('end_time')->exists()) {
            throw BizException::make('DT_RUNNING', 'Masih ada downtime berjalan pada mesin ini.');
        }

        $now = now()->format('H:i:s');
        $id = DB::table('tr_dt_pro_main')->insertGetId([
            'pro_id' => $main->id,
            'code' => substr('DT-'.$main->code.'-'.$det->id, 0, 50),
            'user_id' => $this->userCode($request),
            'machine_id' => (int) $det->machine_id,
            'det_pro_id' => $det->id,
            'cut_id' => (int) ($main->cut_id ?? 0),
            'cat_id' => $data['cat_id'],
            'note' => $data['note'] ?? null,
            'start_time' => $now,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        AuditLogger::record($request, "Start downtime processing #{$id} ({$main->code})", $main->code);

        return ApiResponse::item(['id' => $id, 'start_time' => $now], 201);
    }

    /** Input Downtime → Save: record the tools used and stop the downtime. */
    public function dtSave(Request $request, int $id)
    {
        $data = $request->validate([
            'tools' => ['array'],
            'tools.*.serial_tool' => ['required', 'string', 'max:50'],
            'tools.*.tools_id' => ['nullable', 'integer'],
        ]);
        if (! DB::table('tr_dt_pro_main')->where('id', $id)->exists()) {
            throw BizException::make('DT_404', 'Downtime tidak ditemukan.');
        }
        // Sama seperti downtime cutting: serial harus milik gudang WHS, supaya
        // riwayat penggantian sparepart per mesin bisa dipercaya.
        $resolved = app(WhsStockService::class)->resolveCodes($data['tools'] ?? [], 'serial_tool');

        $now = now()->format('H:i:s');
        DB::transaction(function () use ($resolved, $id, $now) {
            foreach ($resolved as $t) {
                DB::table('tr_dt_pro_detail')->insert([
                    'main_id' => $id, 'serial_tool' => $t['serial'], 'tools_id' => $t['item_id'],
                ]);
            }
            DB::table('tr_dt_pro_main')->where('id', $id)->update(['end_time' => $now, 'updated_at' => $now]);
        });
        AuditLogger::record($request, "Stop downtime processing #{$id}");

        return ApiResponse::item(['id' => $id, 'end_time' => $now]);
    }

    /** Downtime categories (also reachable for processing-only operators). */
    public function dtCategories()
    {
        return ApiResponse::collection(DB::table('tr_dt_category')->orderBy('name_c_dt')->get(['id', 'name_c_dt', 'descriptions']));
    }

    /**
     * Input Abnormality — NG / repair found on the pallets being worked. One
     * tr_ab_pro row per pallet (the table carries pallet_code but no qty); the
     * quantity hangs off it in tr_ng_pro, or tr_repair_pro when it is repairable.
     * This is what fills the KPL "Qty (NG)" column for processing steps.
     */
    public function abnormalSave(Request $request)
    {
        $data = $request->validate([
            'det_pro_id' => ['required', 'integer', 'exists:tr_pro_detail,id'],
            'note' => ['nullable', 'string', 'max:50'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.pallet_code' => ['required', 'string', 'max:50'],
            'rows.*.qty' => ['required', 'integer', 'min:1'],
        ]);
        $det = tr_pro_detail::findOrFail($data['det_pro_id']);
        $main = tr_pro_main::findOrFail($det->main_id);
        $user = $this->userCode($request);

        $ids = DB::transaction(function () use ($data, $det, $main, $user) {
            $ids = [];
            foreach ($data['rows'] as $r) {
                $abId = DB::table('tr_ab_pro')->insertGetId([
                    'pro_id' => $main->id,
                    'wip_id' => (int) $main->wip_id,
                    'cut_id' => (int) ($main->cut_id ?? 0),
                    'det_pro_id' => $det->id,
                    'process_id' => (int) $main->process_id,
                    'pallet_code' => $r['pallet_code'],
                    'qty' => (int) $r['qty'],
                    'code' => substr('AB-'.$main->code.'-'.$det->id, 0, 50),
                    'date' => now(),
                    'user_id' => $user,
                    'item_id' => (int) $main->item_id,
                    'machine_id' => (int) $det->machine_id,
                    'note' => $data['note'] ?? null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                // NG vs repair is decided later, on the Abnormal Decision screen
                $ids[] = $abId;
            }

            return $ids;
        });
        AuditLogger::record($request, 'Abnormality processing '.$main->code.': '.count($ids).' pallet', $main->code);

        return ApiResponse::item(['ids' => $ids], 201);
    }

    /** Downtime rows of a processing transaction. */
    public function downtimes(int $id)
    {
        $rows = DB::table('tr_dt_pro_main as d')
            ->leftJoin('tr_dt_category as c', 'c.id', '=', 'd.cat_id')
            ->where('d.pro_id', $id)->orderByDesc('d.id')
            ->get(['d.id', 'd.det_pro_id', 'd.machine_id', 'd.cat_id', 'c.name_c_dt as category', 'd.note', 'd.start_time', 'd.end_time']);

        return ApiResponse::collection($rows);
    }

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
                throw BizException::make('PRO_MACHINE', "Mesin '{$data['mach_code']}' tidak ditemukan.");
            }
        }
        if (! $machineId) {
            throw BizException::make('PRO_MACHINE', 'Scan kode mesin terlebih dahulu.');
        }
        $main = tr_pro_main::findOrFail($id);
        if (tr_pro_detail::where('main_id', $main->id)->where('machine_id', $machineId)->where('finish', 0)->exists()) {
            throw BizException::make('PRO_MACHINE_DUP', 'Mesin ini sudah aktif pada transaksi ini.');
        }
        if (tr_pro_detail::where('main_id', $main->id)->count() >= self::MAX_MACHINE) {
            throw BizException::make('PRO_MACHINE_MAX', 'Maksimal '.self::MAX_MACHINE.' mesin per transaksi processing.');
        }
        tr_pro_detail::create([
            'main_id' => $main->id, 'machine_id' => $machineId,
            'user_id' => $this->userCode($request), 'qty_half' => 0, 'qty_full' => 0,
            'finish' => 0, 'start_time' => now()->format('H:i:s'),
        ]);

        return $this->show($id);
    }

    public function removeMachine(Request $request, int $detailId)
    {
        $det = tr_pro_detail::findOrFail($detailId);
        DB::transaction(function () use ($det) {
            tr_pro_pallet::where('detail_id', $det->id)->delete();
            $det->delete();
        });

        return $this->show((int) $det->main_id);
    }

    /** "Scan Pallet Code" — bring a pallet onto this machine. */
    public function addPallet(Request $request, int $detailId)
    {
        $data = $request->validate(['pallet_code' => ['required', 'string', 'max:50']]);
        $det = tr_pro_detail::findOrFail($detailId);
        $main = tr_pro_main::findOrFail($det->main_id);
        $wip = prd_wip::findOrFail($main->wip_id);

        $src = collect($this->sourcePallets($wip))->firstWhere('code', $data['pallet_code']);
        if (! $src) {
            throw BizException::make('PRO_PALLET', "Pallet '{$data['pallet_code']}' bukan milik WIP ini.");
        }
        if (($src['qty_half_need'] + $src['qty_full_need']) <= 0) {
            throw BizException::make('PRO_PALLET_DONE', 'Pallet ini sudah selesai dikerjakan.');
        }
        if (tr_pro_pallet::whereIn('detail_id', tr_pro_detail::where('main_id', $main->id)->pluck('id'))
            ->where('pallet_code', $data['pallet_code'])->exists()) {
            throw BizException::make('PRO_PALLET_DUP', 'Pallet ini sudah discan pada transaksi ini.');
        }
        // a repair run may only redo pallets that were judged repairable
        if ((int) $main->repair === 1 && ! isset($this->outstandingRepair((int) $wip->id)[$data['pallet_code']])) {
            throw BizException::make('PRO_REPAIR_NONE', "Pallet '{$data['pallet_code']}' tidak punya sisa repair yang harus dikerjakan ulang.");
        }

        tr_pro_pallet::create([
            'detail_id' => $det->id, 'cut_id' => $src['cut_id'],
            'pallet_code' => $data['pallet_code'], 'qty_half' => 0, 'qty_full' => 0, 'finish' => 0,
        ]);

        return $this->show((int) $main->id);
    }

    public function updatePallet(Request $request, int $palletId)
    {
        $data = $request->validate([
            'qty_half' => ['nullable', 'integer', 'min:0'],
            'qty_full' => ['nullable', 'integer', 'min:0'],
            'finish' => ['nullable', 'boolean'],
        ]);
        $p = tr_pro_pallet::findOrFail($palletId);
        $det = tr_pro_detail::findOrFail($p->detail_id);
        $main = tr_pro_main::findOrFail($det->main_id);
        $wip = prd_wip::findOrFail($main->wip_id);

        // on a repair run the cap is the outstanding repair qty, not the routing need
        if ((int) $main->repair === 1) {
            $left = ($this->outstandingRepair((int) $wip->id)[$p->pallet_code] ?? 0) + (int) $p->qty_half + (int) $p->qty_full;
            $wanted = ($data['qty_half'] ?? (int) $p->qty_half) + ($data['qty_full'] ?? (int) $p->qty_full);
            if ($wanted > $left) {
                throw BizException::make('PRO_REPAIR_OVER', "Qty repair melebihi sisa yang harus dikerjakan ulang (maks {$left}).");
            }
        }

        $src = collect($this->sourcePallets($wip))->firstWhere('code', $p->pallet_code);
        $maxHalf = (int) ($src['qty_half_need'] ?? 0) + (int) $p->qty_half;
        $maxFull = (int) ($src['qty_full_need'] ?? 0) + (int) $p->qty_full;
        if (isset($data['qty_half']) && $data['qty_half'] > $maxHalf) {
            throw BizException::make('PRO_OVER_HALF', "Qty Half melebihi kebutuhan ({$maxHalf}).");
        }
        if (isset($data['qty_full']) && $data['qty_full'] > $maxFull) {
            throw BizException::make('PRO_OVER_FULL', "Qty Full melebihi kebutuhan ({$maxFull}).");
        }

        $p->update(array_filter([
            'qty_half' => $data['qty_half'] ?? null,
            'qty_full' => $data['qty_full'] ?? null,
            'finish' => isset($data['finish']) ? (int) $data['finish'] : null,
        ], fn ($v) => $v !== null));

        // roll the machine totals up
        $det->update([
            'qty_half' => (int) tr_pro_pallet::where('detail_id', $det->id)->sum('qty_half'),
            'qty_full' => (int) tr_pro_pallet::where('detail_id', $det->id)->sum('qty_full'),
        ]);

        return $this->show((int) $main->id);
    }

    public function removePallet(Request $request, int $palletId)
    {
        $p = tr_pro_pallet::findOrFail($palletId);
        $det = tr_pro_detail::findOrFail($p->detail_id);
        $p->delete();

        return $this->show((int) $det->main_id);
    }

    /** FINISH — stop machines, roll totals up and print the outgoing pallet. */
    public function finish(Request $request, int $id)
    {
        $main = tr_pro_main::findOrFail($id);
        $wip = prd_wip::findOrFail($main->wip_id);
        $details = tr_pro_detail::where('main_id', $main->id)->get();
        if ($details->isEmpty()) {
            throw BizException::make('PRO_NO_MACHINE', 'Belum ada mesin pada transaksi ini.');
        }
        $rows = tr_pro_pallet::whereIn('detail_id', $details->pluck('id'))->get();
        $half = (int) $rows->sum('qty_half');
        $full = (int) $rows->sum('qty_full');
        if ($half + $full <= 0) {
            throw BizException::make('PRO_NO_QTY', 'Belum ada hasil (Qty Half/Full masih 0).');
        }

        $out = DB::transaction(function () use ($main, $details, $rows, $half, $full, $wip) {
            foreach ($details as $d) {
                $d->update(['end_time' => now()->format('H:i:s'), 'finish' => 1]);
            }
            $main->update([
                'qty_half' => $half, 'qty_full' => $full, 'finish' => 1,
                'end_time' => now()->format('H:i:s'),
            ]);

            $n = tr_pro_pal_pr::where('pro_id', $main->id)->count() + 1;

            return tr_pro_pal_pr::create([
                'code' => substr(sprintf('%s-P%d-%d', $wip->code, (int) $main->sq_process, $n), 0, 50),
                'pro_id' => $main->id,
                'cut_id' => (int) ($main->cut_id ?? $rows->first()->cut_id ?? 0),
                'qty' => $full > 0 ? $full : $half,
                'pal_code_bf' => (string) ($rows->first()->pallet_code ?? '-'),
                'status' => $full > 0 ? 'FULL' : 'HALF',
                'process_id' => (int) $main->process_id,
            ]);
        });

        AuditLogger::record($request, "Finish processing {$main->code}: half {$half} / full {$full} → pallet {$out->code}", $main->code);

        return $this->show($id);
    }
}
