<?php

namespace App\Enums;

/**
 * What a session looks like to a human right now.
 *
 * The stored GuidanceStatus only knows ongoing / completed / cancelled. A
 * face-to-face session booked for tomorrow is stored as "ongoing" but is really
 * *scheduled* until its time arrives — that distinction is derived from the
 * clock rather than stored, so nothing has to sweep the table to flip it.
 */
enum GuidanceDisplayStatus: string
{
    case Scheduled = 'scheduled';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Terjadwal',
            self::Ongoing => 'Berlangsung',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
