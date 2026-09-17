<?php

namespace App\Models;

use App\Enums\RevisionStatus;
use App\Policies\RevisionNotePolicy;
use Carbon\CarbonInterface;
use Database\Factories\RevisionNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One numbered line of an examiner's Lembar Revisi.
 *
 * @property int $id
 * @property string $exam_type
 * @property int $exam_id
 * @property int $lecturer_id
 * @property int $position
 * @property string $body
 * @property RevisionStatus $status
 * @property CarbonInterface|null $due_date
 * @property CarbonInterface|null $completed_at
 */
#[Fillable([
    'exam_type', 'exam_id', 'lecturer_id', 'position', 'body',
    'status', 'due_date', 'completed_at',
])]
#[UsePolicy(RevisionNotePolicy::class)]
class RevisionNote extends Model
{
    /** @use HasFactory<RevisionNoteFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RevisionStatus::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function exam(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The sitting this belongs to, typed. morphTo can only promise a Model.
     */
    public function thesisExam(): ?ThesisExam
    {
        $exam = $this->exam;

        return $exam instanceof ThesisExam ? $exam : null;
    }

    /** @return BelongsTo<Lecturer, $this> */
    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }
}
