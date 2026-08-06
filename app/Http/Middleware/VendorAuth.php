<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vendor portal guard: the request must carry a valid vendor user
 * (user with ven_id set) authenticated via Sanctum.
 *
 * Attach to vendor routes: ->middleware('vendor.auth')
 */
class VendorAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isVendor()) {
            return response()->json([
                'errors' => [[
                    'code' => 'VENDOR_REQUIRED',
                    'message' => 'Akses vendor tidak valid.',
                    'field' => null,
                ]],
            ], 403);
        }

        return $next($request);
    }
}
