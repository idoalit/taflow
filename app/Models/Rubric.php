<?php

namespace App\Models;

use App\Enums\RubricStatus;
use App\Enums\ScoringScheme;
use App\Policies\RubricPolicy;
use Carbon\CarbonInterface;
use Database\Factories\RubricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A scoring template belonging to one program studi.
 *
 * @property int $id
 * @property int $department_id
 * @property string $name
 * @property string|null $description
 * @property ScoringScheme $scoring_scheme
 * @property RubricStatus $status
 * @property CarbonInterface|null $published_at
 * @property int|null $created_by
 * @property-read Collection<int, RubricCriterion> $criteria
 */
#[Fillable(['department_id', 'name', 'description', 'scoring_scheme', 'status', 'published_at', 'created_by'])]
#[UsePolicy(RubricPolicy::class)]
class Rubric extends Model
{
    /** @use HasFactory<RubricFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scoring_scheme' => ScoringScheme::class,
            'status' => RubricStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return HasMany<RubricCriterion, $this> */
    public function criteria(): HasMany
    {
        return $this->hasMany(RubricCriterion::class)->orderBy('order');
    }

    /** @return HasMany<DepartmentRubric, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(DepartmentRubric::class);
    }

    public function totalWeight(): int
    {
        return (int) $this->criteria->sum('weight');
    }

    /**
     * The highest verdict this rubric can produce: 100 for either scheme, but
     * reached differently — by weights summing to 100, or by the criteria's own
     * maximum scores summing to 100.
     */
    public function totalMaxScore(): int
    {
        return (int) $this->criteria->sum('max_score');
    }

    public function isBlockAdditive(): bool
    {
        return $this->scoring_scheme === ScoringScheme::BlockAdditive;
    }

    /**
     * Criteria grouped for display and for block subtotals, in rubric order.
     *
     * @return Collection<int, array{code: string|null, label: string|null, max: int, subblocks: Collection<int, array{code: string|null, label: string|null, max: int, items: Collection<int, RubricCriterion>}>}>
     */
    public function blocks(): Collection
    {
        return $this->criteria
            ->groupBy('block_code')
            ->map(fn (Collection $items): array => [
                'code' => $items->first()->block_code,
                'label' => $items->first()->block_label,
                'max' => (int) $items->sum('max_score'),
                'subblocks' => $items
                    ->groupBy('subblock_code')
                    ->map(fn (Collection $group): array => [
                        'code' => $group->first()->subblock_code,
                        'label' => $group->first()->subblock_label,
                        'max' => (int) $group->sum('max_score'),
                        'items' => $group->values(),
                    ])
                    ->values(),
            ])
            ->values();
    }

    public function isPublished(): bool
    {
        return $this->status === RubricStatus::Published;
    }

    /**
     * A rubric is only usable once it can produce a full score: weights adding
     * up under the weighted scheme, maximum scores adding up under the additive
     * one.
     */
    public function isBalanced(): bool
    {
        if ($this->criteria->isEmpty()) {
            return false;
        }

        return $this->isBlockAdditive()
            ? $this->totalMaxScore() === 100
            : $this->totalWeight() === 100;
    }

    /**
     * Only a draft may be changed. Publishing is the commitment: from then on
     * the rubric may already be applied to a stage or fixed onto a scheduled
     * exam, and a superseding rubric is the honest way to change the terms.
     */
    public function isEditable(): bool
    {
        return $this->status === RubricStatus::Draft;
    }

    /**
     * Whether any verdict has been recorded against this rubric.
     */
    public function hasBeenUsed(): bool
    {
        return ExamEvaluation::where('rubric_id', $this->id)->exists();
    }
}
