<?php

namespace App\Actions\Thesis;

use App\Enums\ApprovalStatus;
use App\Enums\SupervisorRole;
use App\Models\ThesisProposal;

/**
 * Approving a proposal starts the Persamaan Persepsi round: one slot per
 * assigned supervisor, filled in order of seniority.
 */
final readonly class OpenPerceptionAlignment
{
    public function __invoke(ThesisProposal $proposal): void
    {
        $assignments = $proposal->student->supervisorAssignments()
            ->where('role', SupervisorRole::Supervisor)
            ->orderBy('order')
            ->get();

        foreach ($assignments as $assignment) {
            $proposal->perceptionAlignments()->firstOrCreate(
                ['lecturer_id' => $assignment->lecturer_id],
                [
                    'student_id' => $proposal->student_id,
                    'supervisor_order' => $assignment->order,
                    'status' => ApprovalStatus::Pending,
                ],
            );
        }
    }
}
