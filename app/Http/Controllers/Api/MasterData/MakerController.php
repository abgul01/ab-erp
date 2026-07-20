<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Api\CrudController;
use App\Models\m_maker_m;
use Illuminate\Http\Request;

class MakerController extends CrudController
{
    protected function model(): string
    {
        return m_maker_m::class;
    }

    protected function label(): string
    {
        return 'Maker';
    }

    protected function searchable(): array
    {
        return ['name', 'address'];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:150'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    protected function mutate(array $data, Request $request): array
    {
        $data['active'] = (int) ($data['active'] ?? 1);

        return $data;
    }
}
