<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Api\CrudController;
use App\Models\m_production_line;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Master lintasan produksi. */
class ProductionLineController extends CrudController
{
    protected function model(): string
    {
        return m_production_line::class;
    }

    protected function label(): string
    {
        return 'Production Line';
    }

    protected function searchable(): array
    {
        return ['code', 'name', 'descrip'];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('m_production_line', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:80'],
            'descrip' => ['nullable', 'string', 'max:200'],
            // Jam kerja lintasan per hari — dasar penilaian kapasitas gabungan.
            'daily_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
