<?php

namespace App\Http\Controllers\Api\Wms;

use App\Http\Controllers\Api\CrudController;
use App\Models\m_rack;
use Illuminate\Http\Request;

/**
 * Master Rak gudang RM (m_rack). rem_rack menandai rak remnant (material sisa).
 */
class RackController extends CrudController
{
    protected function model(): string
    {
        return m_rack::class;
    }

    protected function label(): string
    {
        return 'Rak';
    }

    protected function searchable(): array
    {
        return ['location', 'descriptions'];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'location' => ['required', 'string', 'max:50'],
            'descriptions' => ['nullable', 'string', 'max:150'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'area' => ['nullable', 'numeric', 'min:0'],
            'depth' => ['nullable', 'string', 'max:50'],
            'rem_rack' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    protected function mutate(array $data, Request $request): array
    {
        // height/width/area are NOT NULL doubles on the legacy table.
        foreach (['height', 'width', 'area'] as $col) {
            $data[$col] = (float) ($data[$col] ?? 0);
        }
        $data['rem_rack'] = (int) ($data['rem_rack'] ?? 0);
        $data['active'] = (int) ($data['active'] ?? 1);

        return $data;
    }
}
