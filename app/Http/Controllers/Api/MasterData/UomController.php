<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Api\CrudController;
use App\Models\m_uom;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UomController extends CrudController
{
    protected function model(): string
    {
        return m_uom::class;
    }

    protected function label(): string
    {
        return 'UoM';
    }

    protected function searchable(): array
    {
        return ['code', 'name', 'uom_type'];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'code' => ['required', 'string', 'max:10', Rule::unique('m_uom', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:50'],
            'uom_type' => ['nullable', 'string', 'max:15'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    protected function mutate(array $data, Request $request): array
    {
        $data['active'] = (int) ($data['active'] ?? 1);

        return $data;
    }
}
