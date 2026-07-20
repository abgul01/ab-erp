<?php

namespace App\Http\Controllers\Api\Engineering;

use App\Http\Controllers\Api\CrudController;
use App\Models\m_bom;
use App\Models\m_bom_pro;
use App\Models\m_item;
use App\Models\m_item_customer;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Master Item with 5 logical tabs:
 *   1. Main    — code, part_name, type, descrip, category_id, pm, active
 *   2. Detail  — o_d, i_d, thick, width, height, length, length_cut, weight, tolerance, min/max stock
 *   3. BOM     — RM lines (m_bom_det_rm) + PM lines (m_bom_det_pm) under one m_bom header (FG)
 *   4. Process — ordered routing (m_bom_pro_det under m_bom_pro header) (FG)
 *   5. Customer— customers this item may ship to (m_item_customer) (FG)
 *
 * The m_item model/schema is left untouched; BOM / process / customer are
 * persisted transactionally into their own tables from the nested payload.
 */
class ItemController extends CrudController
{
    /** Flat m_item columns owned by the Main + Detail tabs. */
    private const FLAT = [
        'code', 'part_name', 'type', 'descrip', 'category_id',
        'o_d', 'i_d', 'thick', 'width', 'height', 'length', 'length_cut',
        'weight', 'tolerance', 'min_stock', 'max_stock', 'pm', 'active',
    ];

    protected function model(): string
    {
        return m_item::class;
    }

    protected function label(): string
    {
        return 'Item';
    }

    protected function searchable(): array
    {
        return ['code', 'part_name', 'descrip'];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            // --- Tab 1: Main ---
            'code' => ['required', 'string', 'max:50', Rule::unique('m_item', 'code')->ignore($id)],
            'part_name' => ['required', 'string', 'max:50'],
            'type' => ['required', 'string', Rule::in(['Pipe', 'Roundbar', 'Square Pipe', 'Square Bar', 'Plat Bar', 'Other'])],
            'descrip' => ['nullable', 'string', 'max:150'],
            'category_id' => ['required', 'integer', 'exists:m_i_category,id'],
            'pm' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],

            // --- Tab 2: Detail ---
            'o_d' => ['nullable', 'numeric'],
            'i_d' => ['nullable', 'numeric'],
            'thick' => ['nullable', 'numeric'],
            'width' => ['nullable', 'numeric'],
            'height' => ['nullable', 'numeric'],
            'length' => ['nullable', 'numeric'],
            'length_cut' => ['nullable', 'numeric'],
            'weight' => ['nullable', 'numeric'],
            'tolerance' => ['nullable', 'string', 'max:50'],
            'min_stock' => ['nullable', 'integer'],
            'max_stock' => ['nullable', 'integer'],

            // --- Tab 3: BOM (FG) ---
            'rm_lines' => ['array'],
            'rm_lines.*.mat_id' => ['required', 'integer', 'exists:m_item,id'],
            'rm_lines.*.length_cut' => ['nullable', 'numeric', 'min:0'],
            'rm_lines.*.length_use' => ['nullable', 'numeric', 'min:0'],
            'rm_lines.*.priority' => ['nullable', 'integer', 'min:0'],
            'pm_lines' => ['array'],
            'pm_lines.*.pm_id' => ['required', 'integer', 'exists:m_item,id'],
            'pm_lines.*.qty' => ['required', 'integer', 'min:1'],

            // --- Tab 4: Process (FG) ---
            'processes' => ['array'],
            'processes.*.proc_id' => ['required', 'integer', 'exists:m_process,id'],
            'processes.*.sequence' => ['nullable', 'integer', 'min:0'],

            // --- Tab 5: Customer (FG) ---
            'customers' => ['array'],
            'customers.*.cus_id' => ['required', 'integer', 'exists:m_contacts,id'],
            'customers.*.priority' => ['nullable', 'integer', 'min:0'],
            'customers.*.active' => ['nullable', 'boolean'],
        ];
    }

    public function show(Request $request, int $id)
    {
        $item = m_item::findOrFail($id);
        $bom = m_bom::with(['rmLines.material', 'pmLines.part'])->where('item_id', $id)->first();
        $pro = m_bom_pro::with(['detail.process'])->where('item_id', $id)->first();

        $data = $item->toArray();
        $data['rm_lines'] = $bom ? $bom->rmLines->map(fn ($l) => [
            'mat_id' => $l->mat_id,
            'length_cut' => $l->length_cut,
            'length_use' => $l->length_use,
            'priority' => $l->priority,
            'material' => $l->material?->only(['id', 'code', 'part_name']),
        ])->all() : [];
        $data['pm_lines'] = $bom ? $bom->pmLines->map(fn ($l) => [
            'pm_id' => $l->pm_id,
            'qty' => $l->qty,
            'part' => $l->part?->only(['id', 'code', 'part_name']),
        ])->all() : [];
        $data['processes'] = $pro ? $pro->detail->sortBy('sequence')->map(fn ($d) => [
            'proc_id' => $d->proc_id,
            'sequence' => $d->sequence,
            'process' => $d->process?->only(['id', 'code', 'name_p']),
        ])->values()->all() : [];
        $data['customers'] = m_item_customer::with('cus')->where('item_id', $id)->orderBy('priority')->get()->map(fn ($c) => [
            'cus_id' => $c->cus_id,
            'priority' => $c->priority,
            'active' => (int) $c->active,
            'cus' => $c->cus?->only(['id', 'company_n']),
        ])->all();

        return ApiResponse::item($data);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules($request));

        $item = DB::transaction(function () use ($data, $request) {
            $item = m_item::create($this->mutate($this->flat($data), $request));
            $this->syncNested($item, $data);
            AuditLogger::record($request, "Create Item #{$item->id} ({$item->code})");

            return $item;
        });

        return $this->show($request, $item->id)->setStatusCode(201);
    }

    public function update(Request $request, int $id)
    {
        $item = m_item::findOrFail($id);
        $data = $request->validate($this->rules($request, $id));

        DB::transaction(function () use ($item, $data, $request) {
            $item->update($this->mutate($this->flat($data), $request));
            $this->syncNested($item, $data);
            AuditLogger::record($request, "Update Item #{$item->id} ({$item->code})");
        });

        return $this->show($request, $id);
    }

    /** Keep only the flat m_item columns for mass assignment. */
    private function flat(array $data): array
    {
        return array_intersect_key($data, array_flip(self::FLAT));
    }

    protected function mutate(array $data, Request $request): array
    {
        $data['pm'] = (int) ($data['pm'] ?? 0);
        $data['active'] = (int) ($data['active'] ?? 1);
        $data['min_stock'] = (int) ($data['min_stock'] ?? 0);
        $data['max_stock'] = (int) ($data['max_stock'] ?? 0);

        return $data;
    }

    /**
     * Persist the FG tabs into their own tables. A tab is only rewritten when
     * its key is present in the payload, so a Main/Detail-only save is untouched.
     */
    private function syncNested(m_item $item, array $data): void
    {
        if (array_key_exists('rm_lines', $data) || array_key_exists('pm_lines', $data)) {
            $bom = m_bom::firstOrCreate(['item_id' => $item->id], ['active' => 1]);
            $bom->rmLines()->delete();
            $bom->pmLines()->delete();
            foreach ($data['rm_lines'] ?? [] as $i => $l) {
                $bom->rmLines()->create([
                    'mat_id' => $l['mat_id'],
                    'length_cut' => $l['length_cut'] ?? 0,
                    'length_use' => $l['length_use'] ?? 0,
                    'priority' => $l['priority'] ?? ($i + 1),
                ]);
            }
            foreach ($data['pm_lines'] ?? [] as $l) {
                $bom->pmLines()->create([
                    'pm_id' => $l['pm_id'],
                    'qty' => $l['qty'],
                ]);
            }
        }

        if (array_key_exists('processes', $data)) {
            $pro = m_bom_pro::firstOrCreate(['item_id' => $item->id]);
            $pro->detail()->delete();
            foreach ($data['processes'] ?? [] as $i => $p) {
                $pro->detail()->create([
                    'proc_id' => $p['proc_id'],
                    'sequence' => $p['sequence'] ?? ($i + 1),
                ]);
            }
        }

        if (array_key_exists('customers', $data)) {
            m_item_customer::where('item_id', $item->id)->delete();
            foreach ($data['customers'] ?? [] as $i => $c) {
                m_item_customer::create([
                    'item_id' => $item->id,
                    'cus_id' => $c['cus_id'],
                    'priority' => $c['priority'] ?? ($i + 1),
                    'active' => (int) ($c['active'] ?? 1),
                ]);
            }
        }
    }
}
