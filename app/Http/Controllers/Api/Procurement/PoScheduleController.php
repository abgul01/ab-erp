<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Controller;
use App\Models\prc_po_schedule;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PoScheduleController extends Controller
{
    public function index(Request $request)
    {
        $rows = prc_po_schedule::with('detail')
            ->orderByDesc('plan_date')
            ->paginate(min(max((int) $request->query('per_page', 20), 1), 200));

        return ApiResponse::paginated($rows);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'po_detail_id' => ['required', 'integer', 'exists:prc_po_detail,id'],
            'plan_date' => ['required', 'date'],
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        $schedule = prc_po_schedule::create($data);
        AuditLogger::record($request, "PO Schedule #{$schedule->id} created for detail #{$data['po_detail_id']}");

        return ApiResponse::item($schedule, 201);
    }

    public function confirm(Request $request, int $id)
    {
        $schedule = prc_po_schedule::findOrFail($id);
        $schedule->update(['confirmed_at' => now()]);
        AuditLogger::record($request, "PO Schedule #{$id} confirmed");

        return ApiResponse::item($schedule);
    }

    public function destroy(Request $request, int $id)
    {
        prc_po_schedule::findOrFail($id)->delete();
        AuditLogger::record($request, "PO Schedule #{$id} deleted");

        return ApiResponse::empty();
    }

    /**
     * Delivery-schedule template for a purchase order (PRD §4.7).
     *
     * Filled with the order's own outstanding lines rather than an invented
     * example row: a buyer sending a supplier a blank sheet with "1,2026-08-15,
     * 100" in it gets back a spreadsheet keyed to a line id that means nothing
     * to either of them. With the real lines in place the supplier only fills
     * in dates and quantities.
     */
    public function template(Request $request)
    {
        $request->validate(['po_id' => ['nullable', 'integer', 'exists:prc_po_main,id']]);
        $poId = $request->query('po_id');

        $lines = DB::table('prc_po_detail as d')
            ->join('prc_po_main as m', 'm.id', '=', 'd.main_id')
            ->join('m_item as i', 'i.id', '=', 'd.item_id')
            ->when($poId, fn ($q) => $q->where('d.main_id', $poId))
            ->when(! $poId, fn ($q) => $q->whereIn('m.status', ['OPEN', 'INPROGRESS']))
            ->selectRaw('d.id, m.code as po_code, i.code as item_code, i.part_name,
                GREATEST(d.qty - d.qty_received, 0) as outstanding')
            ->orderBy('m.code')->orderBy('d.id')
            ->limit(500)->get();

        $rows = ['po_detail_id,po_code,item_code,part_name,plan_date,qty'];
        foreach ($lines as $l) {
            // Quantity is pre-filled with what is still owed; the supplier
            // splits it across dates by adding rows.
            $rows[] = sprintf(
                '%d,%s,%s,"%s",,%d',
                $l->id, $l->po_code, $l->item_code, str_replace('"', "'", $l->part_name), $l->outstanding
            );
        }

        $name = 'jadwal-kirim-'.($poId ? "po-{$poId}" : 'semua-po').'.csv';

        return response(implode("\n", $rows), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$name}\"",
        ]);
    }

    /**
     * Take the supplier's filled-in schedule back.
     *
     * Accepts either the CSV that went out or a plain list of rows, because the
     * screen posts one and an integration posts the other. Rows without a date
     * are skipped rather than rejected — a supplier who scheduled half the lines
     * has still told us something useful, and refusing the file loses all of it.
     */
    public function import(Request $request)
    {
        $rows = $request->hasFile('file')
            ? $this->parseCsv($request->file('file')->get())
            : $request->input('rows', []);

        $data = validator(['rows' => $rows], [
            'rows' => ['required', 'array', 'max:500'],
            'rows.*.po_detail_id' => ['required', 'integer', 'exists:prc_po_detail,id'],
            'rows.*.plan_date' => ['required', 'date'],
            'rows.*.qty' => ['required', 'integer', 'min:1'],
        ])->validate();

        $imported = 0;
        foreach ($data['rows'] as $row) {
            prc_po_schedule::updateOrCreate(
                ['po_detail_id' => $row['po_detail_id'], 'plan_date' => $row['plan_date']],
                ['qty' => $row['qty']],
            );
            $imported++;
        }

        AuditLogger::record($request, "Import jadwal kirim supplier: {$imported} baris");

        return ApiResponse::ok([
            'imported' => $imported,
            'skipped' => max(0, count($rows) - $imported),
            'message' => "{$imported} baris jadwal tersimpan.",
        ]);
    }

    /**
     * Read the template back by column name, so a supplier who reorders or adds
     * columns in their spreadsheet does not silently corrupt the import.
     *
     * @return array<int, array{po_detail_id:int, plan_date:string, qty:int}>
     */
    private function parseCsv(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        $header = array_map(fn ($h) => strtolower(trim($h, " \t\"")), str_getcsv(array_shift($lines) ?? ''));

        $out = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $row = array_combine(
                $header,
                array_pad(str_getcsv($line), count($header), null)
            );

            // A line the supplier left undated is one they have not committed to.
            if (empty($row['plan_date']) || empty($row['po_detail_id']) || (int) ($row['qty'] ?? 0) < 1) {
                continue;
            }

            $out[] = [
                'po_detail_id' => (int) $row['po_detail_id'],
                'plan_date' => trim($row['plan_date']),
                'qty' => (int) $row['qty'],
            ];
        }

        return $out;
    }
}
