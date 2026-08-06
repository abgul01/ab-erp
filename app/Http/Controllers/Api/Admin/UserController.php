<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\BizException;
use App\Http\Controllers\Api\CrudController;
use App\Models\menus;
use App\Models\status_id;
use App\Models\User;
use App\Models\user_menu_permissions;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Manajemen user (PRD 2.4 Module Administrator — CRUD User, ganti password,
 * atur hak akses). Super admin selalu bypass; pengaman di sini melindungi
 * akun super admin dan akun sendiri dari penyalahgunaan.
 */
class UserController extends CrudController
{
    private const FLAGS = ['can_view', 'can_create', 'can_edit', 'can_delete', 'can_download', 'can_import'];

    protected function model(): string
    {
        return User::class;
    }

    protected function label(): string
    {
        return 'User';
    }

    protected function with(): array
    {
        return ['status', 'ven'];
    }

    protected function searchable(): array
    {
        return ['username', 'name', 'email', 'identity'];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:100', Rule::unique('users', 'email')->ignore($id)],
            'identity' => ['nullable', 'string', 'max:255', Rule::unique('users', 'identity')->ignore($id)],
            'password' => [$id === null ? 'required' : 'nullable', 'string', 'min:6'],
            'status_id' => ['nullable', 'integer', 'exists:status_id,id'],
            'ven_id' => ['nullable', 'integer', 'exists:m_contacts,id'],
        ];
    }

    protected function mutate(array $data, Request $request): array
    {
        if (empty($data['password'])) {
            unset($data['password']);   // update tanpa mengubah password
        }
        if (! isset($data['status_id']) || ! $data['status_id']) {
            $data['status_id'] = status_id::where('status', 'ACTIVE')->value('id');
        }

        return $data;
    }

    public function update(Request $request, int $id)
    {
        $target = User::findOrFail($id);

        if ($target->isSuperAdmin()) {
            $status = status_id::find($request->input('status_id'));
            if ($request->input('username') && $request->input('username') !== $target->username) {
                throw BizException::make('ADMIN_USER_LOCKED', 'Username akun super admin tidak dapat diubah.', 423);
            }
            if ($status && $status->status !== 'ADMIN') {
                throw BizException::make('ADMIN_USER_LOCKED', 'Status akun super admin tidak dapat diubah.', 423);
            }
        }

        if ($target->id === $request->user()->id) {
            $status = status_id::find($request->input('status_id'));
            if ($status && $status->status === 'INACTIVE') {
                throw BizException::make('ADMIN_USER_LOCKED', 'Tidak dapat menonaktifkan akun sendiri.', 423);
            }
        }

        return parent::update($request, $id);
    }

    public function destroy(Request $request, int $id)
    {
        $target = User::findOrFail($id);

        if ($target->id === $request->user()->id) {
            throw BizException::make('ADMIN_USER_LOCKED', 'Tidak dapat menghapus akun sendiri.', 409);
        }
        if ($target->isSuperAdmin()) {
            throw BizException::make('ADMIN_USER_LOCKED', 'Akun super admin tidak dapat dihapus.', 409);
        }

        DB::transaction(function () use ($target) {
            user_menu_permissions::where('user_id', $target->id)->delete();
            $target->tokens()->delete();
            $target->delete();
        });

        AuditLogger::record($request, "Delete User #{$id} ({$target->username})");

        return ApiResponse::item(['message' => 'User berhasil dihapus.']);
    }

    /** Opsi status untuk form (ADMIN / ACTIVE / INACTIVE). */
    public function statuses()
    {
        return ApiResponse::collection(status_id::orderBy('id')->get(['id', 'status']));
    }

    /** Reset password oleh admin; token lain dimatikan agar user login ulang. */
    public function changePassword(Request $request, int $id)
    {
        $target = User::findOrFail($id);
        $data = $request->validate([
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $target->update(['password' => $data['password']]);
        $target->tokens()
            ->where('id', '!=', $request->user()->currentAccessToken()?->id)
            ->delete();

        AuditLogger::record($request, "Reset password User #{$id} ({$target->username})");

        return ApiResponse::item(['message' => 'Password user berhasil diganti.']);
    }

    /** Matriks hak akses: semua menu + flag yang dimiliki user saat ini. */
    public function permissions(Request $request, int $id)
    {
        $user = User::with('permissions')->findOrFail($id);
        $menus = menus::orderBy('parent_id')->orderBy('sort')->orderBy('id')
            ->get(['id', 'name', 'link', 'parent_id']);

        return ApiResponse::item([
            'user' => $this->payload($user),
            'menus' => $menus,
            'permissions' => $user->permissions->mapWithKeys(fn ($p) => [
                $p->menu_id => [
                    'can_view' => (bool) $p->can_view,
                    'can_create' => (bool) $p->can_create,
                    'can_edit' => (bool) $p->can_edit,
                    'can_delete' => (bool) $p->can_delete,
                    'can_download' => (bool) $p->can_download,
                    'can_import' => (bool) $p->can_import,
                ],
            ]),
        ]);
    }

    /** Simpan ulang seluruh matriks hak akses seorang user (full replace). */
    public function updatePermissions(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        if ($user->isSuperAdmin()) {
            throw BizException::make('ADMIN_USER_LOCKED', 'Super admin otomatis memiliki semua hak akses.', 423);
        }

        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*.menu_id' => ['required', 'integer', 'exists:menus,id'],
            'permissions.*.can_view' => ['boolean'],
            'permissions.*.can_create' => ['boolean'],
            'permissions.*.can_edit' => ['boolean'],
            'permissions.*.can_delete' => ['boolean'],
            'permissions.*.can_download' => ['boolean'],
            'permissions.*.can_import' => ['boolean'],
        ]);

        DB::transaction(function () use ($user, $data) {
            user_menu_permissions::where('user_id', $user->id)->delete();

            foreach ($data['permissions'] as $row) {
                $flags = [];
                foreach (self::FLAGS as $flag) {
                    $flags[$flag] = (int) ($row[$flag] ?? 0);
                }
                if (array_sum($flags) === 0) {
                    continue;
                }

                user_menu_permissions::create([
                    'user_id' => $user->id,
                    'menu_id' => $row['menu_id'],
                    ...$flags,
                ]);
            }
        });

        AuditLogger::record($request, "Update hak akses User #{$id} ({$user->username})");

        return ApiResponse::item(['message' => 'Hak akses user berhasil disimpan.']);
    }

    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'identity' => $user->identity,
            'is_super_admin' => $user->isSuperAdmin(),
            'status' => optional($user->status)->status,
        ];
    }
}
