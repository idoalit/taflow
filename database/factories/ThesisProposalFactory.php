<?php

namespace Database\Factories;

use App\Enums\ProposalStatus;
use App\Models\Department;
use App\Models\Student;
use App\Models\ThesisProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThesisProposal>
 */
class ThesisProposalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'department_id' => Department::factory(),
            'title' => fake()->sentence(6),
            'background' => fake()->paragraph(4),
            'problem_formulation' => fake()->paragraph(2),
            'objectives' => fake()->paragraph(2),
            'scope' => fake()->paragraph(),
            'methodology' => fake()->paragraph(2),
            'roadmap_alignment' => fake()->paragraph(),
            'document_path' => null,
            'status' => ProposalStatus::Submitted,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProposalStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProposalStatus::Rejected,
            'rejection_reason' => fake()->sentence(),
        ]);
    }
}
