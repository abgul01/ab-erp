<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use App\Support\MenuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $credentials['username'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Username atau password salah.'],
            ]);
        }

        // Optional per-user active status gate (status_id != inactive)
        if ($user->status && $user->status->status === 'INACTIVE') {
            throw ValidationException::withMessages([
                'username' => ['Akun tidak aktif. Hubungi administrator.'],
            ]);
        }

        $token = $user->createToken('spa')->plainTextToken;

        AuditLogger::record($request, "Login: {$user->username}");

        return ApiResponse::item([
            'token' => $token,
            'user' => $this->userPayload($user),
            'menus' => MenuService::treeFor($user),
            'permissions' => MenuService::permissionMap($user),
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return ApiResponse::item([
            'user' => $this->userPayload($user),
            'menus' => MenuService::treeFor($user),
            'permissions' => MenuService::permissionMap($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        AuditLogger::record($request, 'Logout');

        return ApiResponse::item(['message' => 'Logout berhasil.']);
    }

    /** Ganti password akun sendiri; token lain dimatikan, token aktif dipertahankan. */
    public function changePassword(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password saat ini salah.'],
            ]);
        }

        $user->update(['password' => $data['password']]);
        $user->tokens()
            ->where('id', '!=', $user->currentAccessToken()?->id)
            ->delete();

        AuditLogger::record($request, 'Ganti password sendiri');

        return ApiResponse::item(['message' => 'Password berhasil diganti.']);
    }

    private function userPayload(User $user): array
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
