<?php

namespace App\Actions\Thesis;

use App\Enums\ExamParticipantRole;
use App\Enums\ExamStage;
use App\Enums\ExamStatus;
use App\Enums\NotificationCategory;
use App\Enums\SupervisorRole;
use App\Models\DepartmentRubric;
use App\Models\ThesisExam;
use App\Models\ThesisExamRequest;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Turns an approved exam request into a scheduled exam.
 *
 * The rubric and its pass mark are copied onto the exam rather than looked up
 * later, so a program studi that changes its rubric afterwards does not change
 * the terms of an exam already on the calendar.
 */
final readonly class ScheduleExam
{
    public function __construct(
        private NotifyThesisParticipants $notify,
        private BuildExamPanel $buildPanel,
    ) {}

    /**
     * @param  array<int, array{lecturer_id: int, role: string}>  $panel
     */
    public function handle(
        ThesisExamRequest $examRequest,
        User $scheduledBy,
        string $title,
        CarbonInterface $scheduledAt,
        int $durationMinutes,
        ?string $venue,
        ?string $preparationNotes,
        array $panel,
        ?int $rubricId = null,
    ): ThesisExam {
        $stage = $examRequest->stage();
        $student = $examRequest->student;
        $assignment = DepartmentRubric::activeFor($student->department, $stage);

        /** @var ThesisExam $exam */
        $exam = $stage->modelClass()::query()->create([
            'student_id' => $student->id,
            ...$stage === ExamStage::Proposal
                ? ['thesis_proposal_id' => $examRequest->thesis_proposal_id]
                : [],
            'scheduled_by' => $scheduledBy->id,
            'rubric_id' => $rubricId ?? $assignment?->rubric_id,
            'pass_threshold' => $assignment?->pass_threshold,
            'title' => $title,
            'status' => ExamStatus::Scheduled,
            'scheduled_at' => $scheduledAt,
            'duration_minutes' => $durationMinutes,
            'venue' => $venue,
            'preparation_notes' => $preparationNotes,
        ]);

        // The sitting's own panel, and the roster rows that give its examiners
        // sight of the exam in the first place.
        $this->buildPanel->handle($exam, $panel);
        $this->syncPanel($examRequest, $panel, $scheduledBy);

        $examRequest->forceFill([
            $stage === ExamStage::Proposal ? 'proposal_exam_id' : 'research_exam_id' => $exam->id,
        ])->save();

        $this->notify->student(
            $student,
            NotificationCategory::Schedule,
            "{$stage->title()} dijadwalkan",
            "{$stage->title()} Anda dijadwalkan pada {$scheduledAt->translatedFormat('d F Y, H:i')}"
            .($venue === null ? '.' : " di {$venue}."),
            route('exams.show', ['stage' => $stage, 'exam' => $exam->id]),
        );

        return $exam;
    }

    /**
     * Examiners named on the panel become part of the student's roster, which
     * is what gives them sight of the exam and the right to mark it. A dosen
     * already on the roster keeps their one seat.
     *
     * @param  array<int, array{lecturer_id: int, role: string}>  $panel
     */
    private function syncPanel(ThesisExamRequest $examRequest, array $panel, User $scheduledBy): void
    {
        $student = $examRequest->student;
        $existing = $student->supervisorAssignments()->pluck('order', 'lecturer_id');
        $next = ((int) $existing->max()) + 1;

        foreach ($panel as $member) {
            // Keyed on the lecturer alone, and only ever created: a dosen who
            // is already on the roster sits on the panel by virtue of that, so
            // naming them here must not add a second seat — nor demote a
            // pembimbing to penguji because of how the form was filled in.
            $student->supervisorAssignments()->firstOrCreate(
                ['lecturer_id' => $member['lecturer_id']],
                [
                    'role' => ExamParticipantRole::from($member['role'])->scoresExam()
                        ? SupervisorRole::Examiner
                        : SupervisorRole::Supervisor,
                    'order' => $existing[$member['lecturer_id']] ?? $next++,
                    'assigned_by' => $scheduledBy->id,
                    'assigned_at' => now(),
                ],
            );
        }

    }
}
