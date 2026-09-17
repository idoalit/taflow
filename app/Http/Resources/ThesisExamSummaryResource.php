<?php

namespace App\Http\Resources;

use App\Concerns\FormatsIndonesianDates;
use App\Models\ThesisExam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ThesisExam
 */
class ThesisExamSummaryResource extends JsonResource
{
    use FormatsIndonesianDates;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lecturerId = $request->user()?->lecturer?->id;
        $mine = $this->evaluations->firstWhere('lecturer_id', $lecturerId);

        return [
            'id' => $this->id,
            'stage' => $this->stage()->value,
            'stage_label' => $this->stage()->label(),
            'title' => $this->title,
            'student' => $this->student->user->name,
            'student_number' => $this->student->student_number,
            'scheduled_at' => $this->scheduleLabel($this->scheduled_at),
            'scheduled_at_short' => $this->shortDate($this->scheduled_at),
            'venue' => $this->venue,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'result_label' => $this->result?->label(),
            'my_evaluation' => $mine === null ? 'Belum diisi' : "Terisi · {$mine->final_score}",
            'show_url' => route('exams.show', ['stage' => $this->stage(), 'exam' => $this->id]),
        ];
    }
}
