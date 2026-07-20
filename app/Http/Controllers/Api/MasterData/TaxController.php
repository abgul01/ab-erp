<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Api\CrudController;
use App\Models\m_tax;
use Illuminate\Http\Request;

class TaxController extends CrudController
{
    protected function model(): string
    {
        return m_tax::class;
    }

    protected function label(): string
    {
        return 'Tax Code';
    }

    protected function searchable(): array
    {
        return ['code', 'name'];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
            'rate_pct' => ['required', 'numeric', 'between:0,100'],
            'dpp_factor' => ['required', 'numeric', 'between:0,1'],
            'is_luxury' => ['nullable', 'boolean'],
            'effective_from' => ['nullable', 'date'],
        ];
    }

    protected function mutate(array $data, Request $request): array
    {
        $data['is_luxury'] = (int) ($data['is_luxury'] ?? 0);

        return $data;
    }
}
