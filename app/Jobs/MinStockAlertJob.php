<?php

namespace App\Jobs;

use App\Support\AlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Warn when material drops below its minimum, counting what is already on
 * order — a shortage a purchase order already covers is not one worth raising.
 */
class MinStockAlertJob implements ShouldQueue
{
    use Queueable;

    public function handle(AlertService $alerts): void
    {
        $n = $alerts->checkMinStock();
        Log::info('Cek stok minimum selesai', ['peringatan_terbuka' => $n]);
    }
}
