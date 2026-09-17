<?php

namespace App\Models;

use App\Enums\SupervisorRole;
use Carbon\CarbonInterface;
use Database\Factories\StudentSupervisorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $student_id
 * @property int $lecturer_id
 * @property SupervisorRole $role
 * @property int $order
 * @property int|null $assigned_by
 * @property CarbonInterface|null $assigned_at
 */
#[Fillable(['student_id', 'lecturer_id', 'role', 'order', 'assigned_by', 'assigned_at'])]
class StudentSupervisor extends Model
{
    /** @use HasFactory<StudentSupervisorFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => SupervisorRole::class,
            'assigned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Lecturer, $this> */
    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Label as shown in the UI: "Pembimbing 1", "Penguji 2", and so on.
     */
    public function positionLabel(): string
    {
        return $this->role->label().' '.$this->order;
    }
}
