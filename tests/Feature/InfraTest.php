<?php

use App\Exceptions\BizException;
use App\Jobs\GenerateCogmJob;
use App\Jobs\RunCrpJob;
use App\Models\prd_wo_serial_rm;
use App\Support\ErrorCodes;
use App\Support\SerialService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

/**
 * Cross-cutting guarantees: an error code carries its own HTTP status, a bar
 * cannot be consumed twice from stale state, and the long computations do not
 * run inside a request.
 */
it('derives the HTTP status from the code suffix', function () {
    expect(ErrorCodes::statusFor('PO_LOCKED'))->toBe(423)
        ->and(ErrorCodes::statusFor('CUT_SERIAL_DUP'))->toBe(409)
        ->and(ErrorCodes::statusFor('VEN_SCOPE'))->toBe(403)
        ->and(ErrorCodes::statusFor('DT_404'))->toBe(404)
        ->and(ErrorCodes::statusFor('SO_EMPTY'))->toBe(422);        // the ordinary case
});

it('lets an explicit status win over the convention', function () {
    expect(BizException::make('SO_EMPTY', 'x')->status)->toBe(422)
        ->and(BizException::make('SO_EMPTY', 'x', 409)->status)->toBe(409);
});

it('names the module that refused', function () {
    expect(BizException::make('CUT_SERIAL', 'x')->module())->toBe('MES Cutting')
        ->and(BizException::make('LC_NO_PO', 'x')->module())->toBe('Landed Cost')
        ->and(BizException::make('XYZ_UNKNOWN', 'x')->module())->toBe('Umum');
});

it('refuses a consume built on a stale read of the serial', function () {
    $f = scrapFixture(6000);
    $svc = app(SerialService::class);

    // Two terminals read the same row, then both write.
    $first = prd_wo_serial_rm::with('detail')->find($f['serial']);
    $stale = prd_wo_serial_rm::with('detail')->find($f['serial']);

    $svc->consume($first, 1000);

    expect(fn () => $svc->consume($stale, 1000))
        ->toThrow(BizException::class);

    // Only the first write landed: 6000 − 1000, not 6000 − 2000.
    expect((float) prd_wo_serial_rm::find($f['serial'])->length_rem)->toBe(5000.0);
});

it('bumps the version on every successful consume', function () {
    $f = scrapFixture(6000);
    $svc = app(SerialService::class);

    $svc->consume(prd_wo_serial_rm::with('detail')->find($f['serial']), 500);
    $svc->consume(prd_wo_serial_rm::with('detail')->find($f['serial']), 500);

    expect((int) prd_wo_serial_rm::find($f['serial'])->version)->toBe(2);
});

it('queues CRP instead of computing it inside the request', function () {
    Queue::fake();
    Sanctum::actingAs(admin());

    $this->postJson('/api/v1/crp/run', ['period' => '202607'])
        ->assertOk()
        ->assertJsonPath('data.queued', true);

    Queue::assertPushed(RunCrpJob::class);
});

it('queues the COGM roll-up', function () {
    Queue::fake();
    Sanctum::actingAs(admin());

    /*
     * Costing posts into a period, so it has to be one that is open. Hardcoding
     * the demo month made this test fail the moment the seed closed its books —
     * a failure about the calendar, not about the queue.
     */
    $period = DB::table('acc_period')->where('status', 'OPEN')->orderBy('period')->value('period');

    $this->postJson('/api/v1/cogm/run', ['period' => $period])
        ->assertOk()
        ->assertJsonStructure(['data' => ['period', 'queued_wo', 'message']]);

    Queue::assertPushed(GenerateCogmJob::class);
});

it('still runs CRP inline when the planner asks for it', function () {
    Queue::fake();
    Sanctum::actingAs(admin());

    $this->postJson('/api/v1/crp/run', ['period' => '202607', 'sync' => true])
        ->assertOk()
        ->assertJsonStructure(['data' => ['run_id', 'loads']]);

    Queue::assertNothingPushed();
});
