<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\BizException;
use App\Http\Controllers\Api\CrudController;
use App\Models\menus;
use App\Models\user_menu_permissions;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Manajemen menu (PRD 2.4 Module Administrator — CRUD Menu & Setting Menu).
 * Sidebar dibangun dari tabel `menus` oleh MenuService, jadi perubahan di
 * sini langsung tercermin setelah user refresh sesi.
 */
class MenuController extends CrudController
{
    protected function model(): string
    {
        return menus::class;
    }

    protected function label(): string
    {
        return 'Menu';
    }

    protected function searchable(): array
    {
        return ['name', 'link', 'icon'];
    }

    protected function with(): array
    {
        return ['parent', 'children'];
    }

    /** Flat list semua menu (sudah urut) + jumlah anak, untuk ditampilkan dan disusun ulang. */
    public function index(Request $request)
    {
        $menus = menus::withCount('children')
            ->with('parent')
            ->orderBy('parent_id')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return ApiResponse::collection($menus);
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'link' => [
                'nullable', 'string', 'max:50',
                // '0' dipakai bersama oleh semua grup induk; hanya link nyata yang unik.
                $request->input('link') && $request->input('link') !== '0'
                    ? Rule::unique('menus', 'link')->ignore($id)
                    : 'nullable',
            ],
            'parent_id' => ['required', 'integer', 'exists:menus,id'],
            'icon' => ['nullable', 'string', 'max:50'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function mutate(array $data, Request $request): array
    {
        $data['link'] = $data['link'] ?? '0';
        $data['sort'] = $data['sort'] ?? 0;

        return $data;
    }

    public function update(Request $request, int $id)
    {
        $target = menus::findOrFail($id);
        $newParent = (int) ($request->input('parent_id') ?? $target->parent_id);

        if ($newParent === $id) {
            throw BizException::make('MENU_LOOP', 'Menu tidak dapat dijadikan anak dari dirinya sendiri.', 422);
        }
        if (in_array($newParent, $this->descendantIds($id), true)) {
            throw BizException::make('MENU_LOOP', 'Menu tidak dapat dipindahkan ke bawah keturunannya sendiri.', 422);
        }

        return parent::update($request, $id);
    }

    public function destroy(Request $request, int $id)
    {
        $target = menus::findOrFail($id);

        if ($target->children()->exists()) {
            throw BizException::make('MENU_HAS_CHILDREN', 'Hapus dahulu menu anak sebelum menghapus menu ini.', 409);
        }

        DB::transaction(function () use ($target) {
            user_menu_permissions::where('menu_id', $target->id)->delete();
            $target->delete();
        });

        AuditLogger::record($request, "Delete Menu #{$id} ({$target->name})");

        return ApiResponse::item(['message' => 'Menu berhasil dihapus.']);
    }

    private function descendantIds(int $id): array
    {
        $ids = [];
        $children = menus::where('parent_id', $id)->pluck('id')->all();
        foreach ($children as $child) {
            $ids[] = $child;
            array_push($ids, ...$this->descendantIds($child));
        }

        return $ids;
    }
}
