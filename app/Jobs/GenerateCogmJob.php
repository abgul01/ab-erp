<?php

namespace App\Jobs;

use App\Models\cst_cogm;
use App\Models\prd_wo_main;
use App\Support\CostingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * COGM roll-up for a period, in the background.
 *
 * Every Work Order in the period is costed from its own material, labour, FOH
 * and subcontract records, so the work grows with production volume rather than
 * with the size of the request.
 */
class GenerateCogmJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $period, public ?int $userId = null) {}

    public function handle(CostingService $svc): void
    {
        $wos = prd_wo_main::whereIn('status', [2, 3])
            ->whereRaw("DATE_FORMAT(updated_at, '%Y%m') <= ?", [$this->period])
            ->get();

        $count = DB::transaction(function () use ($wos, $svc) {
            $n = 0;
            foreach ($wos as $wo) {
                cst_cogm::updateOrCreate(
                    ['period' => $this->period, 'wo_id' => $wo->id],
                    $svc->cogmForWo($wo, $this->period)
                );
                $n++;
            }

            return $n;
        });

        Log::info('COGM selesai', ['period' => $this->period, 'wo' => $count, 'user_id' => $this->userId]);
    }
}
