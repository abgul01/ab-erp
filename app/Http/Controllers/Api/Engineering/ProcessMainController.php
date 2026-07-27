<?php

namespace App\Http\Controllers\Api\Engineering;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\m_process_main;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Master Routing (Master Process Main) — a reusable, ordered set of processes
 * that items share. Each item points at one of these via m_bom_pro; defining a
 * routing once here keeps the process sequence consistent across every item
 * that uses it.
 */
class ProcessMainController extends Controller
{
    private array $with = ['detail.process'];

    public function index(Request $request)
    {
        $query = m_process_main::with($this->with)->withCount('detail');
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(fn ($w) => $w->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"));
        }
        $query->orderBy('code');

        // options callers (item tab / cycle-time) want the full list with steps
        if ((int) $request->query('per_page', 20) >= 500) {
            return ApiResponse::collection($query->get());
        }

        return ApiResponse::paginated($query->paginate(min(max((int) $request->query('per_page', 20), 1), 200)));
    }

    public function show(int $id)
    {
        return ApiResponse::item(m_process_main::with($this->with)->findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $pm = DB::transaction(function () use ($data, $request) {
            $pm = m_process_main::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'active' => (int) ($data['active'] ?? 1),
            ]);
            $this->syncSteps($pm, $data['steps'] ?? []);
            AuditLogger::record($request, "Create Routing {$pm->code}", $pm->code);

            return $pm;
        });

        return ApiResponse::item($pm->load($this->with), 201);
    }

    public function update(Request $request, int $id)
    {
        $pm = m_process_main::findOrFail($id);
        $data = $this->validateData($request, $id);
        DB::transaction(function () use ($pm, $data, $request) {
            $pm->update(['code' => $data['code'], 'name' => $data['name'], 'active' => (int) ($data['active'] ?? 1)]);
            $pm->detail()->delete();
            $this->syncSteps($pm, $data['steps'] ?? []);
            AuditLogger::record($request, "Update Routing {$pm->code}", $pm->code);
        });

        return ApiResponse::item($pm->load($this->with));
    }

    public function destroy(Request $request, int $id)
    {
        $pm = m_process_main::findOrFail($id);
        if (DB::table('m_bom_pro')->where('process_main_id', $id)->exists()) {
            throw BizException::make('RT_USED', 'Routing ini masih dipakai item, tidak dapat dihapus.');
        }
        DB::transaction(function () use ($pm, $request) {
            $pm->detail()->delete();
            $pm->delete();
            AuditLogger::record($request, "Delete Routing {$pm->code}", $pm->code);
        });

        return ApiResponse::item(['message' => 'Routing dihapus.']);
    }

    private function syncSteps(m_process_main $pm, array $steps): void
    {
        foreach (array_values($steps) as $i => $s) {
            $pm->detail()->create([
                'proc_id' => $s['proc_id'],
                'sequence' => $s['sequence'] ?? ($i + 1),
            ]);
        }
    }

    private function validateData(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('m_process_main', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:100'],
            'active' => ['nullable', 'boolean'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.proc_id' => ['required', 'integer', 'exists:m_process,id'],
            'steps.*.sequence' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}
