<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Api\CrudController;
use App\Models\m_currency;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CurrencyController extends CrudController
{
    protected function model(): string
    {
        return m_currency::class;
    }

    protected function label(): string
    {
        return 'Currency';
    }

    protected function searchable(): array
    {
        return ['code', 'name'];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'code' => ['required', 'string', 'size:3', Rule::unique('m_currency', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:50'],
            'is_base' => ['nullable', 'boolean'],
        ];
    }

    protected function mutate(array $data, Request $request): array
    {
        $data['is_base'] = (int) ($data['is_base'] ?? 0);

        return $data;
    }
}
