<?php

namespace App\Http\Resources;

use App\Concerns\FormatsIndonesianDates;
use App\Models\ThesisProposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ThesisProposal
 */
class ProposalSummaryResource extends JsonResource
{
    use FormatsIndonesianDates;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'submitted_at' => $this->longDateTime($this->created_at),
            'submitted_at_short' => $this->shortDate($this->created_at),
            'student' => $this->whenLoaded('student', fn (): array => [
                'name' => $this->student->user->name,
                'student_number' => $this->student->student_number,
            ]),
        ];
    }
}
