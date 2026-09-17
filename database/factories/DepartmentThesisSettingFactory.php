<?php

namespace Database\Factories;

use App\Enums\PerceptionAlignmentMode;
use App\Models\Department;
use App\Models\DepartmentThesisSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DepartmentThesisSetting>
 */
class DepartmentThesisSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'min_proposal_guidance_sessions' => 4,
            'min_research_guidance_sessions' => 2,
            'min_logbook_progress' => 100,
            'requires_proposal_exam' => true,
            'perception_alignment_mode' => PerceptionAlignmentMode::Auto,
            'max_supervisors_per_student' => 2,
            'guidance_slot_minutes' => 60,
            'is_active' => true,
            'effective_since' => now()->subYear(),
        ];
    }
}
