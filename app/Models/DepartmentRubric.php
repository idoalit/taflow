<?php

namespace App\Models;

use App\Enums\ExamStage;
use Carbon\CarbonInterface;
use Database\Factories\DepartmentRubricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The rubric a program studi marks one exam stage with, and the score a
 * student has to reach on it.
 *
 * @property int $id
 * @property int $department_id
 * @property ExamStage $exam_stage
 * @property int $rubric_id
 * @property int $pass_threshold
 * @property bool $is_active
 * @property CarbonInterface $effective_from
 * @property CarbonInterface|null $effective_until
 * @property-read Rubric $rubric
 */
#[Fillable([
    'department_id', 'exam_stage', 'rubric_id', 'pass_threshold',
    'is_active', 'effective_from', 'effective_until',
])]
class DepartmentRubric extends Model
{
    /** @use HasFactory<DepartmentRubricFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exam_stage' => ExamStage::class,
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Rubric, $this> */
    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class);
    }

    /**
     * The assignment in force for a stage, if the program studi has made one.
     */
    public static function activeFor(Department $department, ExamStage $stage): ?self
    {
        return static::query()
            ->where('department_id', $department->id)
            ->where('exam_stage', $stage)
            ->where('is_active', true)
            ->with('rubric.criteria')
            ->latest('effective_from')
            ->first();
    }
}
