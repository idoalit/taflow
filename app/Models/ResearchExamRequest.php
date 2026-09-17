<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\ExamStage;
use Carbon\CarbonInterface;
use Database\Factories\ResearchExamRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $student_id
 * @property int $thesis_proposal_id
 * @property int|null $research_exam_id
 * @property string|null $student_notes
 * @property array<int, array{name: string, path: string}>|null $documents
 * @property ApprovalStatus $status
 * @property ApprovalStatus $supervisor_status
 * @property int|null $supervisor_verified_by
 * @property CarbonInterface|null $supervisor_verified_at
 * @property string|null $supervisor_notes
 * @property ApprovalStatus $admin_status
 * @property int|null $admin_verified_by
 * @property CarbonInterface|null $admin_verified_at
 * @property string|null $admin_notes
 */
#[Fillable([
    'student_id', 'thesis_proposal_id', 'research_exam_id', 'student_notes', 'documents', 'status',
    'supervisor_status', 'supervisor_verified_by', 'supervisor_verified_at', 'supervisor_notes',
    'admin_status', 'admin_verified_by', 'admin_verified_at', 'admin_notes',
])]
class ResearchExamRequest extends ThesisExamRequest
{
    /** @use HasFactory<ResearchExamRequestFactory> */
    use HasFactory;

    public function stage(): ExamStage
    {
        return ExamStage::Research;
    }

    /** @return BelongsTo<ResearchExam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(ResearchExam::class, 'research_exam_id');
    }
}
