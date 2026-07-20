<?php

namespace App\Http\Controllers\Api\Procurement;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\prc_pr_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Purchase Requisition (Fase 2A). Header prc_pr_main + lines prc_pr_detail.
 * Lifecycle: DRAFT → SUBMITTED → APPROVED (or REJECTED). Editable only in DRAFT.
 */
class PrController extends Controller
{
    private array $with = ['user', 'detail.item', 'detail.uom'];
    private const TYPES = ['MANUAL', 'MRP', 'ADDITIONAL', 'NON_RM', 'NPD'];

    public function index(Request $request)
    {
        $query = prc_pr_main::with(['user'])->withCount('detail');

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where('code', 'like', "%{$q}%");
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $query->orderByDesc('id');
        $perPage = min(max((int) $request->query('per_page', 20), 1), 200);

        return ApiResponse::paginated($query->paginate($perPage));
    }

    public function show(int $id)
    {
        return ApiResponse::item(prc_pr_main::with($this->with)->findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $this->validatePr($request);

        $pr = DB::transaction(function () use ($data, $request) {
            $pr = prc_pr_main::create([
                'code' => (new NumberingService)->next('PR', 'PR'),
                'date' => $data['date'],
                'pr_type' => $data['pr_type'],
                'user_id' => $request->user()->id,
                'status' => 'DRAFT',
            ]);
            $this->syncLines($pr, $data['lines'] ?? []);
            AuditLogger::record($request, "Create PR {$pr->code}", $pr->code);

            return $pr;
        });

        return ApiResponse::item($pr->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $pr = prc_pr_main::findOrFail($id);
        $this->assertDraft($pr);
        $data = $this->validatePr($request);

        DB::transaction(function () use ($pr, $data, $request) {
            $pr->update(['date' => $data['date'], 'pr_type' => $data['pr_type']]);
            $pr->detail()->delete();
            $this->syncLines($pr, $data['lines'] ?? []);
            AuditLogger::record($request, "Update PR {$pr->code}", $pr->code);
        });

        return ApiResponse::item($pr->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $pr = prc_pr_main::findOrFail($id);
        $this->assertDraft($pr);
        DB::transaction(function () use ($pr, $request) {
            $pr->detail()->delete();
            $pr->delete();
            AuditLogger::record($request, "Delete PR {$pr->code}", $pr->code);
        });

        return ApiResponse::item(['message' => 'PR berhasil dihapus.']);
    }

    public function submit(Request $request, int $id)
    {
        $pr = prc_pr_main::findOrFail($id);
        if ($pr->status !== 'DRAFT') {
            throw BizException::make('PR_NOT_DRAFT', 'Hanya PR berstatus DRAFT yang dapat disubmit.');
        }
        if ($pr->detail()->count() === 0) {
            throw BizException::make('PR_EMPTY', 'PR tanpa baris item tidak dapat disubmit.');
        }
        $pr->update(['status' => 'SUBMITTED']);
        AuditLogger::record($request, "Submit PR {$pr->code}", $pr->code);

        return ApiResponse::item($pr->load($this->with));
    }

    public function approve(Request $request, int $id)
    {
        return $this->transition($request, $id, 'SUBMITTED', 'APPROVED', 'Approve');
    }

    public function reject(Request $request, int $id)
    {
        return $this->transition($request, $id, 'SUBMITTED', 'REJECTED', 'Reject');
    }

    private function transition(Request $request, int $id, string $from, string $to, string $verb)
    {
        $pr = prc_pr_main::findOrFail($id);
        if ($pr->status !== $from) {
            throw BizException::make('PR_BAD_STATE', "PR harus berstatus {$from} untuk {$verb}.");
        }
        $pr->update(['status' => $to]);
        AuditLogger::record($request, "{$verb} PR {$pr->code}", $pr->code);

        return ApiResponse::item($pr->load($this->with));
    }

    private function assertDraft(prc_pr_main $pr): void
    {
        if ($pr->status !== 'DRAFT') {
            throw BizException::make('PR_LOCKED', 'PR yang sudah disubmit tidak dapat diubah.');
        }
    }

    private function syncLines(prc_pr_main $pr, array $lines): void
    {
        foreach ($lines as $l) {
            $pr->detail()->create([
                'item_id' => $l['item_id'],
                'qty' => $l['qty'],
                'uom_id' => $l['uom_id'] ?? null,
                'need_date' => $l['need_date'] ?? null,
                'wo_id' => $l['wo_id'] ?? null,
                'note' => $l['note'] ?? null,
            ]);
        }
    }

    private function validatePr(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'pr_type' => ['required', 'in:' . implode(',', self::TYPES)],
            'lines' => ['array'],
            'lines.*.item_id' => ['required', 'integer', 'exists:m_item,id'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.uom_id' => ['nullable', 'integer', 'exists:m_uom,id'],
            'lines.*.need_date' => ['nullable', 'date'],
            'lines.*.note' => ['nullable', 'string', 'max:150'],
        ]);
    }
}
