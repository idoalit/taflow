<?php

namespace App\Enums;

enum GuidanceStage: string
{
    case Proposal = 'proposal';
    case Research = 'research';

    /**
     * Human readable label, in Bahasa Indonesia, as shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Proposal => 'Bimbingan Proposal',
            self::Research => 'Bimbingan Hasil Penelitian',
        };
    }
}
