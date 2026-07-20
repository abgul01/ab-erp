<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Api\CrudController;
use App\Models\m_machine;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MachineController extends CrudController
{
    protected function model(): string
    {
        return m_machine::class;
    }

    protected function label(): string
    {
        return 'Machine';
    }

    protected function searchable(): array
    {
        return ['code', 'name', 'model', 'categ', 'serial'];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('m_machine', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:50'],
            'model' => ['nullable', 'string', 'max:50'],
            'categ' => ['nullable', 'string', 'max:50'],
            'maker_id' => ['nullable', 'integer', 'exists:m_maker_m,id'],
            'min_d' => ['nullable', 'numeric'],
            'max_d' => ['nullable', 'numeric'],
            'serial' => ['nullable', 'string', 'max:50'],
            'kwh' => ['nullable', 'numeric'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    protected function mutate(array $data, Request $request): array
    {
        $data['active'] = (int) ($data['active'] ?? 1);

        return $data;
    }
}
