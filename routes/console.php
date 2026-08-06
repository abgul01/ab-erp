<?php

use App\Jobs\ContractExpiryJob;
use App\Jobs\MinStockAlertJob;
use App\Jobs\NpdAlertJob;
use App\Jobs\QuotaAlertJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Nightly operational checks.
 *
 * Both run before the working day so buyers arrive to a current picture. They
 * are queued rather than run inline: the scheduler should hand work off, not
 * hold a process open while it walks every item in the catalogue.
 *
 * Requires a worker (`php artisan queue:work`) and the scheduler
 * (`php artisan schedule:work`, or a cron entry calling `schedule:run`).
 */
Schedule::job(new QuotaAlertJob)->dailyAt('05:30')->name('cek-kuota-impor')->withoutOverlapping();
Schedule::job(new MinStockAlertJob)->dailyAt('05:45')->name('cek-stok-minimum')->withoutOverlapping();
// Proyek NPD dicek sedikit lebih siang: yang dibaca PM di pagi hari adalah
// keadaan hari ini, bukan hasil semalam.
Schedule::job(new NpdAlertJob)->dailyAt('06:00')->name('cek-proyek-npd')->withoutOverlapping();

// Kontrak yang lewat tanggal ditutup sebelum jam kerja, supaya pembeli tidak
// tertahan kesepakatan yang sebenarnya sudah berakhir.
Schedule::job(new ContractExpiryJob)->dailyAt('05:15')->name('cek-masa-kontrak')->withoutOverlapping();
