<?php

namespace App\Http\Middleware;

use App\Models\mes_oplog;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * Idempotency guard for MES scan endpoints.
 *
 * A terminal stamps every write with a uuid it generates locally, so the same
 * scan replayed after a flaky connection (or flushed twice from the offline
 * queue) is recognised instead of posted again. Requests without a uuid pass
 * straight through — the desktop UI does not need this.
 *
 * The log is written here, after the call succeeded, which is what makes the
 * guard work for online scans too and not only for the offline flush.
 *
 * LLD §6.2
 */
class CheckClientUuid
{
    public function handle(Request $request, Closure $next)
    {
        $uuid = $request->input('client_uuid');
        if (! $uuid) {
            return $next($request);
        }

        if ($seen = mes_oplog::where('client_uuid', $uuid)->first()) {
            return ApiResponse::conflict("Operasi {$uuid} sudah pernah diproses.", [
                'client_uuid' => $uuid,
                'existing_row_id' => $seen->row_id,
                'type' => $seen->type,
            ]);
        }

        $response = $next($request);

        if ($response->getStatusCode() < 400) {
            $this->record($request, $uuid, $response);
        }

        return $response;
    }

    private function record(Request $request, string $uuid, $response): void
    {
        $body = json_decode($response->getContent(), true);
        $data = $body['data'] ?? null;

        try {
            mes_oplog::create([
                'client_uuid' => $uuid,
                'type' => $request->route()?->uri() ?? 'mes',
                'method' => $request->method(),
                'url' => $request->path(),
                'user_id' => $request->user()?->id,
                'row_id' => is_array($data) ? ($data['id'] ?? null) : null,
                'payload' => $request->except(['client_uuid', 'password']),
                'status' => 'OK',
            ]);
        } catch (QueryException $e) {
            // Two terminals raced on the same uuid; the unique index already
            // guaranteed only one write happened. Nothing left to do.
        }
    }
}
