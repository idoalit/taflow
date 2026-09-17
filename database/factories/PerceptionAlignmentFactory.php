<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Models\Lecturer;
use App\Models\PerceptionAlignment;
use App\Models\Student;
use App\Models\ThesisProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerceptionAlignment>
 */
class PerceptionAlignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'thesis_proposal_id' => ThesisProposal::factory(),
            'student_id' => Student::factory(),
            'lecturer_id' => Lecturer::factory(),
            'supervisor_order' => 1,
            'status' => ApprovalStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ApprovalStatus::Approved,
            'feedback' => fake()->sentence(),
            'responded_at' => now(),
        ]);
    }
}
