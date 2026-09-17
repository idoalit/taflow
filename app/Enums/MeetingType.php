<?php

namespace App\Enums;

enum MeetingType: string
{
    case Offline = 'offline';
    case OnlineMeeting = 'online_meeting';

    /**
     * Human readable label, in Bahasa Indonesia, as shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Offline => 'Offline (Tatap Muka Langsung)',
            self::OnlineMeeting => 'Online Meeting (Zoom/Meet)',
        };
    }
}
