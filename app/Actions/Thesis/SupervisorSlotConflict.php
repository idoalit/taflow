<?php

namespace App\Actions\Thesis;

use App\Enums\GuidanceStatus;
use App\Enums\GuidanceTargetType;
use App\Enums\GuidanceType;
use App\Models\GuidanceSession;
use App\Models\Lecturer;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * A supervisor can only be in one face-to-face bimbingan at a time, so a slot
 * booked at 09:00 blocks the rest of that hour for their other students too.
 */
final readonly class SupervisorSlotConflict
{
    /**
     * The clashing session, or null when the slot is free. Async sessions never
     * clash — they have no slot to occupy.
     */
    public function find(
        Student $student,
        GuidanceTargetType $target,
        CarbonInterface $scheduledAt,
        ?GuidanceSession $ignore = null,
    ): ?GuidanceSession {
        $lecturerIds = $this->targetLecturerIds($student, $target);

        if ($lecturerIds === []) {
            return null;
        }

        $slot = $student->department->activeThesisSetting()->guidance_slot_minutes;

        return GuidanceSession::query()
            ->where('guidance_type', GuidanceType::FaceToFace)
            ->where('status', GuidanceStatus::Ongoing)
            ->whereNotNull('meeting_scheduled_at')
            // Overlap either way round: the new slot may start inside an
            // existing one, or an existing one inside the new slot.
            ->whereBetween('meeting_scheduled_at', [
                $scheduledAt->copy()->subMinutes($slot - 1),
                $scheduledAt->copy()->addMinutes($slot - 1),
            ])
            ->when($ignore !== null, fn (Builder $query) => $query->whereKeyNot($ignore->getKey()))
            ->whereHas('student.supervisorAssignments', fn (Builder $assignments) => $assignments
                ->whereIn('lecturer_id', $lecturerIds))
            ->with('student.user')
            ->first();
    }

    /**
     * @return array<int, int>
     */
    private function targetLecturerIds(Student $student, GuidanceTargetType $target): array
    {
        $supervisors = $student->supervisors;

        return ($target === GuidanceTargetType::Both ? $supervisors : $supervisors->take(1))
            ->map(fn (Lecturer $lecturer): int => $lecturer->id)
            ->all();
    }
}
