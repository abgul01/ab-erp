<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guard for the supplier portal.
 *
 * Only accounts pinned to a supplier (users.ven_id) may enter, and the supplier
 * id is put on the request so controllers scope their queries to it instead of
 * trusting anything the caller sends.
 */
class EnsureVendor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isVendor()) {
            return response()->json([
                'errors' => [[
                    'code' => 'NOT_VENDOR',
                    'message' => 'Portal ini hanya untuk akun vendor.',
                    'field' => null,
                ]],
            ], 403);
        }

        $request->attributes->set('ven_id', (int) $user->ven_id);

        return $next($request);
    }
}
