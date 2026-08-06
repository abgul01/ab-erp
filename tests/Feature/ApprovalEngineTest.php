<?php

use App\Exceptions\BizException;
use App\Models\Approval;
use App\Models\m_item;
use App\Models\prc_po_main;
use App\Support\ApprovalEngine;
use App\Support\HasApproval;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Multi-level approval across every registered document type.
 *
 * The engine has to be indifferent to how a table spells its states — a Work
 * Order stores integers, a Purchase Order strings — and a document must never
 * reach its approved state while a level is still outstanding.
 */
function newItem(): m_item
{
    return m_item::create([
        'code' => 'IT-APP-'.substr(uniqid(), -8), 'part_name' => 'Item uji approval', 'type' => 'RM',
        'category_id' => DB::table('m_i_category')->value('id'), 'min_stock' => 0, 'max_stock' => 0, 'active' => 1,
    ]);
}

it('registers a model for every document type it advertises', function () {
    $engine = new ApprovalEngine;

    foreach ($engine->registry() as $type => $def) {
        expect(class_exists($def['model']))->toBeTrue("model untuk {$type} tidak ada")
            ->and(in_array(HasApproval::class, class_uses_recursive($def['model']), true))
            ->toBeTrue("{$type} belum memakai HasApproval");
    }
});

it('creates one pending row per level on submit', function () {
    $po = prc_po_main::create([
        'code' => 'PO-APP-'.substr(uniqid(), -6), 'date' => now()->toDateString(), 'po_type' => 'LOCAL',
        'source' => 'MANUAL', 'ven_id' => DB::table('m_contacts')->value('id'),
        'user_id' => admin()->id, 'status' => 'DRAFT',
    ]);

    (new ApprovalEngine)->submit($po);

    expect(Approval::where('doc_type', 'prc_po_main')->where('doc_id', $po->id)->count())->toBe(2)
        ->and($po->fresh()->status)->toBe('SUBMITTED');
});

it('holds the document back until the last level signs', function () {
    $po = prc_po_main::create([
        'code' => 'PO-APP-'.substr(uniqid(), -6), 'date' => now()->toDateString(), 'po_type' => 'LOCAL',
        'source' => 'MANUAL', 'ven_id' => DB::table('m_contacts')->value('id'),
        'user_id' => admin()->id, 'status' => 'DRAFT',
    ]);
    $engine = new ApprovalEngine;
    $engine->submit($po);

    expect($engine->approve($po, admin()))->toBeFalse()
        ->and($po->fresh()->status)->toBe('SUBMITTED');       // still one level to go

    expect($engine->approve($po->fresh(), admin()))->toBeTrue()
        ->and($po->fresh()->status)->toBe('APPROVED');
});

it('refuses a second submit while approvals are outstanding', function () {
    $item = newItem();
    $engine = new ApprovalEngine;
    $engine->submit($item);

    expect(fn () => $engine->submit($item->fresh()))
        ->toThrow(BizException::class);
});

it('moves master data through approval now that it has a status', function () {
    $item = newItem();
    $engine = new ApprovalEngine;

    $engine->submit($item);
    expect($item->fresh()->status)->toBe('SUBMITTED');

    $engine->approve($item->fresh(), admin());
    expect($item->fresh()->status)->toBe('APPROVED');
});

it('rejects with a reason and skips the levels below', function () {
    $po = prc_po_main::create([
        'code' => 'PO-APP-'.substr(uniqid(), -6), 'date' => now()->toDateString(), 'po_type' => 'LOCAL',
        'source' => 'MANUAL', 'ven_id' => DB::table('m_contacts')->value('id'),
        'user_id' => admin()->id, 'status' => 'DRAFT',
    ]);
    $engine = new ApprovalEngine;
    $engine->submit($po);

    $engine->reject($po, admin(), 'Harga di atas anggaran');

    $rows = Approval::where('doc_type', 'prc_po_main')->where('doc_id', $po->id)->orderBy('level')->get();

    expect($po->fresh()->status)->toBe('REJECTED')
        ->and($rows[0]->status)->toBe('REJECTED')
        ->and($rows[0]->note)->toBe('Harga di atas anggaran')
        ->and($rows[1]->status)->toBe('SKIPPED');
});

it('exposes approve over HTTP for any document type', function () {
    Sanctum::actingAs(admin());
    $item = newItem();
    (new ApprovalEngine)->submit($item);

    $approvalId = Approval::where('doc_type', 'm_item')->where('doc_id', $item->id)->value('id');

    $this->postJson("/api/v1/approvals/{$approvalId}/approve")
        ->assertOk()
        ->assertJsonPath('data.fully_approved', true);

    expect($item->fresh()->status)->toBe('APPROVED');
});

it('lists the document types that can be routed', function () {
    Sanctum::actingAs(admin());

    $types = collect($this->getJson('/api/v1/approvals/types')->assertOk()->json('data'))->pluck('doc_type');

    expect($types)->toContain('m_item', 'prd_wo_main', 'ast_main');
});
