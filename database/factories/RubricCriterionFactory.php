<?php

namespace Database\Factories;

use App\Models\Rubric;
use App\Models\RubricCriterion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RubricCriterion>
 */
class RubricCriterionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->unique()->word().' '.fake()->word();

        return [
            'rubric_id' => Rubric::factory(),
            'label' => ucfirst($label),
            'key' => str_replace(' ', '_', $label),
            'weight' => 25,
            'max_score' => 100,
            'order' => 1,
        ];
    }
}
