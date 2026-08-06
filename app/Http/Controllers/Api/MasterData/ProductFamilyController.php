<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Api\CrudController;
use App\Models\m_product_family;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Master keluarga produk. */
class ProductFamilyController extends CrudController
{
    protected function model(): string
    {
        return m_product_family::class;
    }

    protected function label(): string
    {
        return 'Product Family';
    }

    protected function searchable(): array
    {
        return ['code', 'name', 'descrip'];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('m_product_family', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:80'],
            'descrip' => ['nullable', 'string', 'max:200'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
