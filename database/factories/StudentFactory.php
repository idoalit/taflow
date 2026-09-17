<?php

namespace Database\Factories;

use App\Enums\StudentStatus;
use App\Models\Department;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'department_id' => Department::factory(),
            'student_number' => fake()->unique()->numerify('########'),
            'phone' => fake()->phoneNumber(),
            'enrollment_year' => fake()->numberBetween(2019, 2024),
            'status' => StudentStatus::Active,
        ];
    }
}
