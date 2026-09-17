<?php

namespace App\Http\Resources;

use App\Models\ExamEvaluation;
use App\Models\ExamEvaluationScore;
use App\Models\ThesisExam;
use App\Support\PanelMember;
use Illuminate\Http\Request;

/**
 * @mixin ThesisExam
 */
class ThesisExamDetailResource extends ThesisExamSummaryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $evaluations = $this->evaluations;

        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'preparation_notes' => $this->preparation_notes,
            'final_score' => $this->final_score,
            'department' => $this->student->department->name,
            'panel' => $this->panel()->map(function (PanelMember $member) use ($evaluations): array {
                $evaluation = $evaluations->firstWhere('lecturer_id', $member->lecturerId);

                return [
                    'lecturer_id' => $member->lecturerId,
                    'name' => $member->user->name,
                    'initials' => $member->initials(),
                    'role_label' => $member->label,
                    'evaluated' => $evaluation !== null,
                    'score' => $evaluation?->final_score,
                    'result_label' => $evaluation?->result->label(),
                ];
            })->values(),
            'my_feedback' => $evaluations
                ->firstWhere('lecturer_id', $request->user()?->lecturer?->id)
                ?->feedback,
            'evaluation_count' => $evaluations->count(),
            'rubric_name' => $this->effectiveRubric()?->name,
            'pass_threshold' => $this->passThreshold(),
            'evaluate_url' => route('exams.evaluate', ['stage' => $this->stage(), 'exam' => $this->id]),
            'panel_size' => $this->panel()->count(),
            'feedback' => $evaluations
                ->filter(fn (ExamEvaluation $evaluation): bool => filled($evaluation->feedback))
                ->map(fn (ExamEvaluation $evaluation): array => [
                    'lecturer' => $evaluation->lecturer->user->name,
                    'body' => $evaluation->feedback,
                    'result_label' => $evaluation->result->label(),
                    'scores' => $evaluation->scores->map(fn (ExamEvaluationScore $score): array => [
                        'label' => $score->criterion_label,
                        'weight' => $score->weight,
                        'score' => $score->score,
                    ])->values(),
                ])
                ->values(),
        ];
    }
}
