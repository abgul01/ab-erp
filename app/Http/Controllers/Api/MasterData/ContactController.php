<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Api\CrudController;
use App\Models\m_contacts;
use Illuminate\Http\Request;

/**
 * Business partners (customers / vendors / subcont) — m_contacts.
 * category_id maps to m_cont_categ.
 */
class ContactController extends CrudController
{
    protected function model(): string
    {
        return m_contacts::class;
    }

    protected function label(): string
    {
        return 'Contact';
    }

    protected function searchable(): array
    {
        return ['u_code', 'name', 'company_n', 'nick_n', 'email', 'phone'];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'u_code' => ['nullable', 'string', 'max:10'],
            'initial' => ['nullable', 'string', 'max:50'],
            'nick_n' => ['nullable', 'string', 'max:50'],
            'company_n' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:400'],
            'name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:50'],
            'category_id' => ['nullable', 'integer', 'exists:m_cont_categ,id'],
            'identity' => ['nullable', 'string', 'max:10'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    protected function mutate(array $data, Request $request): array
    {
        $data['active'] = (int) ($data['active'] ?? 1);

        return $data;
    }
}
