<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Api\CrudController;
use App\Models\m_inspection_param;
use Illuminate\Http\Request;

/** Master Inspection — the characteristics incoming goods are measured on. */
class InspectionParamController extends CrudController
{
    protected function model(): string
    {
        return m_inspection_param::class;
    }

    protected function label(): string
    {
        return 'Parameter Inspeksi';
    }

    protected function searchable(): array
    {
        return ['code', 'name'];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'unique:m_inspection_param,code'.($id ? ",{$id}" : '')],
            'name' => ['required', 'string', 'max:100'],
            'uom' => ['nullable', 'string', 'max:20'],
            'method' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
