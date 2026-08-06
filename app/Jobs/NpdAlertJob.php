<?php

namespace App\Jobs;

use App\Support\AlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Dorong proyek pengembangan yang diam.
 *
 * Proyek NPD berjalan berbulan-bulan dan tidak ada yang membuka daftarnya
 * setiap hari — task yang lewat tanggal, gate yang menunggu tanda tangan, dan
 * target SOP yang terlampaui semuanya berlalu tanpa disadari sampai pelanggan
 * yang menanyakannya.
 */
class NpdAlertJob implements ShouldQueue
{
    use Queueable;

    public function handle(AlertService $alerts): void
    {
        $n = $alerts->checkNpd();
        Log::info('Cek proyek NPD selesai', ['peringatan_terbuka' => $n]);
    }
}
