<?php

namespace Database\Factories;

use App\Enums\ExamResult;
use App\Enums\ExamStatus;
use App\Models\ProposalExam;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProposalExam>
 */
class ProposalExamFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'scheduled_by' => User::factory(),
            'title' => 'Ujian Proposal '.fake()->sentence(3),
            'status' => ExamStatus::Scheduled,
            'scheduled_at' => now()->addWeek(),
            'duration_minutes' => 60,
            'venue' => 'Ruang Sidang '.fake()->numberBetween(1, 4),
        ];
    }

    /**
     * An exam that has actually been held, with an outcome.
     */
    public function decided(ExamResult $result = ExamResult::Passed): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ExamStatus::Scored,
            'result' => $result,
            'final_score' => 80,
            'started_at' => now()->subWeek(),
            'completed_at' => now()->subWeek(),
        ]);
    }
}
