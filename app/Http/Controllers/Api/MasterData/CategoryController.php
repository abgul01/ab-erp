<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Api\CrudController;
use App\Models\m_i_category;
use Illuminate\Http\Request;

class CategoryController extends CrudController
{
    protected function model(): string
    {
        return m_i_category::class;
    }

    protected function label(): string
    {
        return 'Item Category';
    }

    protected function searchable(): array
    {
        return ['name_c'];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'name_c' => ['required', 'string', 'max:50'],
        ];
    }
}
