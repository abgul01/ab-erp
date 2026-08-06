<?php

namespace App\Jobs;

use App\Support\CrpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Capacity planning in the background.
 *
 * A CRP run walks every approved MPS line through its routing; with a full
 * order book that is thousands of rows and will outlive an HTTP request.
 */
class RunCrpJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $period, public ?int $userId = null) {}

    public function handle(CrpService $crp): void
    {
        $result = $crp->run($this->period);

        Log::info('CRP selesai', [
            'period' => $this->period,
            'run_id' => $result['run_id'] ?? null,
            'rows' => count($result['loads'] ?? []),
            'user_id' => $this->userId,
        ]);
    }
}
