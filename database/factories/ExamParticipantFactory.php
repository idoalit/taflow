<?php

namespace Database\Factories;

use App\Enums\ExamParticipantRole;
use App\Models\ExamParticipant;
use App\Models\ProposalExam;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamParticipant>
 */
class ExamParticipantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_type' => (new ProposalExam)->getMorphClass(),
            'exam_id' => ProposalExam::factory(),
            'user_id' => User::factory(),
            'role' => ExamParticipantRole::MemberExaminer,
            'attended' => false,
        ];
    }
}
