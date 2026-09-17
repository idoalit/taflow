<?php

namespace App\Models;

use App\Enums\ExamResult;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Berita Acara: what the panel decided, as decided on the day.
 *
 * @property int $id
 * @property string $exam_type
 * @property int $exam_id
 * @property int $decided_by
 * @property ExamResult $decision
 * @property string|null $exam_score
 * @property string|null $supervision_score
 * @property string|null $final_score
 * @property string|null $letter
 * @property int $revision_days
 * @property string|null $notes
 * @property CarbonInterface $decided_at
 */
#[Fillable([
    'exam_type', 'exam_id', 'decided_by', 'decision', 'exam_score',
    'supervision_score', 'final_score', 'letter', 'revision_days', 'notes', 'decided_at',
])]
class ExamMinutes extends Model
{
    protected $table = 'exam_minutes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => ExamResult::class,
            'decided_at' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * Whether the student owes revisions, and so whether the deadline applies.
     */
    public function requiresRevision(): bool
    {
        return $this->decision === ExamResult::ConditionalPass;
    }
}
