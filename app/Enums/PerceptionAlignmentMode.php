<?php

namespace App\Enums;

/**
 * Who a student must reach a persamaan persepsi with, besides their first
 * supervisor. A program studi picks one of these for all of its students.
 */
enum PerceptionAlignmentMode: string
{
    case Auto = 'auto';
    case SecondSupervisor = 'second_supervisor';
    case Examiner = 'examiner';
    case Skip = 'skip';

    /**
     * Human readable label, in Bahasa Indonesia, as shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Otomatis (Pembimbing 2, lalu Penguji 1)',
            self::SecondSupervisor => 'Wajib Pembimbing Kedua',
            self::Examiner => 'Wajib Dosen Penguji',
            self::Skip => 'Lewati Persamaan Persepsi',
        };
    }

    public function isRequired(): bool
    {
        return $this !== self::Skip;
    }

    /**
     * Which kind of lecturer plays the second part, or null when either will do.
     */
    public function secondPartyRole(): ?SupervisorRole
    {
        return match ($this) {
            self::Auto, self::Skip => null,
            self::SecondSupervisor => SupervisorRole::Supervisor,
            self::Examiner => SupervisorRole::Examiner,
        };
    }
}
