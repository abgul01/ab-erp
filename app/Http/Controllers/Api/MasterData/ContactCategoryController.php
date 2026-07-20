<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Api\CrudController;
use App\Models\m_cont_categ;
use Illuminate\Http\Request;

class ContactCategoryController extends CrudController
{
    protected function model(): string
    {
        return m_cont_categ::class;
    }

    protected function label(): string
    {
        return 'Contact Category';
    }

    protected function searchable(): array
    {
        return ['name'];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
        ];
    }
}
