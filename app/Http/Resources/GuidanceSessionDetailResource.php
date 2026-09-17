<?php

namespace App\Http\Resources;

use App\Enums\MeetingType;
use App\Models\GuidanceSession;
use Illuminate\Http\Request;

/**
 * @mixin GuidanceSession
 */
class GuidanceSessionDetailResource extends GuidanceSessionSummaryResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'notes' => $this->notes,
            'feedback' => $this->feedback,
            'completion_notes' => $this->completion_notes,
            'target_type_label' => $this->target_type->label(),
            'meeting_type_label' => $this->meeting_type?->label(),
            'location_label' => $this->meeting_type === MeetingType::OnlineMeeting ? 'Link Meeting' : 'Lokasi Pertemuan',
            'location_value' => $this->meeting_type === MeetingType::OnlineMeeting
                ? $this->meeting_link
                : $this->meeting_location,
            'scheduled_at' => $this->scheduleLabel($this->meeting_scheduled_at),
            'created_at' => $this->shortDateTime($this->created_at),
            'completed_at' => $this->shortDateTime($this->completed_at),
            'cancellation_reason' => $this->cancellation_reason,
            'student' => new StudentSummaryResource($this->student),
            'supervisors' => LecturerSummaryResource::collection($this->participatingSupervisors()),
            'document' => DocumentResource::fromColumns(
                $this->document_path,
                $this->document_name,
                null,
                $this->created_at,
            ),
            'messages' => GuidanceMessageResource::collection($this->messages),
        ];
    }
}
