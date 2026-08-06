<?php

namespace App\Jobs;

use App\Models\prd_mrp_main;
use App\Support\MrpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Run MRP in the background.
 *
 * The controller inserts a PROCESSING row and returns its id for the browser to
 * poll; this job fills that same row and flips it to DONE. Writing into the
 * caller's row is what keeps the id the page is polling valid.
 */
class RunMrpJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $periods
     */
    public function __construct(
        public array $periods,
        public ?int $mrpMainId = null,
        public ?int $userId = null,
    ) {}

    public function handle(MrpService $mrp): void
    {
        $main = $this->mrpMainId
            ? $mrp->runInto($this->mrpMainId, $this->periods)
            : $mrp->run($this->periods, $this->userId);

        Log::info('MRP selesai', [
            'run_id' => $main->id,
            'periods' => $this->periods,
            'rows' => $main->detail()->count(),
        ]);
    }

    /** A failed run must not sit at PROCESSING forever. */
    public function failed(\Throwable $e): void
    {
        if ($this->mrpMainId) {
            prd_mrp_main::where('id', $this->mrpMainId)->update(['status' => 'FAILED']);
        }

        Log::error('MRP gagal', ['run_id' => $this->mrpMainId, 'error' => $e->getMessage()]);
    }
}
