<?php

namespace Database\Factories;

use App\Models\ResearchLogbook;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResearchLogbook>
 */
class ResearchLogbookFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'log_date' => fake()->dateTimeBetween('-3 months', 'now'),
            'description' => fake()->paragraph(3),
            'progress' => fake()->numberBetween(10, 90),
        ];
    }
}
