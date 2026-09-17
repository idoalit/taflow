<?php

namespace Database\Factories;

use App\Enums\LecturerStatus;
use App\Models\Lecturer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lecturer>
 */
class LecturerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'lecturer_number' => fake()->unique()->numerify('##########'),
            'phone' => fake()->phoneNumber(),
            'academic_position' => fake()->randomElement(['Lektor', 'Lektor Kepala', 'Asisten Ahli', 'Guru Besar']),
            'status' => LecturerStatus::Active,
            'max_supervisions' => 10,
        ];
    }
}
