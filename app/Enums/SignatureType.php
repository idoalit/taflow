<?php

namespace App\Enums;

enum SignatureType: string
{
    case Drawn = 'draw';
    case Uploaded = 'upload';

    /**
     * Human readable label, in Bahasa Indonesia, as shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Drawn => 'Digambar langsung',
            self::Uploaded => 'Diunggah',
        };
    }
}
