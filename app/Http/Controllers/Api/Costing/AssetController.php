<?php

namespace App\Http\Controllers\Api\Costing;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\ast_depre;
use App\Models\ast_main;
use App\Models\m_asset_categ;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\JournalEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Fixed assets (ast_main) + straight-line depreciation. "Hitung Depresiasi"
 * posts one month of depreciation (acq_cost / useful_life) for every active
 * asset that is in service and not yet fully depreciated in the period.
 */
class AssetController extends Controller
{
    public function index(Request $request)
    {
        $q = ast_main::with('categ')->withSum('depre as depre_total', 'amount');
        if ($s = trim((string) $request->query('q', ''))) {
            $q->where(fn ($w) => $w->where('code', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"));
        }
        $rows = $q->orderByDesc('id')->paginate(min(max((int) $request->query('per_page', 20), 1), 200));
        $rows->getCollection()->transform(function ($a) {
            $a->depre_total = (float) ($a->depre_total ?? 0);
            $a->book_value = round((float) $a->acq_cost - $a->depre_total, 2);
            $a->monthly = (int) $a->useful_life > 0 ? round((float) $a->acq_cost / (int) $a->useful_life, 2) : 0;

            return $a;
        });

        return ApiResponse::paginated($rows);
    }

    public function show(int $id)
    {
        return ApiResponse::item(ast_main::with(['categ', 'depre'])->findOrFail($id));
    }

    public function store(Request $request)
    {
        $row = ast_main::create($this->validateRow($request));
        AuditLogger::record($request, "Create asset {$row->code}");

        return ApiResponse::item($row->load('categ'), 201);
    }

    public function update(Request $request, int $id)
    {
        $row = ast_main::findOrFail($id);
        $row->update($this->validateRow($request, $id));
        AuditLogger::record($request, "Update asset {$row->code}");

        return ApiResponse::item($row->load('categ'));
    }

    public function destroy(Request $request, int $id)
    {
        $row = ast_main::findOrFail($id);
        DB::transaction(function () use ($row, $request) {
            ast_depre::where('ast_id', $row->id)->delete();
            $row->delete();
            AuditLogger::record($request, "Delete asset {$row->code}");
        });

        return ApiResponse::item(['message' => 'Aset dihapus.']);
    }

    /** Post one month of straight-line depreciation for the period. */
    /**
     * Retire an asset: dispose, transfer out, or write off.
     *
     * Depreciation already booked stays where it is; the gain or loss is the
     * proceeds against what was left on the books, which is what the journal
     * records. The asset is marked rather than deleted — its history is part of
     * the fixed-asset register.
     */
    public function retire(Request $request, int $id)
    {
        $data = $request->validate([
            'action' => ['required', 'in:DISPOSED,TRANSFERRED,RETIRED'],
            'date' => ['required', 'date'],
            'proceeds' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:300'],
        ]);

        $asset = ast_main::withSum('depre as depre_total', 'amount')->findOrFail($id);

        if (in_array($asset->status, ['DISPOSED', 'TRANSFERRED', 'RETIRED'], true)) {
            throw BizException::make('ASSET_RETIRED', "Aset {$asset->code} sudah berstatus {$asset->status}.");
        }

        $bookValue = round((float) $asset->acq_cost - (float) ($asset->depre_total ?? 0), 2);
        $proceeds = (float) ($data['proceeds'] ?? 0);
        $gainLoss = round($proceeds - $bookValue, 2);

        DB::transaction(function () use ($asset, $data, $proceeds, $gainLoss, $request) {
            $asset->update(['status' => $data['action']]);

            $lines = [
                ['coa' => '1590', 'debit' => round((float) ($asset->depre_total ?? 0), 2), 'memo' => 'Akum. penyusutan dilepas'],
                ['coa' => '1500', 'credit' => round((float) $asset->acq_cost, 2), 'memo' => "Aset {$asset->code} dilepas"],
            ];
            if ($proceeds > 0) {
                $lines[] = ['coa' => '1100', 'debit' => $proceeds, 'memo' => 'Hasil pelepasan aset'];
            }
            // Balancing side: a shortfall is a loss, a surplus a gain.
            $lines[] = $gainLoss < 0
                ? ['coa' => '6910', 'debit' => abs($gainLoss), 'memo' => 'Rugi pelepasan aset']
                : ['coa' => '4900', 'credit' => $gainLoss, 'memo' => 'Laba pelepasan aset'];

            JournalEngine::post(
                'ASSET_RETIRE', $asset->id, $data['date'], 'ADJ', $lines,
                "{$data['action']} aset {$asset->code}: {$data['reason']}",
                (int) $request->user()->id,
            );
        });

        AuditLogger::record($request, "{$data['action']} aset {$asset->code}: {$data['reason']}", $asset->code);

        return ApiResponse::item([
            'code' => $asset->code,
            'status' => $data['action'],
            'book_value' => $bookValue,
            'proceeds' => $proceeds,
            'gain_loss' => $gainLoss,
        ]);
    }

    public function depreciate(Request $request)
    {
        $data = $request->validate([
            'period' => ['required_without:periods', 'regex:/^\d{6}$/'],
            'periods' => ['required_without:period', 'array', 'min:1', 'max:12'],
            'periods.*' => ['regex:/^\d{6}$/'],
        ]);

        // Catching up several months at once is the normal case after a
        // migration or a late close, so the months are processed in order.
        $periods = collect($data['periods'] ?? [$data['period']])->unique()->sort()->values()->all();
        $assets = ast_main::where('status', 'ACTIVE')->get();

        $byPeriod = DB::transaction(function () use ($assets, $periods, $request) {
            $result = [];

            foreach ($periods as $period) {
                $periodEnd = Carbon::createFromFormat('Ym', $period)->endOfMonth();
                $count = 0;

                foreach ($assets as $a) {
                    $life = (int) $a->useful_life;
                    if ($life <= 0 || Carbon::parse($a->acq_date)->gt($periodEnd)) {
                        continue;   // not yet in service
                    }
                    // Months already booked, counted fresh each period so a
                    // multi-month catch-up stops at the end of the asset's life.
                    $already = (int) ast_depre::where('ast_id', $a->id)->count();
                    if ($already >= $life) {
                        continue;   // fully depreciated
                    }
                    $monthly = round((float) $a->acq_cost / $life, 2);
                    ast_depre::updateOrCreate(['ast_id' => $a->id, 'period' => $period], ['amount' => $monthly]);
                    $count++;
                }

                $result[$period] = $count;
            }

            AuditLogger::record($request, 'Hitung depresiasi '.implode(', ', $periods).': '.array_sum($result).' baris');

            return $result;
        });

        return ApiResponse::item([
            'periods' => $periods,
            'period' => $periods[0],
            'posted' => array_sum($byPeriod),
            'by_period' => $byPeriod,
        ]);
    }

    private function validateRow(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('ast_main', 'code')->ignore($id)],
            'categ_id' => ['required', 'integer', 'exists:m_asset_categ,id'],
            'name' => ['required', 'string', 'max:150'],
            'acq_date' => ['required', 'date'],
            'acq_cost' => ['required', 'numeric', 'min:0'],
            'useful_life' => ['nullable', 'integer', 'min:1'],
            'machine_id' => ['nullable', 'integer', 'exists:m_machine,id'],
            'status' => ['nullable', 'in:ACTIVE,DISPOSED,TRANSFERRED'],
        ]);
        // default useful life from the category when not given
        if (empty($data['useful_life'])) {
            $data['useful_life'] = (int) m_asset_categ::whereKey($data['categ_id'])->value('useful_life');
        }
        $data['status'] = $data['status'] ?? 'ACTIVE';

        return $data;
    }
}
