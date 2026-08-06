<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\sls_forecast;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\ItemLifecycle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Customer Forecast (Fase 5 Order Mgmt). Planned demand per customer/item/period
 * with a version (N-3/N-2/N-1/FINAL). Feeds MPP generation.
 */
class ForecastController extends Controller
{
    public function index(Request $request)
    {
        $query = sls_forecast::with(['cus', 'item']);
        if ($q = trim((string) $request->query('q', ''))) {
            $query->whereHas('item', fn ($s) => $s->where('code', 'like', "%{$q}%")->orWhere('part_name', 'like', "%{$q}%"));
        }
        if ($p = $request->query('period')) {
            $query->where('period', $p);
        }
        if ($cus = $request->query('cus_id')) {
            $query->where('cus_id', $cus);
        }
        $query->orderByDesc('period')->orderBy('item_id');

        return ApiResponse::paginated($query->paginate(min(max((int) $request->query('per_page', 20), 1), 200)));
    }

    public function show(int $id)
    {
        return ApiResponse::item(sls_forecast::with(['cus', 'item'])->findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $this->validateFc($request);
        $fc = sls_forecast::create($data);
        AuditLogger::record($request, "Create Forecast {$fc->period} cus#{$fc->cus_id} item#{$fc->item_id}");

        return ApiResponse::item($fc->load(['cus', 'item']), 201);
    }

    public function update(Request $request, int $id)
    {
        $fc = sls_forecast::findOrFail($id);
        $data = $this->validateFc($request, $id);
        $fc->update($data);
        AuditLogger::record($request, "Update Forecast #{$id}");

        return ApiResponse::item($fc->load(['cus', 'item']));
    }

    public function destroy(Request $request, int $id)
    {
        $fc = sls_forecast::findOrFail($id);
        $fc->delete();
        AuditLogger::record($request, "Delete Forecast #{$id}");

        return ApiResponse::item(['message' => 'Forecast berhasil dihapus.']);
    }

    private function validateFc(Request $request, ?int $id = null): array
    {
        $data = $this->rulesFor($request, $id);

        /*
         * Forecast adalah salah satu dari tiga sumber permintaan yang dibaca
         * MRP. Part yang masih uji coba tidak boleh ada di sana — materialnya
         * akan ikut dibeli untuk barang yang belum tentu jadi.
         */
        ItemLifecycle::assertMassPro($data['item_id'], 'forecast');

        return $data;
    }

    private function rulesFor(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'cus_id' => ['required', 'integer', 'exists:m_contacts,id'],
            'item_id' => ['required', 'integer', 'exists:m_item,id'],
            'version' => ['required', 'string', 'max:10'],
            'qty' => ['required', 'integer', 'min:0'],
            'period' => ['required', 'string', 'regex:/^\d{6}$/',
                Rule::unique('sls_forecast', 'period')
                    ->where(fn ($q) => $q->where('cus_id', $request->input('cus_id'))->where('item_id', $request->input('item_id'))->where('version', $request->input('version')))
                    ->ignore($id)],
        ]);
    }
}
