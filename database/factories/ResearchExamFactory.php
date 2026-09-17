<?php

namespace Database\Factories;

use App\Enums\ExamStatus;
use App\Models\ResearchExam;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResearchExam>
 */
class ResearchExamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'scheduled_by' => User::factory(),
            'title' => 'Ujian Hasil Penelitian '.fake()->sentence(3),
            'status' => ExamStatus::Scheduled,
            'scheduled_at' => now()->addWeek(),
            'duration_minutes' => 90,
            'venue' => 'Ruang Sidang '.fake()->numberBetween(1, 4),
        ];
    }
}
