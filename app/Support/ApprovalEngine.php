<?php

namespace App\Support;

use App\Exceptions\BizException;
use App\Models\Approval;
use App\Models\ast_main;
use App\Models\eng_ecn_main;
use App\Models\m_bom;
use App\Models\m_contacts;
use App\Models\m_item;
use App\Models\m_pricelist_main;
use App\Models\m_process_main;
use App\Models\m_quota;
use App\Models\npd_cost_main;
use App\Models\npd_project_phase;
use App\Models\prc_po_main;
use App\Models\prc_pr_main;
use App\Models\prd_mpp;
use App\Models\prd_mps;
use App\Models\prd_mps_resched;
use App\Models\prd_wo_main;
use App\Models\sls_forecast;
use App\Models\sls_so_main;
use App\Models\sub_po_main;
use App\Models\User;
use App\Models\whs_po_main;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Multi-level paperless approval engine (LLD §4.2).
 *
 * Flow definition per doc_type:
 *   submit(doc)       → generate approval rows per level (PENDING)
 *   approve(doc,user) → mark current level APPROVED; if last → doc.onFullyApproved()
 *   reject(doc,user)  → mark current+remaining REJECTED; doc.onRejected()
 */
class ApprovalEngine
{
    /**
     * Define approval levels per doc_type.
     * Each level: [ 'role' => permission_key, 'label' => string ]
     *
     * Override by publishing config or extending the method.
     */
    /**
     * Every approvable document: its model, the menu each level is authorised
     * against, and a human label.
     *
     * Keeping the model beside the flow is what stops the two from drifting —
     * a document used to be able to appear in a flow while having no entry in
     * the resolver, and it would then silently vanish from the approval inbox.
     *
     * `role` is a menu link, because that is what User::canDo() checks; an
     * approver is someone with edit rights on the document's own menu.
     */
    public function registry(): array
    {
        return [
            // Procurement & sales
            'prc_pr_main' => ['model' => prc_pr_main::class, 'label' => 'Purchase Requisition',
                'levels' => [['role' => 'pr', 'label' => 'Purchasing Supervisor']]],
            'prc_po_main' => ['model' => prc_po_main::class, 'label' => 'Purchase Order',
                'levels' => [['role' => 'pr', 'label' => 'Purchasing Supervisor'], ['role' => 'po', 'label' => 'Purchasing Manager']]],
            'sub_po_main' => ['model' => sub_po_main::class, 'label' => 'PO Subcont',
                'levels' => [['role' => 'subcont-po', 'label' => 'Purchasing Manager']]],
            // Pembelian perkakas & sparepart: satu tanda tangan sudah cukup,
            // nilainya kecil dan kebutuhannya biasanya mendesak.
            'whs_po_main' => ['model' => whs_po_main::class, 'label' => 'PO WHS (Tools & Sparepart)',
                'levels' => [['role' => 'whs-po', 'label' => 'Purchasing Supervisor']]],
            'sls_so_main' => ['model' => sls_so_main::class, 'label' => 'Sales Order',
                'levels' => [['role' => 'sales-orders', 'label' => 'Sales Manager']]],
            'm_pricelist_main' => ['model' => m_pricelist_main::class, 'label' => 'Pricelist',
                'levels' => [['role' => 'pricelists', 'label' => 'Sales Manager']]],

            // Planning & production
            'prd_mpp' => ['model' => prd_mpp::class, 'label' => 'MPP',
                'levels' => [['role' => 'mpp', 'label' => 'PPIC Supervisor']]],
            'prd_mps' => ['model' => prd_mps::class, 'label' => 'MPS',
                'levels' => [['role' => 'mps', 'label' => 'PPIC Manager']]],
            'prd_mps_resched' => ['model' => prd_mps_resched::class, 'label' => 'Reschedule MPS',
                'levels' => [['role' => 'mps-approvals', 'label' => 'PPIC Approver']]],
            'prd_wo_main' => ['model' => prd_wo_main::class, 'label' => 'Work Order',
                'levels' => [['role' => 'work-orders', 'label' => 'Production Manager']]],

            // Engineering masters
            'm_item' => ['model' => m_item::class, 'label' => 'Item Master',
                'levels' => [['role' => 'items', 'label' => 'Engineering Manager']]],
            'm_bom' => ['model' => m_bom::class, 'label' => 'Bill of Material',
                'levels' => [['role' => 'items', 'label' => 'Engineering Manager']]],
            'm_process_main' => ['model' => m_process_main::class, 'label' => 'Master Routing (WOS)',
                'levels' => [['role' => 'process-mains', 'label' => 'Engineering Manager']]],
            /*
             * An ECN changes a part that production is already building, so it
             * is signed off twice: engineering agrees the change is right, PPIC
             * agrees the factory can absorb it.
             */
            'eng_ecn_main' => ['model' => eng_ecn_main::class, 'label' => 'ECN (Perubahan Teknik)',
                'levels' => [
                    ['role' => 'ecn', 'label' => 'Engineering Manager'],
                    ['role' => 'mps', 'label' => 'PPIC Manager'],
                ]],

            /*
             * Gate NPD. Dua level untuk semua gate (keputusan 4 Agustus 2026):
             * pengaju dari sisi proyek/engineering, lalu SPV yang juga memegang
             * kewenangan handover — supaya yang mengizinkan proyek naik fase
             * adalah orang yang sama yang nanti menerima hasilnya.
             */
            'npd_project_phase' => ['model' => npd_project_phase::class, 'label' => 'Gate Fase NPD',
                'levels' => [
                    ['role' => 'npd-projects', 'label' => 'Project Manager / Engineering'],
                    ['role' => 'npd-handover', 'label' => 'Supervisor'],
                ]],
            // Quotation: harga yang keluar ke pelanggan disetujui dua tangan —
            // yang menyusun biayanya, lalu yang bertanggung jawab atas marginnya.
            'npd_cost_main' => ['model' => npd_cost_main::class, 'label' => 'Quotation NPD',
                'levels' => [
                    ['role' => 'npd-costing', 'label' => 'Costing / Procurement'],
                    ['role' => 'npd-handover', 'label' => 'Supervisor'],
                ]],

            // Commercial masters
            'm_contacts' => ['model' => m_contacts::class, 'label' => 'Vendor / Customer',
                'levels' => [['role' => 'contacts', 'label' => 'Manager']]],
            'sls_forecast' => ['model' => sls_forecast::class, 'label' => 'Forecast',
                'levels' => [['role' => 'forecasts', 'label' => 'Sales Manager']]],
            'm_quota' => ['model' => m_quota::class, 'label' => 'Kuota Impor',
                'levels' => [['role' => 'quotas', 'label' => 'Import Manager']]],

            // Asset
            'ast_main' => ['model' => ast_main::class, 'label' => 'Fixed Asset',
                'levels' => [['role' => 'assets', 'label' => 'Finance Manager']]],
        ];
    }

    public function flows(): array
    {
        return array_map(fn ($d) => $d['levels'], $this->registry());
    }

    /** Human label for a doc type, for the approval inbox. */
    public function labelFor(string $docType): string
    {
        return $this->registry()[$docType]['label'] ?? $docType;
    }

    public function flowFor(Model $doc): array
    {
        $table = $doc->getTable();

        return $this->flows()[$table] ?? [
            ['role' => "{$table}.approve", 'label' => 'Approver'],
        ];
    }

    /**
     * Submit a document for approval — generate approval rows.
     */
    public function submit(Model $doc): void
    {
        $table = $doc->getTable();
        $flow = $this->flowFor($doc);

        if (Approval::where('doc_type', $table)->where('doc_id', $doc->id)->where('status', 'PENDING')->exists()) {
            throw new BizException('APPROVAL_PENDING', 'Dokumen ini sudah menunggu persetujuan.');
        }

        DB::transaction(function () use ($doc, $table, $flow) {
            foreach ($flow as $i => $level) {
                Approval::create([
                    'doc_type' => $table,
                    'doc_id' => $doc->id,
                    'level' => $i + 1,
                    'required_role' => $level['role'],
                    'status' => 'PENDING',
                ]);
            }
            $doc->update(['status' => $doc->submittedStatus()]);
        });
    }

    /**
     * Approve the current PENDING level. Returns true if fully approved.
     */
    public function approve(Model $doc, User $user, ?string $note = null): bool
    {
        $table = $doc->getTable();

        return DB::transaction(function () use ($doc, $table, $user, $note) {
            $pending = Approval::where('doc_type', $table)
                ->where('doc_id', $doc->id)
                ->where('status', 'PENDING')
                ->orderBy('level')
                ->first();

            if (! $pending) {
                throw new BizException('APPROVAL_NOT_PENDING', 'Tidak ada approval yang menunggu.');
            }

            if (! $user->canDo($pending->required_role, 'edit')) {
                throw new BizException(
                    'APPROVAL_FORBIDDEN',
                    'Anda tidak memiliki wewenang untuk level ini.'
                );
            }

            $pending->update([
                'status' => 'APPROVED',
                'acted_by' => $user->id,
                'acted_at' => now(),
                'note' => $note,
            ]);

            $isLast = Approval::where('doc_type', $table)
                ->where('doc_id', $doc->id)
                ->where('status', 'PENDING')
                ->doesntExist();

            if ($isLast) {
                $doc->onFullyApproved();
                AuditLogger::record(
                    request() ?? null,
                    "Approve {$table}#{$doc->id}: fully approved",
                    $doc->code ?? $doc->id
                );
            }

            return $isLast;
        });
    }

    /**
     * Reject the document — cancel all remaining levels.
     */
    public function reject(Model $doc, User $user, string $note): void
    {
        $table = $doc->getTable();

        DB::transaction(function () use ($doc, $table, $user, $note) {
            $pending = Approval::where('doc_type', $table)
                ->where('doc_id', $doc->id)
                ->where('status', 'PENDING')
                ->orderBy('level')
                ->first();

            if (! $pending) {
                throw new BizException('APPROVAL_NOT_PENDING', 'Tidak ada approval yang menunggu.');
            }

            if (! $user->canDo($pending->required_role, 'edit')) {
                throw new BizException(
                    'APPROVAL_FORBIDDEN',
                    'Anda tidak memiliki wewenang untuk level ini.'
                );
            }

            $pending->update([
                'status' => 'REJECTED',
                'acted_by' => $user->id,
                'acted_at' => now(),
                'note' => $note,
            ]);

            // Skip remaining
            Approval::where('doc_type', $table)
                ->where('doc_id', $doc->id)
                ->where('status', 'PENDING')
                ->update(['status' => 'SKIPPED']);

            $doc->onRejected();
            AuditLogger::record(
                request() ?? null,
                "Reject {$table}#{$doc->id}: {$note}",
                $doc->code ?? $doc->id
            );
        });
    }

    /**
     * All approvals pending action by this user (via their permissions).
     */
    public function pendingFor(User $user): array
    {
        $permIds = $user->permissions()
            ->where('can_edit', 1)
            ->with('menu')
            ->get()
            ->pluck('menu.link')
            ->all();

        if ($user->isSuperAdmin()) {
            $rows = Approval::with('actor')
                ->where('status', 'PENDING')
                ->orderByDesc('id')
                ->get();
        } else {
            $rows = Approval::with('actor')
                ->where('status', 'PENDING')
                ->whereIn('required_role', $permIds)
                ->orderByDesc('id')
                ->get();
        }

        $results = [];
        foreach ($rows as $a) {
            $doc = $this->resolveDoc($a->doc_type, $a->doc_id);
            if (! $doc) {
                continue;
            }
            $allLevels = Approval::where('doc_type', $a->doc_type)
                ->where('doc_id', $a->doc_id)
                ->orderBy('level')
                ->get();
            $results[] = [
                'approval' => $a,
                'approvals' => $allLevels,
                'doc' => $doc,
                'doc_type_label' => $this->labelFor($a->doc_type),
                'doc_label' => $doc->code ?? "{$a->doc_type}#{$a->doc_id}",
                'doc_status' => $doc->status ?? '?',
            ];
        }

        return $results;
    }

    /**
     * Resolve the underlying document model from doc_type table name.
     */
    public function resolveDoc(string $docType, int $docId): ?Model
    {
        $class = $this->registry()[$docType]['model'] ?? null;

        if (! $class || ! class_exists($class)) {
            return null;
        }

        return $class::find($docId);
    }
}
