<?php

namespace App\Jobs;

use App\Support\VendorQuotationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Tutup kontrak yang masa berlakunya habis.
 *
 * Kontrak yang tetap berstatus aktif setelah lewat tanggal akan terus menahan
 * penawaran baru menggantikan harganya — pembeli tidak bisa berpindah vendor
 * karena kesepakatan yang sebenarnya sudah berakhir.
 */
class ContractExpiryJob implements ShouldQueue
{
    use Queueable;

    public function handle(VendorQuotationService $svc): void
    {
        $n = $svc->expireContracts();
        Log::info('Cek masa berlaku kontrak selesai', ['kontrak_ditutup' => $n]);
    }
}
