<?php

namespace App\Enums;

enum UserRole: string
{
    case Student = 'student';
    case Lecturer = 'lecturer';
    case Admin = 'admin';
    case SuperAdmin = 'super-admin';

    /**
     * Human readable label, in Bahasa Indonesia, as shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Student => 'Mahasiswa',
            self::Lecturer => 'Dosen',
            self::Admin => 'Admin Prodi',
            self::SuperAdmin => 'Superadmin',
        };
    }
}
