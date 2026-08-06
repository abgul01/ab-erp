<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Exceptions\BizException;
use App\Http\Controllers\Controller;
use App\Models\prc_po_main;
use App\Models\prc_po_schedule;
use App\Models\sub_dn_main;
use App\Models\sub_po_detail;
use App\Models\sub_po_main;
use App\Models\sub_progress;
use App\Support\ApiResponse;
use App\Support\AuditLogger;
use Illuminate\Http\Request;

/**
 * Supplier portal — the only surface a vendor account can reach.
 *
 * Every query is scoped to the ven_id the guard put on the request, never to a
 * value from the payload, so one supplier can never read or confirm another's
 * documents.
 *
 * PRD §4.7, LLD §9
 */
class VendorPortalController extends Controller
{
    private function venId(Request $request): int
    {
        return (int) $request->attributes->get('ven_id');
    }

    /** Who the portal user is and which supplier they act for. */
    public function me(Request $request)
    {
        $user = $request->user()->load('ven');

        return ApiResponse::item([
            'name' => $user->name,
            'username' => $user->username,
            'vendor' => [
                'id' => $user->ven?->id,
                'code' => $user->ven?->u_code,
                'company' => $user->ven?->company_n,
                'npwp' => $user->ven?->npwp,
            ],
        ]);
    }

    /** Purchase orders addressed to this supplier, general and subcontract. */
    public function purchaseOrders(Request $request)
    {
        $ven = $this->venId($request);

        return ApiResponse::item([
            'purchase' => prc_po_main::with('detail.item')
                ->where('ven_id', $ven)->orderByDesc('date')->limit(200)->get(),
            'subcontract' => sub_po_main::with('detail.item')
                ->where('ven_id', $ven)->orderByDesc('date')->limit(200)->get(),
        ]);
    }

    /** Delivery schedule lines the supplier is asked to commit to. */
    public function schedules(Request $request)
    {
        $rows = prc_po_schedule::with('detail.main')
            ->whereHas('detail.main', fn ($q) => $q->where('ven_id', $this->venId($request)))
            ->orderBy('plan_date')
            ->get();

        return ApiResponse::collection($rows);
    }

    /**
     * Supplier confirms a delivery date — optionally proposing a different one,
     * which is what buyers use to spot slipping lines.
     */
    public function confirmSchedule(Request $request, int $id)
    {
        $data = $request->validate([
            'plan_date' => ['nullable', 'date'],
            'qty' => ['nullable', 'integer', 'min:1'],
        ]);

        $schedule = prc_po_schedule::with('detail.main')->findOrFail($id);

        if ((int) ($schedule->detail?->main?->ven_id) !== $this->venId($request)) {
            throw BizException::make('VEN_SCOPE', 'Jadwal ini bukan milik vendor Anda.', 403);
        }

        $schedule->update(array_filter([
            'plan_date' => $data['plan_date'] ?? null,
            'qty' => $data['qty'] ?? null,
        ]) + ['confirmed_at' => now()]);

        AuditLogger::record($request, "Vendor konfirmasi jadwal kirim #{$id}");

        return ApiResponse::item($schedule->fresh());
    }

    /**
     * Supplier reports how far along a subcontract line is.
     *
     * Reports are appended rather than overwritten so the plant can see whether
     * a line has been stuck at the same percentage for a week.
     */
    public function reportProgress(Request $request)
    {
        $data = $request->validate([
            'po_detail_id' => ['required', 'integer', 'exists:sub_po_detail,id'],
            'progress_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $line = sub_po_detail::with('main')->findOrFail($data['po_detail_id']);
        if ((int) ($line->main?->ven_id) !== $this->venId($request)) {
            throw BizException::make('VEN_SCOPE', 'Baris PO ini bukan milik vendor Anda.', 403);
        }

        $progress = sub_progress::create([
            'po_detail_id' => $line->id,
            'progress_pct' => $data['progress_pct'],
            'note' => $data['note'] ?? null,
            'user_id' => $request->user()->id,
            'reported_at' => now(),
        ]);

        AuditLogger::record($request, "Vendor lapor progres baris PO subcont #{$line->id}: {$data['progress_pct']}%");

        return ApiResponse::item($progress, 201);
    }

    /** Progress this supplier has already reported. */
    public function progress(Request $request)
    {
        $rows = sub_progress::with('detail.item')
            ->whereHas('detail.main', fn ($q) => $q->where('ven_id', $this->venId($request)))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return ApiResponse::collection($rows);
    }

    /** Subcontract material the plant has sent out to this supplier. */
    public function deliveryNotes(Request $request)
    {
        $rows = sub_dn_main::with(['detail', 'po'])
            ->where('ven_id', $this->venId($request))
            ->orderByDesc('date')->limit(200)->get();

        return ApiResponse::collection($rows);
    }
}
