<?php

namespace Database\Factories;

use App\Enums\ExamStage;
use App\Models\Department;
use App\Models\DepartmentRubric;
use App\Models\Rubric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepartmentRubric>
 */
class DepartmentRubricFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'exam_stage' => ExamStage::Proposal,
            'rubric_id' => Rubric::factory()->published(),
            'pass_threshold' => 65,
            'is_active' => true,
            'effective_from' => now()->startOfYear(),
        ];
    }
}
