<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Models\ProposalExamRequest;
use App\Models\Student;
use App\Models\ThesisProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProposalExamRequest>
 */
class ProposalExamRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'thesis_proposal_id' => ThesisProposal::factory(),
            'student_notes' => fake()->sentence(),
            'documents' => [],
            'status' => ApprovalStatus::Pending,
            'supervisor_status' => ApprovalStatus::Pending,
            'admin_status' => ApprovalStatus::Pending,
        ];
    }

    public function supervisorApproved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'supervisor_status' => ApprovalStatus::Approved,
            'supervisor_verified_at' => now(),
        ]);
    }
}
