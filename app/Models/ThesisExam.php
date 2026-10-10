<?php

namespace App\Models;

use App\Enums\ExamParticipantRole;
use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\ExamStatus;
use App\Policies\ThesisExamPolicy;
use App\Support\PanelMember;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;

/**
 * What the proposal exam and the research exam have in common: a panel drawn
 * from the student's supervisors, one verdict per member, and a result settled
 * from those verdicts. The two keep separate tables because only the proposal
 * exam points back at a proposal.
 *
 * @property int $id
 * @property int $student_id
 * @property int $scheduled_by
 * @property int|null $rubric_id
 * @property int|null $pass_threshold
 * @property string $title
 * @property string|null $description
 * @property ExamStatus $status
 * @property CarbonInterface $scheduled_at
 * @property int $duration_minutes
 * @property string|null $venue
 * @property string|null $preparation_notes
 * @property string|null $final_score
 * @property ExamResult|null $result
 * @property string|null $evaluation_notes
 * @property string|null $feedback
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $released_at
 * @property int|null $released_by
 * @property string|null $verification_token
 * @property-read Student $student
 * @property-read Collection<int, ExamEvaluation> $evaluations
 */
#[UsePolicy(ThesisExamPolicy::class)]
abstract class ThesisExam extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ExamStatus::class,
            'result' => ExamResult::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /**
     * Which of the two exams this is. Drives labels, routes and the morph type.
     */
    abstract public function stage(): ExamStage;

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<User, $this> */
    public function scheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    /** @return MorphMany<ExamEvaluation, $this> */
    public function evaluations(): MorphMany
    {
        return $this->morphMany(ExamEvaluation::class, 'exam');
    }

    /** @return MorphOne<ExamMinutes, $this> */
    public function minutes(): MorphOne
    {
        return $this->morphOne(ExamMinutes::class, 'exam');
    }

    /** @return MorphMany<RevisionNote, $this> */
    public function revisionNotes(): MorphMany
    {
        return $this->morphMany(RevisionNote::class, 'exam')
            ->orderBy('lecturer_id')
            ->orderBy('position');
    }

    public function isReleased(): bool
    {
        return $this->status === ExamStatus::Released;
    }

    /** @return MorphMany<ExamParticipant, $this> */
    public function participants(): MorphMany
    {
        return $this->morphMany(ExamParticipant::class, 'exam');
    }

    /**
     * The examiners of this sitting, chair first.
     *
     * @return Collection<int, ExamParticipant>
     */
    public function examiners(): Collection
    {
        return $this->participants()
            ->whereIn('role', [ExamParticipantRole::ChairExaminer, ExamParticipantRole::MemberExaminer])
            ->with(['user', 'lecturer'])
            ->get()
            ->sortBy(fn (ExamParticipant $p): int => $p->role === ExamParticipantRole::ChairExaminer ? 0 : 1)
            ->values();
    }

    public function chairExaminer(): ?ExamParticipant
    {
        return $this->participants()
            ->where('role', ExamParticipantRole::ChairExaminer)
            ->with('user')
            ->first();
    }

    /**
     * Whether this sitting has its own panel, as against inheriting one from
     * the student's roster. Exams scheduled before panels existed do not.
     */
    public function hasOwnPanel(): bool
    {
        return $this->participants()->whereIn(
            'role',
            [ExamParticipantRole::ChairExaminer, ExamParticipantRole::MemberExaminer],
        )->exists();
    }

    /** @return BelongsTo<Rubric, $this> */
    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class);
    }

    /**
     * The rubric this exam is marked against: the one fixed at scheduling, or
     * failing that whatever the program studi has in force now.
     */
    public function effectiveRubric(): ?Rubric
    {
        if ($this->rubric_id !== null) {
            return $this->rubric()->with('criteria')->first();
        }

        return DepartmentRubric::activeFor($this->student->department, $this->stage())?->rubric;
    }

    /**
     * The mark a student has to reach, from the exam or the standing
     * assignment. Null means the program studi has set no bar.
     */
    public function passThreshold(): ?int
    {
        return $this->pass_threshold
            ?? DepartmentRubric::activeFor($this->student->department, $this->stage())?->pass_threshold;
    }

    /**
     * The panel is the student's supervisors and examiners — there is no
     * separate panel roster.
     *
     * @return Collection<int, PanelMember>
     */
    public function panel(): Collection
    {
        // A sitting with its own panel is authoritative; the roster is only the
        // fallback for exams scheduled before panels were recorded.
        if ($this->hasOwnPanel()) {
            $members = 0;

            return $this->examiners()->map(function (ExamParticipant $participant) use (&$members): PanelMember {
                $ordinal = $participant->role === ExamParticipantRole::MemberExaminer ? ++$members : 0;

                return PanelMember::fromParticipant($participant, $ordinal);
            })->values();
        }

        return $this->student->supervisorAssignments()
            ->with('lecturer.user')
            ->orderBy('role')
            ->orderBy('order')
            ->get()
            // One seat per person. A lecturer listed under two roles would
            // otherwise be counted twice, and the exam could never settle:
            // only one verdict per lecturer can ever be recorded.
            ->unique('lecturer_id')
            ->map(fn (StudentSupervisor $assignment): PanelMember => PanelMember::fromAssignment($assignment))
            ->values();
    }

    /**
     * The exam settles once every panel member has evaluated: the mean of their
     * scores, and the harshest result any of them gave.
     */
    public function settleFromEvaluations(): void
    {
        $evaluations = $this->evaluations()->get();

        if ($evaluations->isEmpty() || $evaluations->count() < $this->panel()->count()) {
            return;
        }

        $final = round((float) $evaluations->avg('final_score'), 2);
        $threshold = $this->passThreshold();

        $harshest = $evaluations
            ->map(fn (ExamEvaluation $evaluation): ExamResult => $evaluation->result)
            ->sortBy(fn (ExamResult $result): int => match ($result) {
                ExamResult::Failed => 0,
                ExamResult::ConditionalPass => 1,
                ExamResult::Passed => 2,
            })
            ->first();

        $this->update([
            'final_score' => $final,
            // The panel's own verdicts decide, except that a mark below the
            // program studi's bar cannot be a pass however kindly it was given.
            'result' => $threshold !== null && $final < $threshold ? ExamResult::Failed : $harshest,
            'status' => ExamStatus::Scored,
            'completed_at' => now(),
        ]);
    }
}
