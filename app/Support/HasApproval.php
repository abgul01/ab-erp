<?php

namespace App\Support;

use App\Models\Approval;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Makes a document approvable.
 *
 * The status values are methods rather than constants because not every table
 * spells its states the same way — a Work Order stores an integer, a Purchase
 * Order a string — and the engine must not care which.
 */
trait HasApproval
{
    public static function docType(): string
    {
        return (new static)->getTable();
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'doc_id', 'id')
            ->where('doc_type', static::docType());
    }

    public function pendingApprovals(): HasMany
    {
        return $this->approvals()->where('status', 'PENDING')->orderBy('level');
    }

    public function isFullyApproved(): bool
    {
        return $this->approvals()->exists() && ! $this->approvals()->where('status', 'PENDING')->exists();
    }

    public function submitForApproval(): void
    {
        app(ApprovalEngine::class)->submit($this);
    }

    /** Status this document takes while approvals are outstanding. */
    public function submittedStatus(): mixed
    {
        return 'SUBMITTED';
    }

    public function approvedStatus(): mixed
    {
        return 'APPROVED';
    }

    public function rejectedStatus(): mixed
    {
        return 'REJECTED';
    }

    /** Hooks — override to do more than move the status. */
    public function onFullyApproved(): void
    {
        $this->update(['status' => $this->approvedStatus()]);
    }

    public function onRejected(): void
    {
        $this->update(['status' => $this->rejectedStatus()]);
    }
}
