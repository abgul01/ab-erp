<?php

use App\Models\mes_oplog;
use App\Support\MesSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * The offline queue replays whole API calls. What matters is that a scan sent
 * twice lands once, that a rejected scan is reported rather than swallowed, and
 * that the queue cannot be used to reach anything outside the MES endpoints.
 */
function syncReq(): Request
{
    $r = Request::create('/api/v1/mes/sync', 'POST');
    $r->setUserResolver(fn () => admin());

    return $r;
}

/** A throwaway MES route, so the test does not depend on shop-floor fixtures. */
beforeEach(function () {
    Route::middleware(['client-uuid'])->post('api/v1/mes/_probe', function (Request $r) {
        $id = DB::table('mes_oplog')->insertGetId([
            'client_uuid' => 'probe-'.uniqid(), 'type' => 'probe', 'method' => 'POST',
            'status' => 'OK', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(['data' => ['id' => $id, 'echo' => $r->input('echo')]]);
    });
});

it('replays a queued operation through the real route', function () {
    $uuid = (string) Str::uuid();

    $result = (new MesSyncService)->processBatch([
        ['client_uuid' => $uuid, 'method' => 'POST', 'url' => 'api/v1/mes/_probe', 'data' => ['echo' => 'halo']],
    ], syncReq());

    expect($result['processed'])->toBe(1)
        ->and($result['failed'])->toBe(0)
        ->and($result['results'][0]['data']['echo'])->toBe('halo')
        ->and(mes_oplog::where('client_uuid', $uuid)->exists())->toBeTrue();
});

it('treats a second delivery of the same uuid as already done', function () {
    $uuid = (string) Str::uuid();
    $op = ['client_uuid' => $uuid, 'method' => 'POST', 'url' => 'api/v1/mes/_probe', 'data' => ['echo' => 'x']];
    $svc = new MesSyncService;

    $svc->processBatch([$op], syncReq());
    $second = $svc->processBatch([$op], syncReq());

    expect($second['skipped'])->toBe(1)
        ->and($second['processed'])->toBe(0)
        ->and($second['results'][0]['status'])->toBe('SKIPPED')
        ->and(mes_oplog::where('client_uuid', $uuid)->count())->toBe(1);
});

it('refuses to replay anything outside the MES endpoints', function () {
    $result = (new MesSyncService)->processBatch([
        ['client_uuid' => (string) Str::uuid(), 'method' => 'POST', 'url' => 'api/v1/journals', 'data' => []],
    ], syncReq());

    expect($result['failed'])->toBe(1)
        ->and($result['results'][0]['error'])->toContain('tidak boleh disinkronkan');
});

it('rejects work the terminal held longer than the offline window', function () {
    $result = (new MesSyncService)->processBatch([
        [
            'client_uuid' => (string) Str::uuid(),
            'method' => 'POST',
            'url' => 'api/v1/mes/_probe',
            'data' => [],
            'client_at' => now()->subHours(30)->toIso8601String(),
        ],
    ], syncReq());

    expect($result['failed'])->toBe(1)
        ->and($result['results'][0]['error'])->toContain('24 jam');
});

it('logs a rejected replay so the operator can see why', function () {
    Route::middleware(['client-uuid'])->post('api/v1/mes/_boom', fn () => response()->json(
        ['errors' => [['code' => 'NOPE', 'message' => 'Serial tidak dibooking.', 'field' => null]]], 422
    ));

    $uuid = (string) Str::uuid();
    $result = (new MesSyncService)->processBatch([
        ['client_uuid' => $uuid, 'method' => 'POST', 'url' => 'api/v1/mes/_boom', 'data' => []],
    ], syncReq());

    expect($result['failed'])->toBe(1)
        ->and(mes_oplog::where('client_uuid', $uuid)->value('status'))->toBe('ERROR')
        ->and(mes_oplog::where('client_uuid', $uuid)->value('error'))->toContain('tidak dibooking');
});
