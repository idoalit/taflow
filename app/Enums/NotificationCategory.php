<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case Approval = 'approval';
    case Schedule = 'schedule';
    case Reminder = 'reminder';
    case Document = 'document';

    /**
     * Human readable label, in Bahasa Indonesia, as shown on the category tag.
     */
    public function label(): string
    {
        return match ($this) {
            self::Approval => 'Persetujuan',
            self::Schedule => 'Jadwal',
            self::Reminder => 'Pengingat',
            self::Document => 'Dokumen',
        };
    }

    /**
     * The tone the tag borrows from the status palette.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Approval => 'success',
            self::Schedule => 'info',
            self::Reminder => 'warning',
            self::Document => 'neutral',
        };
    }
}
