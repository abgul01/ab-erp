<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Api\CrudController;
use App\Models\m_process;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Manufacturing processes (routing steps). is_cutting is inferred from name in seed;
 * kept as a plain master here (WOS routing lives in Engineering).
 */
class ProcessController extends CrudController
{
    protected function model(): string
    {
        return m_process::class;
    }

    protected function label(): string
    {
        return 'Process';
    }

    protected function searchable(): array
    {
        return ['code', 'name_p', 'descript'];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('m_process', 'code')->ignore($id)],
            'name_p' => ['required', 'string', 'max:50'],
            'descript' => ['nullable', 'string', 'max:100'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    protected function mutate(array $data, Request $request): array
    {
        $data['active'] = (int) ($data['active'] ?? 1);

        return $data;
    }
}
