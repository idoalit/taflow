<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One criterion's mark within a panel member's verdict. Label and weight are
 * copied rather than referenced, so editing a rubric later cannot rewrite a
 * verdict that has already been given.
 *
 * @property int $id
 * @property int $exam_evaluation_id
 * @property string $criterion_key
 * @property string $criterion_label
 * @property int $weight
 * @property int $max_score
 * @property string $score
 */
#[Fillable(['exam_evaluation_id', 'criterion_key', 'criterion_label', 'weight', 'max_score', 'score'])]
class ExamEvaluationScore extends Model
{
    /** @return BelongsTo<ExamEvaluation, $this> */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(ExamEvaluation::class, 'exam_evaluation_id');
    }
}
