<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\sys_alert;
use App\Support\AlertService;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

/**
 * Operational alerts: import quota running low, stock under its minimum.
 *
 * LLD §2.2
 */
class AlertController extends Controller
{
    public function __construct(private AlertService $svc) {}

    public function index(Request $request)
    {
        $open = $this->svc->open($request->query('type'));

        return ApiResponse::item([
            'open' => $open->count(),
            'critical' => $open->where('severity', 'CRITICAL')->count(),
            'items' => $open->values(),
        ]);
    }

    /** Run the checks now rather than waiting for the nightly schedule. */
    public function refresh(Request $request)
    {
        $quota = $this->svc->checkQuota();
        $stock = $this->svc->checkMinStock();
        $npd = $this->svc->checkNpd();

        AuditLogger::record($request, "Cek peringatan: {$quota} kuota, {$stock} stok minimum, {$npd} proyek NPD");

        return ApiResponse::item([
            'quota' => $quota,
            'min_stock' => $stock,
            'npd' => $npd,
            'message' => "Pemeriksaan selesai: {$quota} peringatan kuota, {$stock} peringatan stok minimum, {$npd} peringatan proyek NPD.",
        ]);
    }

    /**
     * Close an alert by hand.
     *
     * Conditions that have genuinely cleared close themselves on the next
     * check; this is for the ones a human has decided to accept.
     */
    public function resolve(Request $request, int $id)
    {
        $alert = sys_alert::findOrFail($id);
        $alert->update(['resolved_at' => now(), 'resolved_by' => $request->user()->id]);
        AuditLogger::record($request, "Tutup peringatan #{$id}: {$alert->title}");

        return ApiResponse::item($alert->fresh());
    }
}
