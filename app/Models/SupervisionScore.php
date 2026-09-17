<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A pembimbing's mark on one indicator for one sitting.
 *
 * @property int $id
 * @property string $exam_type
 * @property int $exam_id
 * @property int $lecturer_id
 * @property int|null $supervision_indicator_id
 * @property string $indicator_code
 * @property string $indicator_label
 * @property int $max_score
 * @property string $score
 * @property string|null $notes
 * @property CarbonInterface $scored_at
 */
#[Fillable([
    'exam_type', 'exam_id', 'lecturer_id', 'supervision_indicator_id',
    'indicator_code', 'indicator_label', 'max_score', 'score', 'notes', 'scored_at',
])]
class SupervisionScore extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['scored_at' => 'datetime'];
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

    public function descriptor(): string
    {
        return SupervisionIndicator::descriptorFor((float) $this->score, $this->max_score);
    }
}
