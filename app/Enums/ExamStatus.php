<?php

namespace App\Enums;

enum ExamStatus: string
{
    case Scheduled = 'scheduled';
    case Ongoing = 'ongoing';
    case Scored = 'scored';
    case Released = 'released';
    case Cancelled = 'cancelled';

    /**
     * Human readable label, in Bahasa Indonesia, as shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Terjadwal',
            self::Ongoing => 'Berlangsung',
            self::Scored => 'Selesai Dinilai',
            self::Released => 'Hasil Dirilis',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * The states in which an exam has been decided.
     *
     * Release governs whether the marks are visible, not whether the exam
     * happened: a sitting that has been marked but not yet published is still
     * an exam the student sat and passed.
     *
     * @return array<int, self>
     */
    public static function decided(): array
    {
        return [self::Scored, self::Released];
    }

    /**
     * Whether the student may see the marks. Everything before release is the
     * panel's business, not theirs.
     */
    public function isPublished(): bool
    {
        return $this === self::Released;
    }

    /**
     * Whether marks and decisions may still be changed without unlocking.
     */
    public function isLocked(): bool
    {
        return $this === self::Released;
    }
}
