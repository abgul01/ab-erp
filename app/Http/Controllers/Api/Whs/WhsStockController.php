<?php

namespace App\Http\Controllers\Api\Whs;

use App\Http\Controllers\Controller;
use App\Models\whs_tool_unit;
use App\Support\ApiResponse;
use App\Support\WhsStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Stok gudang WHS: isi rak, nilainya, dan alat yang sedang di luar.
 *
 * Semua angkanya dihitung dari dokumen yang sudah di-post, bukan dari kolom
 * saldo — jadi laporan ini tidak bisa berbeda dengan transaksinya.
 */
class WhsStockController extends Controller
{
    public function __construct(private WhsStockService $svc) {}

    public function index(Request $request)
    {
        $request->validate([
            'whs_type' => ['nullable', 'in:PART,CONSUMABLE,TOOL'],
        ]);

        return ApiResponse::item([
            'summary' => $this->svc->summary(),
            'items' => $this->svc->stockList(
                $request->query('whs_type'),
                $request->boolean('below_min')
            ),
        ]);
    }

    /** Alat yang sedang dipinjam, beserta pemegang dan lama pinjamnya. */
    public function onLoan()
    {
        return ApiResponse::collection($this->svc->onLoan());
    }

    /**
     * Serial penerimaan: batch mana yang masih ada isinya, dari kiriman kapan.
     *
     * Dipakai layar pengeluaran untuk memilih batch, dan layar downtime untuk
     * menunjuk sparepart yang benar-benar pernah diterima.
     */
    public function serials(Request $request)
    {
        $request->validate([
            'item_id' => ['nullable', 'integer', 'exists:m_whs_item,id'],
        ]);

        return ApiResponse::collection($this->svc->serialBalances(
            $request->query('item_id') ? (int) $request->query('item_id') : null,
            $request->boolean('available')
        ));
    }

    /**
     * Kode yang bisa dipakai layar downtime — batch sparepart maupun unit alat.
     */
    public function codes(Request $request)
    {
        return ApiResponse::collection($this->svc->usableCodes($request->query('q')));
    }

    /** Riwayat satu unit alat: sekarang di mana, dan pernah ke mana. */
    public function unit(int $id)
    {
        $unit = whs_tool_unit::with('item')->findOrFail($id);

        return ApiResponse::item([
            'unit' => $unit,
            'history' => DB::table('whs_ret_det as r')
                ->join('whs_ret_main as m', 'm.id', '=', 'r.main_id')
                ->where('r.tool_unit_id', $id)
                ->orderByDesc('m.date')
                ->get(['m.code', 'm.date', 'm.returner', 'r.condition', 'r.note']),
        ]);
    }
}
