<?php

namespace App\Support;

use App\Enums\SupervisorRole;
use App\Models\ExamParticipant;
use App\Models\StudentSupervisor;
use App\Models\User;

/**
 * One member of an exam panel, however the panel was arrived at.
 *
 * A sitting scheduled with its own panel names its members directly; one
 * scheduled before panels were recorded falls back to the student's roster.
 * Callers should not have to care which, so both are read as this.
 */
final readonly class PanelMember
{
    public function __construct(
        public int $lecturerId,
        public User $user,
        public string $label,
        public SupervisorRole $supervisorRole,
        public ?ExamParticipant $participant = null,
    ) {}

    public static function fromParticipant(ExamParticipant $participant, int $ordinal = 0): self
    {
        return new self(
            lecturerId: (int) $participant->lecturer_id,
            user: $participant->user,
            label: $participant->positionLabel($ordinal),
            supervisorRole: $participant->role->scoresExam()
                ? SupervisorRole::Examiner
                : SupervisorRole::Supervisor,
            participant: $participant,
        );
    }

    public static function fromAssignment(StudentSupervisor $assignment): self
    {
        return new self(
            lecturerId: $assignment->lecturer_id,
            user: $assignment->lecturer->user,
            label: $assignment->positionLabel(),
            supervisorRole: $assignment->role,
        );
    }

    public function initials(): string
    {
        return $this->user->initials();
    }

    public function hasSigned(): bool
    {
        return $this->participant?->hasSigned() ?? false;
    }

    public function attended(): bool
    {
        return $this->participant !== null && $this->participant->attended;
    }
}
