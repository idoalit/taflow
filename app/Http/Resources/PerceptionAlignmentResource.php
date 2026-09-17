<?php

namespace App\Http\Resources;

use App\Concerns\FormatsIndonesianDates;
use App\Enums\ApprovalStatus;
use App\Models\PerceptionAlignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PerceptionAlignment
 */
class PerceptionAlignmentResource extends JsonResource
{
    use FormatsIndonesianDates;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'supervisor_order' => $this->supervisor_order,
            'lecturer_name' => $this->lecturer->user->name,
            'lecturer_initials' => $this->lecturer->user->initials(),
            'status' => $this->status->value,
            'feedback' => $this->feedback,
            'responded_at' => $this->shortDateTime($this->responded_at),
            'is_mine' => $request->user()?->lecturer?->id === $this->lecturer_id,
            'is_my_turn' => $this->status === ApprovalStatus::Pending && $this->isMyTurn(),
        ];
    }
}
