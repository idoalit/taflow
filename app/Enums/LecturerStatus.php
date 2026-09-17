<?php

namespace App\Enums;

enum LecturerStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Retired = 'retired';

    /**
     * Human readable label, in Bahasa Indonesia, as shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Tidak Aktif',
            self::Retired => 'Purnatugas',
        };
    }
}
