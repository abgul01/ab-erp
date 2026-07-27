<?php

namespace App\Http\Controllers\Api\Engineering;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\m_route_time;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cycle-time master (item × process × machine). One row = "producing one piece
 * of this item at this process on this machine takes cycle_sec seconds", with a
 * priority so the MPS scheduler knows which machine to prefer. The slowest
 * (bottleneck) process determines an item's daily throughput.
 */
class RouteTimeController extends Controller
{
    private array $with = ['item', 'process', 'machine'];

    public function index(Request $request)
    {
        $query = m_route_time::with($this->with);
        if ($q = trim((string) $request->query('q', ''))) {
            $query->whereHas('item', fn ($s) => $s->where('code', 'like', "%{$q}%")->orWhere('part_name', 'like', "%{$q}%"));
        }
        if ($item = $request->query('item_id')) {
            $query->where('item_id', $item);
        }
        $query->orderBy('item_id')->orderBy('priority')->orderBy('proc_id');

        return ApiResponse::paginated($query->paginate(min(max((int) $request->query('per_page', 20), 1), 500)));
    }

    public function show(int $id)
    {
        return ApiResponse::item(m_route_time::with($this->with)->findOrFail($id));
    }

    /** Distinct processes that appear in the item's routing templates. */
    public function itemProcs(int $itemId)
    {
        return ApiResponse::collection($this->itemProcRows($itemId));
    }

    public function store(Request $request)
    {
        $data = $this->validateRow($request);
        $row = m_route_time::create($data);
        AuditLogger::record($request, "Create RouteTime item#{$row->item_id} proc#{$row->proc_id}");

        return ApiResponse::item($row->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $row = m_route_time::findOrFail($id);
        $row->update($this->validateRow($request));
        AuditLogger::record($request, "Update RouteTime #{$id}");

        return ApiResponse::item($row->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $row = m_route_time::findOrFail($id);
        $row->delete();
        AuditLogger::record($request, "Delete RouteTime #{$id}");

        return ApiResponse::item(['message' => 'Cycle time berhasil dihapus.']);
    }

    private function validateRow(Request $request): array
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer', 'exists:m_item,id'],
            'proc_id' => ['required', 'integer', 'exists:m_process,id'],
            'machine_id' => ['nullable', 'integer', 'exists:m_machine,id'],
            'cycle_sec' => ['required', 'numeric', 'min:0'],
            'setup_min' => ['nullable', 'numeric', 'min:0'],
            'priority' => ['nullable', 'integer', 'min:1'],
            'active' => ['nullable', 'boolean'],
        ]);
        // the process must belong to one of the item's routing templates
        $allowed = collect($this->itemProcRows((int) $data['item_id']))->pluck('id')->all();
        if ($allowed && ! in_array((int) $data['proc_id'], $allowed, true)) {
            throw BizException::make('RT_PROC', 'Proses ini bukan langkah routing item tersebut. Atur routing di Item Master / Master Routing.');
        }

        $data['setup_min'] = $data['setup_min'] ?? 0;
        $data['priority'] = $data['priority'] ?? 1;
        $data['active'] = (int) ($data['active'] ?? 1);
        $data['machine_id'] = $data['machine_id'] ?? null;

        return $data;
    }

    /** @return array<int, array{id:int, code:string, name_p:string}> */
    private function itemProcRows(int $itemId): array
    {
        return DB::table('m_bom_pro as b')
            ->join('m_process_main_det as d', 'd.main_id', '=', 'b.process_main_id')
            ->join('m_process as p', 'p.id', '=', 'd.proc_id')
            ->where('b.item_id', $itemId)
            ->distinct()->orderBy('p.code')
            ->get(['p.id', 'p.code', 'p.name_p'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'code' => $r->code, 'name_p' => $r->name_p])
            ->all();
    }
}
