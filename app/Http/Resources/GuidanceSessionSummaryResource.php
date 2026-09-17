<?php

namespace App\Http\Resources;

use App\Concerns\FormatsIndonesianDates;
use App\Enums\GuidanceDisplayStatus;
use App\Enums\GuidanceType;
use App\Models\GuidanceSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GuidanceSession
 */
class GuidanceSessionSummaryResource extends JsonResource
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
            'meta' => $this->meta(),
            'stage' => $this->stage->value,
            'stage_label' => $this->stage->label(),
            'guidance_type' => $this->guidance_type->value,
            'guidance_type_label' => $this->guidance_type->label(),
            'status' => $this->displayStatus()->value,
            'status_label' => $this->displayStatus()->label(),
            'student' => $this->whenLoaded('student', fn (): array => [
                'name' => $this->student->user->name,
                'student_number' => $this->student->student_number,
            ]),
        ];
    }

    /**
     * The single line under the title: a schedule for anything upcoming, the
     * completion date once it is done.
     */
    protected function meta(): string
    {
        return match ($this->displayStatus()) {
            GuidanceDisplayStatus::Completed => 'Selesai · '.$this->shortDate($this->completed_at ?? $this->updated_at),
            GuidanceDisplayStatus::Cancelled => 'Dibatalkan · '.$this->shortDate($this->cancelled_at ?? $this->updated_at),
            GuidanceDisplayStatus::Ongoing => $this->guidance_type === GuidanceType::OnlineAsync
                ? 'Diskusi daring · dibuat '.$this->shortDate($this->created_at)
                : ($this->scheduleLabel($this->meeting_scheduled_at) ?? 'Sedang berlangsung'),
            GuidanceDisplayStatus::Scheduled => $this->scheduleLabel($this->meeting_scheduled_at) ?? 'Terjadwal',
        };
    }
}
