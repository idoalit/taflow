<?php

namespace App\Models;

use App\Enums\ExamResult;
use App\Enums\ExamStatus;
use App\Enums\GuidanceStage;
use App\Enums\GuidanceStatus;
use App\Enums\ProposalStatus;
use App\Enums\StudentStatus;
use App\Enums\SupervisorRole;
use App\Enums\UserRole;
use App\Policies\StudentPolicy;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $department_id
 * @property string $student_number
 * @property string|null $phone
 * @property int $enrollment_year
 * @property StudentStatus $status
 */
#[Fillable(['user_id', 'department_id', 'student_number', 'phone', 'enrollment_year', 'status'])]
#[UsePolicy(StudentPolicy::class)]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => StudentStatus::class];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return HasMany<ThesisProposal, $this> */
    public function thesisProposals(): HasMany
    {
        return $this->hasMany(ThesisProposal::class);
    }

    /** @return HasMany<StudentSupervisor, $this> */
    public function supervisorAssignments(): HasMany
    {
        return $this->hasMany(StudentSupervisor::class);
    }

    /** @return BelongsToMany<Lecturer, $this> */
    public function supervisors(): BelongsToMany
    {
        return $this->belongsToMany(Lecturer::class, 'student_supervisors')
            ->wherePivot('role', SupervisorRole::Supervisor->value)
            ->withPivot(['role', 'order'])
            ->orderBy('student_supervisors.order')
            ->withTimestamps();
    }

    /** @return BelongsToMany<Lecturer, $this> */
    public function examiners(): BelongsToMany
    {
        return $this->belongsToMany(Lecturer::class, 'student_supervisors')
            ->wherePivot('role', SupervisorRole::Examiner->value)
            ->withPivot(['role', 'order'])
            ->orderBy('student_supervisors.order')
            ->withTimestamps();
    }

    /** @return HasMany<GuidanceSession, $this> */
    public function guidanceSessions(): HasMany
    {
        return $this->hasMany(GuidanceSession::class);
    }

    /** @return HasMany<ResearchLogbook, $this> */
    public function researchLogbooks(): HasMany
    {
        return $this->hasMany(ResearchLogbook::class);
    }

    /** @return HasMany<ProposalExamRequest, $this> */
    public function proposalExamRequests(): HasMany
    {
        return $this->hasMany(ProposalExamRequest::class);
    }

    /** @return HasMany<ResearchExamRequest, $this> */
    public function researchExamRequests(): HasMany
    {
        return $this->hasMany(ResearchExamRequest::class);
    }

    /** @return HasMany<ProposalExam, $this> */
    public function proposalExams(): HasMany
    {
        return $this->hasMany(ProposalExam::class);
    }

    /** @return HasMany<ResearchExam, $this> */
    public function researchExams(): HasMany
    {
        return $this->hasMany(ResearchExam::class);
    }

    /**
     * The single proposal a student may have in flight. The redesign allows one
     * active proposal at a time — anything rejected no longer blocks a new one.
     */
    public function activeProposal(): ?ThesisProposal
    {
        return $this->thesisProposals()
            ->whereNot('status', ProposalStatus::Rejected)
            ->latest('id')
            ->first();
    }

    public function completedGuidanceCount(GuidanceStage $stage): int
    {
        return $this->guidanceSessions()
            ->where('stage', $stage)
            ->where('status', GuidanceStatus::Completed)
            ->count();
    }

    /**
     * The proposal exam that decides whether the research stage is open — the
     * most recent one that was not cancelled. Ordered by id because a scheduled
     * exam has no completion date yet.
     */
    public function latestProposalExam(): ?ProposalExam
    {
        return $this->proposalExams()
            ->whereNot('status', ExamStatus::Cancelled)
            ->latest('id')
            ->first();
    }

    /**
     * Whether the student has sat a proposal exam and come away with a result
     * that lets them proceed to the research stage.
     */
    public function hasPassedProposalExam(): bool
    {
        return $this->proposalExams()
            ->whereIn('status', ExamStatus::decided())
            ->whereIn('result', array_filter(
                ExamResult::cases(),
                fn (ExamResult $result): bool => $result->unlocksNextStage(),
            ))
            ->exists();
    }

    public function latestLogbookProgress(): int
    {
        return (int) ($this->researchLogbooks()->orderByDesc('log_date')->orderByDesc('id')->value('progress') ?? 0);
    }

    /**
     * Narrow a student query to the rows the given user is allowed to see.
     * This is the single place the cross-role visibility rule lives: a lecturer
     * sees the students they supervise or examine, a program admin sees the
     * students of the departments they administer, and a superadmin sees all.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $workspace = $user->activeWorkspace();
        $department = $workspace?->department;

        return match ($workspace?->role) {
            UserRole::SuperAdmin => $query,
            // Scoped to the one prodi being administered, not to every prodi
            // the user happens to administer.
            UserRole::Admin => $department === null
                ? $query->whereRaw('1 = 0')
                : $query->where('department_id', $department->id),
            // A dosen supervising across prodi sees, in each workspace, only
            // the students of that prodi.
            UserRole::Lecturer => $query
                ->when($department !== null, fn (Builder $scoped) => $scoped->where('department_id', $department->id))
                ->whereHas(
                    'supervisorAssignments',
                    fn (Builder $assignments) => $assignments->whereIn(
                        'lecturer_id',
                        Lecturer::query()->where('user_id', $user->id)->select('id')
                    )
                ),
            UserRole::Student => $query->where('user_id', $user->id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->whereKey($this->getKey())->visibleTo($user)->exists();
    }

    /**
     * Whether the given user is one of this student's assigned supervisors.
     */
    public function isSupervisedBy(User $user): bool
    {
        return $this->supervisorAssignments()
            ->where('role', SupervisorRole::Supervisor)
            ->whereIn('lecturer_id', Lecturer::query()->where('user_id', $user->id)->select('id'))
            ->exists();
    }
}
