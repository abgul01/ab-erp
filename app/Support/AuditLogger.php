<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Log Historical (PRD 2.3) — append-only audit trail into the existing log_prc table.
 */
class AuditLogger
{
    public static function record(Request $request, string $action, ?string $codeTr = null): void
    {
        DB::table('log_prc')->insert([
            'code_tr' => $codeTr ?? '-',
            'date' => now(),
            'user_id' => optional($request->user())->id ?? 0,
            'ip_user' => $request->ip(),
            'hostname' => gethostname() ?: null,
            'action' => mb_substr($action, 0, 300),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
