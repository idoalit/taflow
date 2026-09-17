<?php

namespace App\Enums;

enum RevisionStatus: string
{
    case Open = 'open';
    case Done = 'done';

    /**
     * Human readable label, in Bahasa Indonesia, as shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Belum Selesai',
            self::Done => 'Selesai',
        };
    }
}
