<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Api\CrudController;
use App\Models\m_defective;
use Illuminate\Http\Request;

/** Master Defective — defect codes used by process inspection and NG handling. */
class DefectiveController extends CrudController
{
    protected function model(): string
    {
        return m_defective::class;
    }

    protected function label(): string
    {
        return 'Kode Defect';
    }

    protected function searchable(): array
    {
        return ['code', 'name'];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:30'],
        ];
    }
}
