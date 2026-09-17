<?php

namespace Database\Factories;

use App\Enums\RevisionStatus;
use App\Models\Lecturer;
use App\Models\ProposalExam;
use App\Models\RevisionNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RevisionNote>
 */
class RevisionNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_type' => (new ProposalExam)->getMorphClass(),
            'exam_id' => ProposalExam::factory(),
            'lecturer_id' => Lecturer::factory(),
            'position' => 1,
            'body' => fake()->sentence(),
            'status' => RevisionStatus::Open,
        ];
    }
}
