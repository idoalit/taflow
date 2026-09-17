<?php

namespace App\Concerns;

use App\Enums\ApprovalStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared two-stage verification behaviour for proposal and research exam
 * requests: a supervisor signs off first, then the program admin. The overall
 * status only settles once both gates have answered.
 *
 * @property ApprovalStatus $status
 * @property ApprovalStatus $supervisor_status
 * @property ApprovalStatus $admin_status
 */
trait VerifiesExamRequest
{
    /** @return BelongsTo<User, $this> */
    public function supervisorVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_verified_by');
    }

    /** @return BelongsTo<User, $this> */
    public function adminVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_verified_by');
    }

    public function isPending(): bool
    {
        return $this->status === ApprovalStatus::Pending;
    }

    /**
     * Recompute the overall status from the two verification gates. A rejection
     * at either gate settles the request immediately; approval needs both.
     */
    public function syncOverallStatus(): void
    {
        $this->status = match (true) {
            $this->supervisor_status === ApprovalStatus::Rejected,
            $this->admin_status === ApprovalStatus::Rejected => ApprovalStatus::Rejected,
            $this->supervisor_status === ApprovalStatus::Approved
                && $this->admin_status === ApprovalStatus::Approved => ApprovalStatus::Approved,
            default => ApprovalStatus::Pending,
        };
    }

    /**
     * The gate the request is currently waiting on, for display in the UI.
     */
    public function awaitingLabel(): string
    {
        return match (true) {
            $this->status === ApprovalStatus::Rejected => 'Ditolak',
            $this->status === ApprovalStatus::Approved => 'Disetujui',
            $this->supervisor_status === ApprovalStatus::Pending => 'Menunggu verifikasi dosen pembimbing',
            default => 'Menunggu verifikasi admin prodi',
        };
    }
}
