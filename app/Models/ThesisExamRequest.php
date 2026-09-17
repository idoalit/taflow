<?php

namespace App\Models;

use App\Concerns\VerifiesExamRequest;
use App\Enums\ApprovalStatus;
use App\Enums\ExamStage;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's request to sit an exam, of either stage. Both go through the same
 * two gates — supervisor, then program admin — and differ only in which exam
 * they can be scheduled into.
 *
 * @property int $id
 * @property int $student_id
 * @property int $thesis_proposal_id
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
 * @property CarbonInterface $created_at
 * @property-read Student $student
 * @property-read ThesisProposal $thesisProposal
 * @property-read ThesisExam|null $exam
 */
abstract class ThesisExamRequest extends Model
{
    use VerifiesExamRequest;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'documents' => 'array',
            'status' => ApprovalStatus::class,
            'supervisor_status' => ApprovalStatus::class,
            'admin_status' => ApprovalStatus::class,
            'supervisor_verified_at' => 'datetime',
            'admin_verified_at' => 'datetime',
        ];
    }

    /**
     * Which exam this request is for. Drives labels and routes. The matching
     * exam relation lives on each subclass, which knows its own foreign key.
     */
    abstract public function stage(): ExamStage;

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<ThesisProposal, $this> */
    public function thesisProposal(): BelongsTo
    {
        return $this->belongsTo(ThesisProposal::class);
    }
}
