<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\acc_coa;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Chart of Accounts master. */
class CoaController extends Controller
{
    private array $groups = ['ASSET', 'LIABILITY', 'EQUITY', 'REVENUE', 'COGS', 'EXPENSE'];

    public function index(Request $request)
    {
        $q = acc_coa::query();
        if ($s = trim((string) $request->query('q', ''))) {
            $q->where(fn ($w) => $w->where('code', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%"));
        }
        if ($g = $request->query('acc_group')) {
            $q->where('acc_group', $g);
        }

        return ApiResponse::paginated($q->orderBy('code')->paginate(min(max((int) $request->query('per_page', 100), 1), 500)));
    }

    public function store(Request $request)
    {
        $row = acc_coa::create($this->validateRow($request));
        AuditLogger::record($request, "Create COA {$row->code}");

        return ApiResponse::item($row, 201);
    }

    public function update(Request $request, int $id)
    {
        $row = acc_coa::findOrFail($id);
        $row->update($this->validateRow($request, $id));
        AuditLogger::record($request, "Update COA {$row->code}");

        return ApiResponse::item($row);
    }

    public function destroy(Request $request, int $id)
    {
        $row = acc_coa::findOrFail($id);
        if (DB::table('acc_journal_det')->where('coa_id', $id)->exists()) {
            throw BizException::make('COA_USED', 'Akun ini sudah dipakai pada jurnal, tidak dapat dihapus.');
        }
        $row->delete();
        AuditLogger::record($request, "Delete COA {$row->code}");

        return ApiResponse::item(['message' => 'Akun dihapus.']);
    }

    private function validateRow(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('acc_coa', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:150'],
            'acc_group' => ['required', Rule::in($this->groups)],
            'parent_id' => ['nullable', 'integer', 'exists:acc_coa,id'],
            'postable' => ['nullable', 'boolean'],
        ]);
        $data['postable'] = (int) ($data['postable'] ?? 1);

        return $data;
    }
}
