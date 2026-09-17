<?php

namespace App\Http\Resources\Admin;

use App\Actions\Thesis\ThesisPhaseAccess;
use App\Concerns\FormatsIndonesianDates;
use App\Enums\GuidanceStage;
use App\Enums\SupervisorRole;
use App\Models\Student;
use App\Models\StudentSupervisor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A student as the Admin Prodi screens list them: who supervises them, how far
 * along they are, and what is currently waiting on someone.
 *
 * @mixin Student
 */
class AdminStudentResource extends JsonResource
{
    use FormatsIndonesianDates;

    private bool $detailed = false;

    public function withDetail(): self
    {
        $this->detailed = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $proposal = $this->activeProposal();
        $stage = ThesisPhaseAccess::forStudent($this->resource)->researchPhaseOpen()
            ? GuidanceStage::Research
            : GuidanceStage::Proposal;
        $required = $stage === GuidanceStage::Proposal
            ? $this->department->activeThesisSetting()->min_proposal_guidance_sessions
            : $this->department->activeThesisSetting()->min_research_guidance_sessions;
        $completed = $this->completedGuidanceCount($stage);

        return [
            'id' => $this->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'student_number' => $this->student_number,
            'enrollment_year' => $this->enrollment_year,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'stage_label' => $stage->label(),
            'supervisors' => $this->panelNames(SupervisorRole::Supervisor),
            'examiners' => $this->panelNames(SupervisorRole::Examiner),
            'has_supervisor' => $this->panelNames(SupervisorRole::Supervisor) !== [],
            'sessions_completed' => $completed,
            'sessions_required' => $required,
            'progress_percent' => $required === 0 ? 100 : min(100, (int) round($completed / $required * 100)),
            'proposal_status_label' => $proposal?->status->label() ?? 'Belum ada proposal',
            'proposal_title' => $proposal?->title,
            ...$this->detailed ? $this->detail() : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(): array
    {
        return [
            'department' => $this->department->name,
            'initials' => $this->user->initials(),
            'assignments' => $this->supervisorAssignments
                ->map(fn (StudentSupervisor $assignment): array => [
                    'lecturer_id' => $assignment->lecturer_id,
                    'name' => $assignment->lecturer->user->name,
                    'role' => $assignment->role->value,
                    'role_label' => $assignment->positionLabel(),
                ])->values(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function panelNames(SupervisorRole $role): array
    {
        return $this->supervisorAssignments
            ->where('role', $role)
            ->sortBy('order')
            ->map(fn (StudentSupervisor $assignment): string => $assignment->lecturer->user->name)
            ->values()
            ->all();
    }
}
