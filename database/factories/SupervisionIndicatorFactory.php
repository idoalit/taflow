<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\SupervisionIndicator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupervisionIndicator>
 */
class SupervisionIndicatorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'code' => fake()->unique()->bothify('#?'),
            'label' => fake()->sentence(),
            'max_score' => 20,
            'order' => 1,
            'is_active' => true,
        ];
    }
}
