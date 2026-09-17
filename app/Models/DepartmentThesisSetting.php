<?php

namespace App\Models;

use App\Enums\PerceptionAlignmentMode;
use App\Policies\DepartmentThesisSettingPolicy;
use Carbon\CarbonInterface;
use Database\Factories\DepartmentThesisSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $department_id
 * @property int $min_proposal_guidance_sessions
 * @property int $min_research_guidance_sessions
 * @property int $min_logbook_progress
 * @property bool $requires_proposal_exam
 * @property PerceptionAlignmentMode $perception_alignment_mode
 * @property bool $allows_supervisor_as_examiner
 * @property int $min_examiners
 * @property string $exam_score_weight
 * @property string $supervision_score_weight
 * @property int $max_supervisors_per_student
 * @property int $guidance_slot_minutes
 * @property bool $is_active
 * @property CarbonInterface $effective_since
 * @property CarbonInterface|null $effective_until
 */
#[Fillable([
    'department_id', 'min_proposal_guidance_sessions', 'min_research_guidance_sessions',
    'min_logbook_progress', 'requires_proposal_exam', 'perception_alignment_mode',
    'allows_supervisor_as_examiner', 'min_examiners',
    'exam_score_weight', 'supervision_score_weight',
    'max_supervisors_per_student', 'guidance_slot_minutes',
    'is_active', 'effective_since', 'effective_until',
])]
#[UsePolicy(DepartmentThesisSettingPolicy::class)]
class DepartmentThesisSetting extends Model
{
    /** @use HasFactory<DepartmentThesisSettingFactory> */
    use HasFactory;

    protected $attributes = [
        'min_proposal_guidance_sessions' => 4,
        'min_research_guidance_sessions' => 2,
        'min_logbook_progress' => 100,
        'requires_proposal_exam' => true,
        'perception_alignment_mode' => PerceptionAlignmentMode::Auto->value,
        'allows_supervisor_as_examiner' => true,
        'min_examiners' => 2,
        'exam_score_weight' => 0.900,
        'supervision_score_weight' => 0.100,
        'max_supervisors_per_student' => 2,
        'guidance_slot_minutes' => 60,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_proposal_exam' => 'boolean',
            'perception_alignment_mode' => PerceptionAlignmentMode::class,
            'allows_supervisor_as_examiner' => 'boolean',
            'is_active' => 'boolean',
            'effective_since' => 'datetime',
            'effective_until' => 'datetime',
        ];
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
