<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\ProposalStatus;
use Carbon\CarbonInterface;
use Database\Factories\ThesisProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $student_id
 * @property int $department_id
 * @property string $title
 * @property string $background
 * @property string $problem_formulation
 * @property string $objectives
 * @property string|null $scope
 * @property string|null $methodology
 * @property string|null $roadmap_alignment
 * @property string|null $document_path
 * @property string|null $document_name
 * @property int|null $document_size
 * @property ProposalStatus $status
 * @property int|null $approved_by
 * @property CarbonInterface|null $approved_at
 * @property string|null $rejection_reason
 * @property CarbonInterface|null $agreed_at
 */
#[Fillable([
    'student_id', 'department_id', 'title', 'background', 'problem_formulation', 'objectives',
    'scope', 'methodology', 'roadmap_alignment', 'document_path', 'document_name', 'document_size',
    'status', 'approved_by', 'approved_at', 'rejection_reason', 'agreed_at',
])]
class ThesisProposal extends Model
{
    /** @use HasFactory<ThesisProposalFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProposalStatus::class,
            'approved_at' => 'datetime',
            'agreed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Lecturer, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class, 'approved_by');
    }

    /** @return HasMany<PerceptionAlignment, $this> */
    public function perceptionAlignments(): HasMany
    {
        return $this->hasMany(PerceptionAlignment::class);
    }

    public function isApproved(): bool
    {
        return $this->status === ProposalStatus::Approved;
    }

    public function isAwaitingReview(): bool
    {
        return in_array($this->status, [ProposalStatus::Submitted, ProposalStatus::UnderReview], true);
    }

    /**
     * Where the Persamaan Persepsi round stands, phrased the way the list
     * screen labels it.
     */
    public function alignmentStatusLabel(): string
    {
        $alignments = $this->perceptionAlignments;

        if ($alignments->isEmpty()) {
            return 'Belum Dimulai';
        }

        if ($alignments->contains(fn (PerceptionAlignment $a): bool => $a->status === ApprovalStatus::Rejected)) {
            return 'Ditolak';
        }

        $pending = $alignments
            ->filter(fn (PerceptionAlignment $a): bool => $a->status === ApprovalStatus::Pending)
            ->sortBy('supervisor_order')
            ->first();

        return $pending === null
            ? 'Selesai'
            : "Menunggu Feedback Pembimbing {$pending->supervisor_order}";
    }
}
