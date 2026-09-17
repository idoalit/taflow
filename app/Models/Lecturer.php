<?php

namespace App\Models;

use App\Enums\LecturerStatus;
use App\Enums\SupervisorRole;
use App\Policies\LecturerPolicy;
use Database\Factories\LecturerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
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
