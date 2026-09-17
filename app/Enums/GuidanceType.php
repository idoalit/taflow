<?php

namespace App\Enums;

enum GuidanceType: string
{
    case FaceToFace = 'face_to_face';
    case OnlineAsync = 'online_async';

    /**
     * Human readable label, in Bahasa Indonesia, as shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::FaceToFace => 'Tatap Muka',
            self::OnlineAsync => 'Online Async',
        };
    }
}
