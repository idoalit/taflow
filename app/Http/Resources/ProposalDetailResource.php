<?php

namespace App\Http\Resources;

use App\Concerns\FormatsIndonesianDates;
use App\Enums\ApprovalStatus;
use App\Models\ThesisProposal;
use Illuminate\Http\Request;

/**
 * @mixin ThesisProposal
 */
class ProposalDetailResource extends ProposalSummaryResource
{
    use FormatsIndonesianDates;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'background' => $this->background,
            'problem_formulation' => $this->problem_formulation,
            'objectives' => $this->objectives,
            'scope' => $this->scope,
            'methodology' => $this->methodology,
            'roadmap_alignment' => $this->roadmap_alignment,
            'document' => DocumentResource::fromColumns(
                $this->document_path,
                $this->document_name,
                $this->document_size,
                $this->created_at,
            ),
            'approved_at' => $this->longDateTime($this->approved_at),
            'rejection_reason' => $this->rejection_reason,
            'student' => new StudentSummaryResource($this->student),
            'supervisors' => LecturerSummaryResource::collection($this->student->supervisors),
            'alignment_summary' => $this->alignmentSummary(),
            'history' => $this->history(),
        ];
    }

    /**
     * The "Hasil Persamaan Persepsi" card: what the supervisors said about the
     * proposal, collapsed into one line the student can act on.
     */
    private function alignmentSummary(): ?string
    {
        $alignments = $this->perceptionAlignments;

        if ($alignments->isEmpty()) {
            return null;
        }

        if ($alignments->contains(fn ($alignment) => $alignment->status === ApprovalStatus::Pending)) {
            return 'Menunggu tanggapan pembimbing terhadap topik dan metodologi yang Anda ajukan.';
        }

        $feedback = $alignments
            ->filter(fn ($alignment) => filled($alignment->feedback))
            ->map(fn ($alignment) => $alignment->lecturer->user->name.': '.$alignment->feedback)
            ->implode(' ');

        return $feedback !== '' ? $feedback : 'Topik dan metodologi telah disetujui seluruh pembimbing.';
    }

    /**
     * @return array<int, array{label: string, detail: string, current: bool}>
     */
    private function history(): array
    {
        $entries = [];

        if ($this->approved_at !== null) {
            $entries[] = [
                'label' => $this->status->label(),
                'detail' => $this->longDateTime($this->approved_at)
                    .($this->approvedBy?->user?->name !== null ? ' · oleh '.$this->approvedBy->user->name : ''),
                'current' => true,
            ];
        }

        if (filled($this->rejection_reason)) {
            $entries[] = [
                'label' => 'Perlu Revisi',
                'detail' => $this->rejection_reason,
                'current' => $this->approved_at === null,
            ];
        }

        $entries[] = [
            'label' => 'Diajukan',
            'detail' => $this->longDateTime($this->created_at) ?? '—',
            'current' => $entries === [],
        ];

        return $entries;
    }
}
