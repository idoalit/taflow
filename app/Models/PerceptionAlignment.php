<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Carbon\CarbonInterface;
use Database\Factories\PerceptionAlignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A supervisor's verdict on a thesis proposal. Every supervisor assigned to the
 * student gets one row per proposal; the proposal is only approved once all of
 * them have responded positively.
 *
 * @property int $id
 * @property int $thesis_proposal_id
 * @property int $student_id
 * @property int $lecturer_id
 * @property int $supervisor_order
 * @property ApprovalStatus $status
 * @property string|null $feedback
 * @property string|null $preparation_requirements
 * @property CarbonInterface|null $responded_at
 */
#[Fillable([
    'thesis_proposal_id', 'student_id', 'lecturer_id', 'supervisor_order',
    'status', 'feedback', 'preparation_requirements', 'responded_at',
])]
class PerceptionAlignment extends Model
{
    /** @use HasFactory<PerceptionAlignmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ThesisProposal, $this> */
    public function thesisProposal(): BelongsTo
    {
        return $this->belongsTo(ThesisProposal::class);
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

    /**
     * Feedback is sequential: the main supervisor writes first, and only then
     * does the second supervisor's turn open. This is what the Persamaan
     * Persepsi screen renders its "menunggu" states from.
     */
    public function isMyTurn(): bool
    {
        if ($this->status !== ApprovalStatus::Pending) {
            return false;
        }

        if ($this->supervisor_order === 1) {
            return true;
        }

        return $this->thesisProposal
            ->perceptionAlignments()
            ->where('supervisor_order', '<', $this->supervisor_order)
            ->where('status', ApprovalStatus::Pending)
            ->doesntExist();
    }
}
