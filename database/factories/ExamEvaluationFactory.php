<?php

namespace Database\Factories;

use App\Enums\ExamResult;
use App\Enums\SupervisorRole;
use App\Models\ExamEvaluation;
use App\Models\Lecturer;
use App\Models\ProposalExam;
use App\Models\ResearchExam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamEvaluation>
 */
class ExamEvaluationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $presentation = fake()->numberBetween(70, 95);
        $document = fake()->numberBetween(70, 95);

        return [
            'exam_type' => (new ProposalExam)->getMorphClass(),
            'exam_id' => ProposalExam::factory(),
            'lecturer_id' => Lecturer::factory(),
            'panel_role' => SupervisorRole::Supervisor,
            'presentation_score' => $presentation,
            'document_score' => $document,
            'final_score' => round(($presentation + $document) / 2, 2),
            'result' => ExamResult::Passed,
            'feedback' => fake()->sentence(),
            'evaluated_at' => now(),
        ];
    }

    /**
     * Attach the verdict to a research exam instead of a proposal exam. Pass an
     * exam to reuse it; omit it and one is created.
     */
    public function forResearchExam(?ResearchExam $exam = null): self
    {
        return $this->state(fn (): array => [
            'exam_type' => (new ResearchExam)->getMorphClass(),
            'exam_id' => $exam instanceof ResearchExam ? $exam->id : ResearchExam::factory(),
        ]);
    }
}
