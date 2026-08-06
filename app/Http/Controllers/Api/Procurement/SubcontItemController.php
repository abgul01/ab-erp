<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\m_subcont_item;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Master Subcont — which goods (item + optional process + price) each subcont
 * vendor can handle. The subcont PO picks items from this list per vendor.
 */
class SubcontItemController extends Controller
{
    private array $with = ['ven', 'item', 'process'];

    public function index(Request $request)
    {
        $q = m_subcont_item::with($this->with);
        if ($ven = $request->query('ven_id')) {
            $q->where('ven_id', $ven);
        }
        if ($s = trim((string) $request->query('q', ''))) {
            $q->whereHas('item', fn ($w) => $w->where('code', 'like', "%{$s}%")->orWhere('part_name', 'like', "%{$s}%"))
                ->orWhereHas('ven', fn ($w) => $w->where('company_n', 'like', "%{$s}%"));
        }

        return ApiResponse::paginated($q->orderByDesc('id')->paginate(min(max((int) $request->query('per_page', 20), 1), 500)));
    }

    /** Active master items for a vendor — the subcont PO line picker. */
    public function forVendor(int $venId)
    {
        $rows = m_subcont_item::with(['item', 'process'])->where('ven_id', $venId)->where('active', 1)->get();

        return ApiResponse::collection($rows->map(fn ($r) => [
            'item_id' => $r->item_id, 'item_code' => $r->item?->code, 'part_name' => $r->item?->part_name,
            'process_id' => $r->process_id, 'process_code' => $r->process?->code, 'price' => (float) $r->price,
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->validateRow($request);
        $this->assertUnique($data);
        $row = m_subcont_item::create($data);
        AuditLogger::record($request, "Create subcont master ven#{$data['ven_id']} item#{$data['item_id']}");

        return ApiResponse::item($row->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $row = m_subcont_item::findOrFail($id);
        $data = $this->validateRow($request);
        $this->assertUnique($data, $id);
        $row->update($data);
        AuditLogger::record($request, "Update subcont master #{$id}");

        return ApiResponse::item($row->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        m_subcont_item::findOrFail($id)->delete();
        AuditLogger::record($request, "Delete subcont master #{$id}");

        return ApiResponse::item(['message' => 'Master subcont dihapus.']);
    }

    private function validateRow(Request $request): array
    {
        $data = $request->validate([
            'ven_id' => ['required', 'integer', 'exists:m_contacts,id'],
            'item_id' => ['required', 'integer', 'exists:m_item,id'],
            'process_id' => ['nullable', 'integer', 'exists:m_process,id'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);
        $data['process_id'] = $data['process_id'] ?? null;
        $data['price'] = $data['price'] ?? 0;
        $data['active'] = (int) ($data['active'] ?? 1);

        return $data;
    }

    private function assertUnique(array $data, ?int $id = null): void
    {
        $exists = DB::table('m_subcont_item')
            ->where('ven_id', $data['ven_id'])->where('item_id', $data['item_id'])
            ->where(fn ($q) => $data['process_id'] === null ? $q->whereNull('process_id') : $q->where('process_id', $data['process_id']))
            ->when($id, fn ($q) => $q->where('id', '<>', $id))
            ->exists();
        if ($exists) {
            throw BizException::make('SUBM_DUP', 'Kombinasi vendor + item + proses ini sudah ada di master.');
        }
    }
}
