<?php

namespace App\Models;

use App\Enums\LecturerStatus;
use App\Enums\SupervisorRole;
use App\Policies\LecturerPolicy;
use Database\Factories\LecturerFactory;
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
 * @property string $lecturer_number
 * @property string|null $phone
 * @property string|null $academic_position
 * @property LecturerStatus $status
 * @property int $max_supervisions
 * @property-read int|null $supervision_count
 */
#[Fillable(['user_id', 'lecturer_number', 'phone', 'academic_position', 'status', 'max_supervisions'])]
#[UsePolicy(LecturerPolicy::class)]
class Lecturer extends Model
{
    /** @use HasFactory<LecturerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => LecturerStatus::class];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Department, $this> */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class)->withPivot('is_primary')->withTimestamps();
    }

    /**
     * The prodi the dosen belongs to. Every other one in departments() is a
     * prodi they also supervise or examine in.
     */
    public function homebase(): ?Department
    {
        return $this->departments->firstWhere('pivot.is_primary', true);
    }

    /**
     * How many of a prodi's mahasiswa still have this dosen on their panel,
     * as pembimbing or penguji. While it is not zero the dosen cannot leave
     * that prodi: the workspace they do that work in would go with them.
     */
    public function panelSeatsIn(Department $department): int
    {
        return $this->supervisorAssignments()
            ->whereHas('student', fn (Builder $query) => $query->where('department_id', $department->id))
            ->count();
    }

    /** @return HasMany<StudentSupervisor, $this> */
    public function supervisorAssignments(): HasMany
    {
        return $this->hasMany(StudentSupervisor::class);
    }

    /** @return BelongsToMany<Student, $this> */
    public function supervisedStudents(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_supervisors')
            ->wherePivot('role', SupervisorRole::Supervisor->value)
            ->withPivot(['role', 'order'])
            ->withTimestamps();
    }

    public function initials(): string
    {
        return $this->user?->initials() ?? '?';
    }
}
