<?php

namespace App\Models;

use App\Enums\ExamResult;
use App\Enums\SupervisorRole;
use Carbon\CarbonInterface;
use Database\Factories\ExamEvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

/**
 * A single panel member's verdict on a proposal or research exam.
 *
 * @property int $id
 * @property string $exam_type
 * @property int $exam_id
 * @property int $lecturer_id
 * @property int|null $rubric_id
 * @property SupervisorRole $panel_role
 * @property string $final_score
 * @property-read Collection<int, ExamEvaluationScore> $scores
 * @property ExamResult $result
 * @property string|null $feedback
 * @property string|null $revision_notes
 * @property CarbonInterface $evaluated_at
 * @property-read ThesisExam $exam
 */
#[Fillable([
    'exam_type', 'exam_id', 'lecturer_id', 'rubric_id', 'panel_role',
    'final_score', 'result', 'feedback', 'revision_notes', 'evaluated_at',
])]
class ExamEvaluation extends Model
{
    /** @use HasFactory<ExamEvaluationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'panel_role' => SupervisorRole::class,
            'result' => ExamResult::class,
            'evaluated_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function exam(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Lecturer, $this> */
    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    /** @return BelongsTo<Rubric, $this> */
    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class);
    }

    /** @return HasMany<ExamEvaluationScore, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(ExamEvaluationScore::class);
    }

    /**
     * A panel member's verdict, on the scale the rubric's own scheme implies.
     *
     * Weighted: each mark is read as the fraction of its criterion it earned —
     * 4 out of 5 and 80 out of 100 are the same 80% — and only then weighted,
     * because a raw mean across mixed scales would be meaningless.
     *
     * Block-additive: the marks are simply summed, as the faculty's paper form
     * does. The rubric's maximum scores add up to 100, so the sum already is
     * the verdict.
     *
     * @param  array<string, float>  $marks  criterion key => score
     */
    public static function scoreFor(Rubric $rubric, array $marks): float
    {
        $criteria = $rubric->criteria;

        if ($rubric->isBlockAdditive()) {
            return round(
                $criteria->sum(fn (RubricCriterion $c): float => $marks[$c->key] ?? 0),
                2,
            );
        }

        $total = $criteria->sum('weight');

        if ($total === 0) {
            return 0.0;
        }

        $sum = $criteria->sum(function (RubricCriterion $criterion) use ($marks): float {
            if ($criterion->max_score <= 0) {
                return 0.0;
            }

            return ($marks[$criterion->key] ?? 0) / $criterion->max_score * $criterion->weight;
        });

        return round($sum / $total * 100, 2);
    }

    /**
     * The subtotal of each block, for the printed form and the marking screen.
     *
     * @param  array<string, float>  $marks
     * @return array<int, array{code: string|null, label: string|null, earned: float, max: int}>
     */
    public static function blockSubtotals(Rubric $rubric, array $marks): array
    {
        return $rubric->blocks()
            ->map(fn (array $block): array => [
                'code' => $block['code'],
                'label' => $block['label'],
                'earned' => round($block['subblocks']->sum(
                    fn (array $sub): float => $sub['items']->sum(
                        fn (RubricCriterion $c): float => $marks[$c->key] ?? 0,
                    ),
                ), 2),
                'max' => $block['max'],
            ])
            ->all();
    }
}
