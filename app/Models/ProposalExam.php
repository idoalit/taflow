<?php

namespace App\Models;

use App\Enums\ExamStage;
use App\Policies\ThesisExamPolicy;
use Database\Factories\ProposalExamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int|null $thesis_proposal_id
 */
#[Fillable(['student_id', 'thesis_proposal_id', 'scheduled_by', 'rubric_id', 'pass_threshold', 'title', 'description', 'status',
    'scheduled_at', 'duration_minutes', 'venue', 'preparation_notes', 'final_score',
    'result', 'evaluation_notes', 'feedback', 'started_at', 'completed_at',
    'released_at', 'released_by', 'verification_token', ])]
#[UsePolicy(ThesisExamPolicy::class)]
class ProposalExam extends ThesisExam
{
    /** @use HasFactory<ProposalExamFactory> */
    use HasFactory;

    public function stage(): ExamStage
    {
        return ExamStage::Proposal;
    }
}
