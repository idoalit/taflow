<?php

namespace App\Enums;

enum ProposalStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case NeedsRevision = 'needs_revision';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Human readable label, in Bahasa Indonesia, as shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Diajukan',
            self::UnderReview => 'Sedang Ditinjau',
            self::NeedsRevision => 'Perlu Revisi',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }
}
