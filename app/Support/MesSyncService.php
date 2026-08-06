<?php

namespace App\Support;

use App\Models\mes_oplog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * MES offline sync — replays operations a terminal buffered while it had no
 * network. Each queued item is the original API call (method + url + payload),
 * so it is dispatched back through the router and hits the very same controller,
 * validation and permission middleware as an online scan. None of the business
 * rules are duplicated here.
 *
 * Idempotency: mes_oplog.client_uuid is unique. An operation already logged is
 * reported as SKIPPED with the row it produced the first time, so a terminal
 * that flushes twice (or reconnects mid-flush) never double-posts a scan.
 *
 * LLD §6.2
 */
class MesSyncService
{
    /** Only MES endpoints may be replayed — the queue is not a generic proxy. */
    private const ALLOWED_PREFIX = 'api/v1/mes/';

    /** A terminal may not hold work older than this (PRD: 24-hour offline limit). */
    public const MAX_OFFLINE_HOURS = 24;

    /**
     * @param  array<int,array>  $ops  each: {client_uuid, method, url, data, client_at}
     * @return array{processed:int, skipped:int, failed:int, results:array}
     */
    public function processBatch(array $ops, Request $origin): array
    {
        $results = [];
        $tally = ['processed' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($ops as $op) {
            $uuid = $op['client_uuid'] ?? null;

            if ($existing = mes_oplog::where('client_uuid', $uuid)->first()) {
                $results[] = [
                    'client_uuid' => $uuid,
                    'status' => 'SKIPPED',
                    'row_id' => $existing->row_id,
                ];
                $tally['skipped']++;

                continue;
            }

            $outcome = $this->replay($op, $origin);

            // A successful replay was already logged by the client-uuid
            // middleware on the way out; only the rejects still need a row.
            if ($outcome['status'] !== 'OK') {
                $this->log($op, $outcome, $origin);
            }

            $results[] = ['client_uuid' => $uuid] + $outcome;
            $tally[$outcome['status'] === 'OK' ? 'processed' : 'failed']++;
        }

        return $tally + ['results' => $results];
    }

    /**
     * Re-issue one buffered call against the running application.
     *
     * @return array{status:string, code:int, row_id:?int, data:mixed, error:?string}
     */
    private function replay(array $op, Request $origin): array
    {
        $url = '/'.ltrim((string) ($op['url'] ?? ''), '/');
        $method = strtoupper($op['method'] ?? 'POST');

        if (! str_starts_with(ltrim($url, '/'), self::ALLOWED_PREFIX)) {
            return $this->fail(422, "Endpoint {$url} tidak boleh disinkronkan dari antrean offline.");
        }

        if ($this->tooOld($op)) {
            return $this->fail(422, 'Operasi lebih tua dari '.self::MAX_OFFLINE_HOURS.' jam dan ditolak.');
        }

        $payload = (array) ($op['data'] ?? []);
        $payload['client_uuid'] = $op['client_uuid'];

        $sub = Request::create($url, $method, $payload);
        $sub->headers->replace($origin->headers->all());
        $sub->headers->set('Accept', 'application/json');
        $sub->setUserResolver(fn () => $origin->user());

        // Controllers type-hint Request, which is resolved from the container —
        // it has to point at the replayed call for the duration of the dispatch.
        $previous = app('request');
        app()->instance('request', $sub);

        try {
            $response = Route::dispatch($sub);
            $body = json_decode($response->getContent(), true);
            $code = $response->getStatusCode();

            if ($code >= 400) {
                return $this->fail($code, $body['errors'][0]['message'] ?? $body['error'] ?? 'Sync ditolak server.');
            }

            $data = $body['data'] ?? null;

            return [
                'status' => 'OK',
                'code' => $code,
                'row_id' => is_array($data) ? ($data['id'] ?? null) : null,
                'data' => $data,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return $this->fail(500, $e->getMessage());
        } finally {
            app()->instance('request', $previous);
        }
    }

    private function tooOld(array $op): bool
    {
        $at = $op['client_at'] ?? null;

        return $at && now()->diffInHours($at, true) > self::MAX_OFFLINE_HOURS;
    }

    private function fail(int $code, string $message): array
    {
        return ['status' => 'ERROR', 'code' => $code, 'row_id' => null, 'data' => null, 'error' => $message];
    }

    private function log(array $op, array $outcome, Request $origin): void
    {
        mes_oplog::create([
            'client_uuid' => $op['client_uuid'],
            'type' => $op['type'] ?? 'replay',
            'method' => strtoupper($op['method'] ?? 'POST'),
            'url' => $op['url'] ?? null,
            'user_id' => $origin->user()?->id,
            'row_id' => $outcome['row_id'],
            'payload' => $op['data'] ?? [],
            'status' => $outcome['status'],
            'error' => $outcome['error'] ? mb_substr($outcome['error'], 0, 200) : null,
            'client_at' => $op['client_at'] ?? null,
        ]);
    }

    /** Operations that failed on replay — the terminal shows these for manual fixing. */
    public function failedOps(int $limit = 100)
    {
        return mes_oplog::where('status', 'ERROR')->latest('id')->limit($limit)->get();
    }

    /** Housekeeping: the log only needs to outlive the offline window. */
    public function clearOld(int $days = 30): int
    {
        return mes_oplog::where('created_at', '<', now()->subDays($days))->delete();
    }
}
