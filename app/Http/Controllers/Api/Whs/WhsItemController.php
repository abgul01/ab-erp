<?php

namespace App\Http\Controllers\Api\Whs;

use App\Http\Controllers\Api\CrudController;
use App\Models\m_whs_item;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Master barang gudang WHS — sparepart, barang habis pakai, dan alat kerja.
 *
 * Terpisah dari `m_item` karena barang untuk menjalankan pabrik bukan barang
 * yang dibuat pabrik: tidak punya BOM, tidak punya routing, tidak masuk MRP,
 * dan dibeli lewat jalurnya sendiri.
 */
class WhsItemController extends CrudController
{
    protected function model(): string
    {
        return m_whs_item::class;
    }

    protected function label(): string
    {
        return 'Master Barang WHS';
    }

    protected function with(): array
    {
        return ['uom'];
    }

    protected function searchable(): array
    {
        return ['code', 'name', 'brand', 'spec', 'categ'];
    }

    /**
     * Daftar barang, boleh disaring per jenis.
     *
     * Layar gudang hampir selalu dibuka untuk satu jenis saja — orang yang
     * mencari mata bor tidak sedang melihat-lihat sarung tangan.
     */
    public function index(Request $request)
    {
        $query = m_whs_item::with($this->with())
            ->when($request->query('whs_type'), fn ($q, $t) => $q->where('whs_type', $t))
            ->when($request->boolean('active_only'), fn ($q) => $q->where('active', 1))
            ->when(trim((string) $request->query('q', '')), function ($q, $s) {
                $q->where(function ($sub) use ($s) {
                    foreach ($this->searchable() as $col) {
                        $sub->orWhere($col, 'like', "%{$s}%");
                    }
                });
            })
            ->orderBy('code');

        $perPage = min(max((int) $request->query('per_page', 20), 1), 500);

        return ApiResponse::paginated($query->paginate($perPage));
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'code' => ['required', 'string', 'max:40', Rule::unique('m_whs_item', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:100'],
            'whs_type' => ['required', Rule::in(m_whs_item::TYPES)],
            'categ' => ['nullable', 'string', 'max:40'],
            'uom_id' => ['nullable', 'integer', 'exists:m_uom,id'],
            'brand' => ['nullable', 'string', 'max:40'],
            'spec' => ['nullable', 'string', 'max:150'],
            'rack_loc' => ['nullable', 'string', 'max:30'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'max_stock' => ['nullable', 'integer', 'min:0'],
            'standard_cost' => ['nullable', 'numeric', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
