<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard: perm:{menuLink},{action}
 * e.g. ->middleware('perm:items,create')
 * Super admin bypasses. Others must have the matching user_menu_permissions flag.
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $menuLink, string $action = 'view'): Response
    {
        $user = $request->user();

        // Supplier portal accounts are confined to /api/v1/vendor. Every internal
        // endpoint is guarded by this middleware, so refusing here is what keeps
        // the two populations apart regardless of the permission rows they hold.
        if ($user?->isVendor()) {
            return response()->json([
                'errors' => [[
                    'code' => 'VENDOR_SCOPE',
                    'message' => 'Akun vendor hanya dapat mengakses portal vendor.',
                    'field' => null,
                ]],
            ], 403);
        }

        if (! $user || ! $user->canDo($menuLink, $action)) {
            return response()->json([
                'errors' => [[
                    'code' => 'FORBIDDEN',
                    'message' => "Anda tidak memiliki hak akses untuk aksi '{$action}' pada menu '{$menuLink}'.",
                    'field' => null,
                ]],
            ], 403);
        }

        return $next($request);
    }
}
