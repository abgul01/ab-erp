<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Http\Controllers\Api\CrudController;
use App\Models\m_supplier_item;
use App\Support\ApiResponse;
use App\Support\MrpService;
use Illuminate\Http\Request;

/**
 * Supplier Item & Price — who sells a material, at what price, on what terms.
 *
 * MRP reads the lowest-priority row to decide the order quantity and the date
 * the buyer has to act, so these numbers drive purchasing, not just reporting.
 *
 * PRD §4.7
 */
class SupplierItemController extends CrudController
{
    protected function model(): string
    {
        return m_supplier_item::class;
    }

    protected function label(): string
    {
        return 'Supplier Item & Price';
    }

    protected function with(): array
    {
        return ['vendor', 'item'];
    }

    protected function searchable(): array
    {
        return ['supplier_part_no'];
    }

    /**
     * Buying terms for one material: every supplier that sells it, ranked, plus
     * the terms MRP would actually plan against.
     *
     * The screens raising a requisition or a purchase order need the same answer
     * the planner already computes — who to buy from, at what price, with how
     * much notice — so it is served from MrpService rather than reimplemented
     * here, where the two could quietly drift apart.
     */
    public function terms(Request $request, MrpService $mrp)
    {
        $request->validate(['item_id' => ['required', 'integer', 'exists:m_item,id']]);
        $itemId = (int) $request->query('item_id');

        return ApiResponse::item([
            'item_id' => $itemId,
            // What MRP would use: the preferred supplier, or the item fallback.
            'effective' => $mrp->supplierTerms($itemId),
            'suppliers' => m_supplier_item::with('vendor')
                ->where('item_id', $itemId)
                ->where('active', 1)
                ->orderBy('priority')->orderBy('price')
                ->get(),
        ]);
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'ven_id' => ['required', 'integer', 'exists:m_contacts,id'],
            'item_id' => ['required', 'integer', 'exists:m_item,id'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:99'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'integer', 'exists:m_currency,id'],
            // Zero means "no constraint" — the sensible default for a one-off buy.
            'moq' => ['nullable', 'integer', 'min:0'],
            'order_lot' => ['nullable', 'integer', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'supplier_part_no' => ['nullable', 'string', 'max:50'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
