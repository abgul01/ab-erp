<?php

namespace App\Http\Controllers\Api\Costing;

use App\Http\Controllers\Controller;
use App\Models\m_asset_categ;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Asset categories: default useful life (months) + depreciation method. */
class AssetCategoryController extends Controller
{
    public function index(Request $request)
    {
        $q = m_asset_categ::query();
        if ($s = trim((string) $request->query('q', ''))) {
            $q->where(fn ($w) => $w->where('code', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"));
        }

        return ApiResponse::paginated($q->orderBy('code')->paginate(min(max((int) $request->query('per_page', 50), 1), 500)));
    }

    public function store(Request $request)
    {
        $row = m_asset_categ::create($this->validateRow($request));
        AuditLogger::record($request, "Create asset category {$row->code}");

        return ApiResponse::item($row, 201);
    }

    public function update(Request $request, int $id)
    {
        $row = m_asset_categ::findOrFail($id);
        $row->update($this->validateRow($request, $id));
        AuditLogger::record($request, "Update asset category {$row->code}");

        return ApiResponse::item($row);
    }

    public function destroy(Request $request, int $id)
    {
        m_asset_categ::findOrFail($id)->delete();
        AuditLogger::record($request, "Delete asset category #{$id}");

        return ApiResponse::item(['message' => 'Kategori aset dihapus.']);
    }

    private function validateRow(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('m_asset_categ', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:100'],
            'useful_life' => ['required', 'integer', 'min:1'],
            'depr_method' => ['nullable', 'in:STRAIGHT'],
        ]);
    }
}
