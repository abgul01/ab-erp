<?php

namespace App\Http\Controllers\Api\Wms;

use App\Http\Controllers\Controller;
use App\Models\prc_gr_serial;
use App\Models\prd_wo_serial_rm;
use App\Models\wh_inc_detail;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

/**
 * Putaway: scan serial → assign rack via wh_inc_detail.
 * Booking: reserve serial for WO via prd_wo_serial_rm.
 *
 * LLD §5.3b
 */
class PutawayController extends Controller
{
    /**
     * Serials that passed inspection and are waiting for a rack, plus the ones
     * already placed — the terminal needs both to confirm a scan and to show
     * where a bar currently sits.
     */
    public function serials(Request $request)
    {
        $q = wh_inc_detail::query()
            ->join('prc_gr_serial as s', 's.id', '=', 'wh_inc_detail.serial_id')
            ->leftJoin('m_item as i', 'i.id', '=', 'wh_inc_detail.item_id')
            ->leftJoin('m_rack as r', 'r.id', '=', 'wh_inc_detail.rack_id')
            ->where('s.status', 'OK');

        if ($search = trim((string) $request->query('q'))) {
            $q->where('s.serial_id', 'like', "%{$search}%");
        }
        if ($request->boolean('unplaced')) {
            $q->whereNull('wh_inc_detail.rack_id');
        }

        return ApiResponse::collection(
            $q->orderBy('s.serial_id')->limit(300)->get([
                'wh_inc_detail.id as inc_detail_id',
                's.id as serial_db_id',
                's.serial_id',
                's.length',
                's.weight',
                'i.code as item_code',
                'i.part_name as item_name',
                'wh_inc_detail.rack_id',
                'r.location as rack_code',
                'r.rem_rack',
            ])
        );
    }

    public function putaway(Request $request)
    {
        $data = $request->validate([
            'serial_id' => ['required', 'integer', 'exists:prc_gr_serial,id'],
            'rack_id' => ['required', 'integer', 'exists:m_rack,id'],
        ]);

        $inc = wh_inc_detail::where('serial_id', $data['serial_id'])->first();
        if (! $inc) {
            return ApiResponse::notFound('Serial tidak ditemukan di transaksi penerimaan.');
        }

        $inc->update(['rack_id' => $data['rack_id']]);
        AuditLogger::record($request, "Putaway serial #{$data['serial_id']} → rack #{$data['rack_id']}");

        return ApiResponse::item($inc);
    }

    public function book(Request $request)
    {
        $data = $request->validate([
            'serial_id' => ['required', 'integer', 'exists:prc_gr_serial,id'],
            'wo_detail_rm_id' => ['required', 'integer', 'exists:prd_wo_detail_rm,id'],
            'length_book' => ['nullable', 'integer', 'min:1'],
            'qty' => ['nullable', 'integer', 'min:1'],
        ]);

        $serial = prc_gr_serial::findOrFail($data['serial_id']);

        $exists = prd_wo_serial_rm::where('serial_id', $data['serial_id'])->exists();
        if ($exists) {
            return ApiResponse::conflict("Serial #{$serial->id} sudah di-booking ke WO lain.");
        }

        $booked = prd_wo_serial_rm::create([
            'detail_id' => $data['wo_detail_rm_id'],
            'serial_id' => $data['serial_id'],
            'length_book' => $data['length_book'] ?? 0,
            'qty_serial' => $data['qty'] ?? 1,
        ]);

        AuditLogger::record($request, "Book serial #{$data['serial_id']} → WO detail #{$data['wo_detail_rm_id']}");

        return ApiResponse::item($booked, 201);
    }

    public function unbook(Request $request, int $id)
    {
        $serial = prd_wo_serial_rm::findOrFail($id);
        $serial->delete();
        AuditLogger::record($request, "Unbook serial WO RM #{$id}");

        return ApiResponse::empty();
    }
}
