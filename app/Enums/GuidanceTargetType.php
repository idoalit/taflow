<?php

namespace App\Enums;

enum GuidanceTargetType: string
{
    case Single = 'single';
    case Both = 'both';

    /**
     * Human readable label, in Bahasa Indonesia, as shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Single => 'Pembimbing Utama',
            self::Both => 'Semua Pembimbing',
        };
    }
}
