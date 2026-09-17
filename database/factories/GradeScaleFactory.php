<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\GradeScale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeScale>
 */
class GradeScaleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'letter' => 'A',
            'min_score' => 85,
            'max_score' => 100,
            'order' => 1,
        ];
    }
}
