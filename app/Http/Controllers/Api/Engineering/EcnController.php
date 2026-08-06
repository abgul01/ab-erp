<?php

namespace App\Http\Controllers\Api\Engineering;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\eng_ecn_main;
use App\Support\ApiResponse;
use App\Support\ApprovalEngine;
use App\Support\AuditLogger;
use App\Support\EcnService;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Engineering Change Notice (PRD §4.3).
 *
 * DRAFT → SUBMITTED → APPROVED → APPLIED. Approving agrees the change; applying
 * makes it, and only on or after the effective date.
 */
class EcnController extends Controller
{
    private const TYPES = ['ITEM', 'BOM', 'ROUTING'];

    private array $with = ['detail', 'item', 'user'];

    public function __construct(private EcnService $svc) {}

    public function index(Request $request)
    {
        $query = eng_ecn_main::with(['item'])->withCount('detail');

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(fn ($sub) => $sub->where('code', 'like', "%{$q}%")->orWhere('reason', 'like', "%{$q}%"));
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($type = $request->query('change_type')) {
            $query->where('change_type', $type);
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 200);

        return ApiResponse::paginated($query->orderByDesc('id')->paginate($perPage));
    }

    public function show(int $id)
    {
        $ecn = eng_ecn_main::with($this->with)->findOrFail($id);

        return ApiResponse::item($ecn);
    }

    /** What a change to this part would touch — read before agreeing to it. */
    public function impact(Request $request)
    {
        $request->validate(['item_id' => ['required', 'integer', 'exists:m_item,id']]);

        return ApiResponse::item($this->svc->impact((int) $request->query('item_id')));
    }

    public function store(Request $request)
    {
        $data = $this->validateEcn($request);

        $ecn = DB::transaction(function () use ($data, $request) {
            $ecn = eng_ecn_main::create([
                'code' => app(NumberingService::class)->next('ECN', 'ECN'),
                'date' => $data['date'],
                'change_type' => $data['change_type'],
                'item_id' => $data['item_id'],
                'reason' => $data['reason'],
                'impact' => $data['impact'] ?? null,
                'effective_date' => $data['effective_date'],
                'user_id' => $request->user()->id,
                'status' => 'DRAFT',
            ]);

            $this->syncLines($ecn, $data);
            AuditLogger::record($request, "Create ECN {$ecn->code}", $ecn->code);

            return $ecn;
        });

        return ApiResponse::item($ecn->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $ecn = eng_ecn_main::findOrFail($id);
        $this->assertDraft($ecn);
        $data = $this->validateEcn($request);

        DB::transaction(function () use ($ecn, $data, $request) {
            $ecn->update([
                'date' => $data['date'],
                'change_type' => $data['change_type'],
                'item_id' => $data['item_id'],
                'reason' => $data['reason'],
                'impact' => $data['impact'] ?? null,
                'effective_date' => $data['effective_date'],
            ]);
            $ecn->detail()->delete();
            $this->syncLines($ecn, $data);
            AuditLogger::record($request, "Update ECN {$ecn->code}", $ecn->code);
        });

        return ApiResponse::item($ecn->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $ecn = eng_ecn_main::findOrFail($id);
        $this->assertDraft($ecn);

        DB::transaction(function () use ($ecn, $request) {
            $ecn->detail()->delete();
            $ecn->delete();
            AuditLogger::record($request, "Delete ECN {$ecn->code}", $ecn->code);
        });

        return ApiResponse::item(['message' => 'ECN berhasil dihapus.']);
    }

    public function submit(Request $request, int $id)
    {
        $ecn = eng_ecn_main::with('detail')->findOrFail($id);

        if ($ecn->status !== 'DRAFT') {
            throw BizException::make('ECN_NOT_DRAFT', 'Hanya ECN berstatus DRAFT yang dapat disubmit.');
        }
        if ($ecn->detail->isEmpty()) {
            throw BizException::make('ECN_EMPTY', 'ECN tanpa baris perubahan tidak dapat disubmit.');
        }

        // The "before" an approver reads is taken from the database at this
        // moment, not from whatever the browser last had.
        $this->svc->snapshot($ecn);
        $ecn->submitForApproval();
        AuditLogger::record($request, "Submit ECN {$ecn->code}", $ecn->code);

        return ApiResponse::item($ecn->fresh()->load($this->with));
    }

    public function approve(Request $request, int $id)
    {
        $ecn = eng_ecn_main::findOrFail($id);

        if ($ecn->status !== 'SUBMITTED') {
            throw BizException::make('ECN_BAD_STATE', 'ECN harus berstatus SUBMITTED untuk di-approve.');
        }

        app(ApprovalEngine::class)->approve($ecn, $request->user(), $request->input('note'));

        return ApiResponse::item($ecn->fresh()->load($this->with));
    }

    public function reject(Request $request, int $id)
    {
        $ecn = eng_ecn_main::findOrFail($id);
        $request->validate(['note' => 'required|string|max:300']);

        app(ApprovalEngine::class)->reject($ecn, $request->user(), $request->input('note'));

        return ApiResponse::item($ecn->fresh()->load($this->with));
    }

    /** Write the agreed change into the master. */
    public function apply(Request $request, int $id)
    {
        $ecn = eng_ecn_main::findOrFail($id);
        $applied = $this->svc->apply($ecn, $request->user()->id);

        AuditLogger::record($request, "Terapkan ECN {$ecn->code} ke master", $ecn->code);

        return ApiResponse::item($applied->load($this->with));
    }

    private function assertDraft(eng_ecn_main $ecn): void
    {
        if ($ecn->status !== 'DRAFT') {
            throw BizException::make('ECN_LOCKED', 'ECN yang sudah disubmit tidak dapat diubah.');
        }
    }

    private function syncLines(eng_ecn_main $ecn, array $data): void
    {
        foreach ($data['lines'] ?? [] as $l) {
            // Whitelist first: an impossible change must not reach an approver.
            $this->svc->assertLineAllowed($data['change_type'], $l);

            $ecn->detail()->create([
                'action' => $l['action'] ?? 'UPDATE',
                'target_table' => $l['target_table'],
                'target_id' => $l['target_id'] ?? null,
                'field' => $l['field'] ?? null,
                'new_value' => $l['new_value'] ?? null,
                'note' => $l['note'] ?? null,
            ]);
        }
    }

    private function validateEcn(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'change_type' => ['required', 'in:'.implode(',', self::TYPES)],
            'item_id' => ['required', 'integer', 'exists:m_item,id'],
            'reason' => ['required', 'string', 'max:400'],
            'impact' => ['nullable', 'string', 'max:400'],
            'effective_date' => ['required', 'date'],
            'lines' => ['array'],
            'lines.*.action' => ['nullable', 'in:UPDATE,ADD,REMOVE'],
            'lines.*.target_table' => ['required', 'string', 'max:30'],
            'lines.*.target_id' => ['nullable', 'integer'],
            'lines.*.field' => ['nullable', 'string', 'max:40'],
            'lines.*.new_value' => ['nullable', 'string', 'max:150'],
            'lines.*.note' => ['nullable', 'string', 'max:200'],
        ]);
    }
}
