<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Generic server-side CRUD for master/reference tables.
 * Subclasses declare model(), rules(), and optionally searchable()/with()/label().
 * List supports ?q= search, ?sort=&dir=, ?per_page= pagination.
 */
abstract class CrudController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** Validation rules; $id is null on create, the record id on update. */
    abstract protected function rules(Request $request, ?int $id = null): array;

    /** Columns matched by the ?q= free-text filter. */
    protected function searchable(): array
    {
        return [];
    }

    /** Eager-loaded relations for index/show. */
    protected function with(): array
    {
        return [];
    }

    /** Human label used in audit messages. */
    protected function label(): string
    {
        return class_basename($this->model());
    }

    public function index(Request $request)
    {
        $model = $this->model();
        $query = $model::query()->with($this->with());

        if ($q = trim((string) $request->query('q', ''))) {
            $cols = $this->searchable();
            if ($cols) {
                $query->where(function ($sub) use ($cols, $q) {
                    foreach ($cols as $col) {
                        $sub->orWhere($col, 'like', "%{$q}%");
                    }
                });
            }
        }

        $sort = $request->query('sort');
        $instance = new $model;
        if ($sort && in_array($sort, $instance->getFillable(), true)) {
            $dir = strtolower((string) $request->query('dir')) === 'desc' ? 'desc' : 'asc';
            $query->orderBy($sort, $dir);
        } else {
            $query->orderByDesc($instance->getKeyName());
        }

        $perPage = min(max((int) $request->query('per_page', 20), 1), 200);

        return ApiResponse::paginated($query->paginate($perPage));
    }

    public function show(Request $request, int $id)
    {
        $record = $this->model()::with($this->with())->findOrFail($id);

        return ApiResponse::item($record);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules($request));
        $record = $this->model()::create($this->mutate($data, $request));

        AuditLogger::record($request, "Create {$this->label()} #{$record->getKey()}");

        /*
         * Dibaca ulang dari database, bukan dikembalikan apa adanya: kolom yang
         * nilainya diisi database — default `active`, timestamp, kolom yang
         * dihitung trigger — tidak ada pada model hasil create, sehingga klien
         * menerima record yang bolong pada bagian yang justru baru saja diisi.
         */
        return ApiResponse::item($record->refresh()->load($this->with()), 201);
    }

    public function update(Request $request, int $id)
    {
        $record = $this->model()::findOrFail($id);
        $data = $request->validate($this->rules($request, $id));
        $record->update($this->mutate($data, $request));

        AuditLogger::record($request, "Update {$this->label()} #{$id}");

        return ApiResponse::item($record->load($this->with()));
    }

    public function destroy(Request $request, int $id)
    {
        $record = $this->model()::findOrFail($id);
        $record->delete();

        AuditLogger::record($request, "Delete {$this->label()} #{$id}");

        return ApiResponse::item(['message' => 'Data berhasil dihapus.']);
    }

    /** Hook to transform validated data before persist (e.g. defaults, casts). */
    protected function mutate(array $data, Request $request): array
    {
        return $data;
    }
}
