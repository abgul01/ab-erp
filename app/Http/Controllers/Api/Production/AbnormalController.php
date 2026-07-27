<?php

namespace App\Http\Controllers\Api\Production;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Abnormal decision desk. The shop floor only records the FINDING (which serial
 * / pallet, how many pieces). Whether those pieces are repaired or scrapped is
 * judged here, afterwards:
 *
 *   cutting     finding = tr_ab_cut_det (per ab-header + serial)
 *               decision → tr_ng_cut / tr_repair_cut
 *   processing  finding = tr_ab_pro (one row per pallet, carries qty)
 *               decision → tr_ng_pro / tr_repair_pro
 *
 * A finding may be split (some repaired, some scrapped) and decided in stages;
 * it is PENDING until the decided quantity reaches the abnormal quantity.
 * Only the NG side feeds the KPL "Qty (NG)" column.
 */
class AbnormalController extends Controller
{
    private function userCode(Request $request): string
    {
        return substr(sprintf('U%04d', $request->user()->id), 0, 5);
    }

    public function index(Request $request)
    {
        $status = strtoupper((string) $request->query('status', 'PENDING'));

        /* ---------- cutting findings ---------- */
        $cut = DB::table('tr_ab_cut_det as d')
            ->join('tr_ab_cut_main as m', 'm.id', '=', 'd.main_id')
            ->leftJoin('m_item as i', 'i.id', '=', 'm.item_id')
            ->leftJoin('m_machine as mc', 'mc.id', '=', 'm.machine_id')
            ->leftJoin('prd_wip as w', 'w.id', '=', 'm.wip_id')
            ->leftJoin('m_process as p', 'p.id', '=', 'm.process_id')
            ->groupBy('m.id', 'd.serial_id', 'm.date', 'm.note', 'i.code', 'mc.code', 'w.code', 'p.code')
            ->get([
                DB::raw('m.id as main_id'), 'd.serial_id',
                DB::raw('SUM(d.qty) as qty'), 'm.date', 'm.note',
                DB::raw('i.code as item_code'), DB::raw('mc.code as machine'),
                DB::raw('w.code as wip_code'), DB::raw('p.code as process'),
            ]);

        $cutNg = DB::table('tr_ng_cut')->selectRaw('main_id, serial_id, SUM(qty) q')->groupBy('main_id', 'serial_id')->get()
            ->keyBy(fn ($r) => $r->main_id . '|' . $r->serial_id);
        $cutRp = DB::table('tr_repair_cut')->selectRaw('main_id, serial_id, SUM(qty) q')->groupBy('main_id', 'serial_id')->get()
            ->keyBy(fn ($r) => $r->main_id . '|' . $r->serial_id);

        $rows = $cut->map(function ($r) use ($cutNg, $cutRp) {
            $k = $r->main_id . '|' . $r->serial_id;
            $ng = (int) ($cutNg[$k]->q ?? 0);
            $rp = (int) ($cutRp[$k]->q ?? 0);

            return [
                'type' => 'CUT', 'main_id' => (int) $r->main_id, 'serial_id' => $r->serial_id,
                'ref' => $r->serial_id, 'ref_label' => 'Serial',
                'wip_code' => $r->wip_code, 'item_code' => $r->item_code,
                'machine' => $r->machine, 'process' => $r->process,
                'date' => $r->date, 'note' => $r->note,
                'qty' => (int) $r->qty, 'ng' => $ng, 'repair' => $rp,
                'remaining' => max(0, (int) $r->qty - $ng - $rp),
            ];
        });

        /* ---------- processing findings ---------- */
        $pro = DB::table('tr_ab_pro as a')
            ->leftJoin('m_item as i', 'i.id', '=', 'a.item_id')
            ->leftJoin('m_machine as mc', 'mc.id', '=', 'a.machine_id')
            ->leftJoin('prd_wip as w', 'w.id', '=', 'a.wip_id')
            ->leftJoin('m_process as p', 'p.id', '=', 'a.process_id')
            ->get([
                'a.id', 'a.pallet_code', 'a.qty', 'a.date', 'a.note',
                DB::raw('i.code as item_code'), DB::raw('mc.code as machine'),
                DB::raw('w.code as wip_code'), DB::raw('p.code as process'),
            ]);

        $proNg = DB::table('tr_ng_pro')->selectRaw('main_id, SUM(qty) q')->groupBy('main_id')->pluck('q', 'main_id');
        $proRp = DB::table('tr_repair_pro')->selectRaw('main_id, SUM(qty) q')->groupBy('main_id')->pluck('q', 'main_id');

        $rows = $rows->concat($pro->map(function ($r) use ($proNg, $proRp) {
            $ng = (int) ($proNg[$r->id] ?? 0);
            $rp = (int) ($proRp[$r->id] ?? 0);

            return [
                'type' => 'PRO', 'main_id' => (int) $r->id, 'serial_id' => null,
                'ref' => $r->pallet_code, 'ref_label' => 'Pallet',
                'wip_code' => $r->wip_code, 'item_code' => $r->item_code,
                'machine' => $r->machine, 'process' => $r->process,
                'date' => $r->date, 'note' => $r->note,
                'qty' => (int) $r->qty, 'ng' => $ng, 'repair' => $rp,
                'remaining' => max(0, (int) $r->qty - $ng - $rp),
            ];
        }));

        if ($status === 'PENDING') {
            $rows = $rows->filter(fn ($r) => $r['remaining'] > 0);
        } elseif ($status === 'DECIDED') {
            $rows = $rows->filter(fn ($r) => $r['remaining'] <= 0);
        }

        return ApiResponse::collection($rows->sortByDesc('date')->values());
    }

    /** Judge a finding: how many pieces are scrapped (NG) and how many repaired. */
    public function decide(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:CUT,PRO'],
            'main_id' => ['required', 'integer'],
            'serial_id' => ['nullable', 'string', 'max:50'],
            'qty_ng' => ['nullable', 'integer', 'min:0'],
            'qty_repair' => ['nullable', 'integer', 'min:0'],
        ]);
        $ng = (int) ($data['qty_ng'] ?? 0);
        $rp = (int) ($data['qty_repair'] ?? 0);
        if ($ng + $rp <= 0) {
            throw BizException::make('AB_EMPTY', 'Isi qty NG dan/atau qty Repair.');
        }
        $user = $this->userCode($request);

        if ($data['type'] === 'CUT') {
            if (empty($data['serial_id'])) {
                throw BizException::make('AB_SERIAL', 'Serial wajib untuk temuan cutting.');
            }
            $abnormal = (int) DB::table('tr_ab_cut_det')->where('main_id', $data['main_id'])->where('serial_id', $data['serial_id'])->sum('qty');
            $decided = (int) DB::table('tr_ng_cut')->where('main_id', $data['main_id'])->where('serial_id', $data['serial_id'])->sum('qty')
                + (int) DB::table('tr_repair_cut')->where('main_id', $data['main_id'])->where('serial_id', $data['serial_id'])->sum('qty');
            $this->assertFits($abnormal, $decided, $ng + $rp);

            DB::transaction(function () use ($data, $ng, $rp, $user) {
                if ($ng > 0) {
                    DB::table('tr_ng_cut')->insert([
                        'main_id' => $data['main_id'], 'user_id' => $user,
                        'serial_id' => $data['serial_id'], 'qty' => $ng,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                if ($rp > 0) {
                    DB::table('tr_repair_cut')->insert([
                        'main_id' => $data['main_id'], 'user_id' => $user,
                        'serial_id' => $data['serial_id'], 'qty' => $rp,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
        } else {
            $ab = DB::table('tr_ab_pro')->where('id', $data['main_id'])->first();
            if (! $ab) {
                throw BizException::make('AB_404', 'Temuan abnormal tidak ditemukan.');
            }
            $decided = (int) DB::table('tr_ng_pro')->where('main_id', $ab->id)->sum('qty')
                + (int) DB::table('tr_repair_pro')->where('main_id', $ab->id)->sum('qty');
            $this->assertFits((int) $ab->qty, $decided, $ng + $rp);

            DB::transaction(function () use ($ab, $ng, $rp, $user) {
                if ($ng > 0) {
                    DB::table('tr_ng_pro')->insert([
                        'main_id' => $ab->id, 'user_id' => $user, 'qty' => $ng,
                        'created_at' => now(), 'update_at' => now(),
                    ]);
                }
                if ($rp > 0) {
                    DB::table('tr_repair_pro')->insert([
                        'main_id' => $ab->id, 'user_id' => $user,
                        'pallet_code' => $ab->pallet_code, 'qty' => $rp,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            });
        }

        AuditLogger::record($request, "Decide abnormal {$data['type']}#{$data['main_id']}: NG {$ng} / repair {$rp}");

        return ApiResponse::item(['decided_ng' => $ng, 'decided_repair' => $rp]);
    }

    private function assertFits(int $abnormal, int $decided, int $adding): void
    {
        $left = $abnormal - $decided;
        if ($adding > $left) {
            throw BizException::make('AB_OVER', "Total keputusan melebihi qty abnormal (sisa {$left} dari {$abnormal}).");
        }
    }
}
