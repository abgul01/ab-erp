<?php

namespace App\Jobs;

use App\Support\AlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Warn before an import quota runs out.
 *
 * A PO that exceeds the quota is refused outright, and by then there is nothing
 * the buyer can do about the shipment. This runs ahead of that moment.
 */
class QuotaAlertJob implements ShouldQueue
{
    use Queueable;

    public function handle(AlertService $alerts): void
    {
        $n = $alerts->checkQuota();
        Log::info('Cek kuota impor selesai', ['peringatan_terbuka' => $n]);
    }
}
