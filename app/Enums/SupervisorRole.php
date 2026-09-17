<?php

namespace App\Enums;

enum SupervisorRole: string
{
    case Supervisor = 'supervisor';
    case Examiner = 'examiner';

    /**
     * Human readable label, in Bahasa Indonesia, as shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Supervisor => 'Pembimbing',
            self::Examiner => 'Penguji',
        };
    }
}
