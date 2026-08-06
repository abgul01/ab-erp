<?php

namespace App\Support;

/**
 * Error-code taxonomy (LLD §10).
 *
 * Codes are `{MODULE}_{REASON}`. The module prefix says which part of the
 * system refused, and the reason suffix decides the HTTP status — so a client
 * can react to a whole class of failures (everything locked, everything
 * forbidden) without a hardcoded list of individual codes.
 *
 * This is the single place that mapping lives. Call sites keep passing plain
 * strings; what changed is that the status is no longer guessed at each one.
 */
class ErrorCodes
{
    /** Module prefix → the area that owns it. Used for docs and triage. */
    public const MODULES = [
        'PR' => 'Purchase Requisition',
        'PO' => 'Purchase Order',
        'SPO' => 'PO Subcont',
        'SUB' => 'Subcontract',
        'SUBM' => 'Master Subcont',
        'GR' => 'Goods Receipt',
        'QAS' => 'Incoming Inspection',
        'RJ' => 'GR Reject',
        'INV' => 'AP Invoice',
        'LC' => 'Landed Cost',
        'KURS' => 'Exchange Rate',
        'QUOTA' => 'Import Quota',
        'IN' => 'WMS Incoming',
        'OUT' => 'WMS Outgoing',
        'RM' => 'WMS Remaining',
        'SCRAP' => 'Scrap RM',
        'KANBAN' => 'Kanban Issue',
        'SERIAL' => 'Serial',
        'FG' => 'Finished Goods',
        'FGO' => 'FG Outgoing',
        'GS' => 'General Store',
        'WO' => 'Work Order',
        'CUT' => 'MES Cutting',
        'PRO' => 'MES Processing',
        'KPL' => 'MES Pallet',
        'DT' => 'MES Downtime',
        'AB' => 'MES Abnormal',
        'FCS' => 'Final Check Sheet',
        'MPP' => 'MPP',
        'MPS' => 'MPS',
        'RESCHED' => 'MPS Reschedule',
        'RT' => 'Routing / Cycle Time',
        'BOM' => 'Bill of Material',
        'SO' => 'Sales Order',
        'DO' => 'Delivery Order',
        'SINV' => 'Sales Invoice',
        'SR' => 'Sales Return',
        'PL' => 'Pricelist',
        'JRN' => 'Journal',
        'COA' => 'Chart of Accounts',
        'PERIOD' => 'Accounting Period',
        'PAY' => 'AP Payment',
        'REC' => 'AR Receipt',
        'APPROVAL' => 'Approval',
        'VEN' => 'Vendor Portal',
        'VALIDATION' => 'Validation',
    ];

    /**
     * Reason suffixes that are not plain unprocessable-entity failures.
     *
     * LOCKED is 423 because the document exists and the caller may well be
     * allowed to touch it — just not now. Conflating it with 422 is what makes
     * a closed accounting period look like bad input.
     */
    private const STATUS_BY_SUFFIX = [
        'LOCKED' => 423,
        'FORBIDDEN' => 403,
        'SCOPE' => 403,
        'NOT_FOUND' => 404,
        '404' => 404,
        'DUP' => 409,
        'EXISTS' => 409,
        'CONFLICT' => 409,
    ];

    /** Codes whose status does not follow from their suffix. */
    private const EXPLICIT = [
        'PERIOD_LOCKED' => 423,
        'QUOTA_EXCEEDED' => 422,
        'APPROVAL_FORBIDDEN' => 403,
        'APPROVAL_PENDING' => 409,
        'VEN_SCOPE' => 403,
        'VENDOR_SCOPE' => 403,
        'NOT_VENDOR' => 403,
    ];

    public static function statusFor(string $code): int
    {
        if (isset(self::EXPLICIT[$code])) {
            return self::EXPLICIT[$code];
        }

        foreach (self::STATUS_BY_SUFFIX as $suffix => $status) {
            if (str_ends_with($code, '_'.$suffix) || $code === $suffix) {
                return $status;
            }
        }

        return 422;
    }

    /** Owning module of a code, for logs and support triage. */
    public static function moduleOf(string $code): string
    {
        $prefix = explode('_', $code)[0];

        return self::MODULES[$prefix] ?? 'Umum';
    }

    public static function isKnownModule(string $code): bool
    {
        return isset(self::MODULES[explode('_', $code)[0]]);
    }
}
