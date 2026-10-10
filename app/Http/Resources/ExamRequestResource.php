<?php

namespace App\Http\Resources;

use App\Concerns\FormatsIndonesianDates;
use App\Http\Controllers\DocumentController;
use App\Models\ThesisExamRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Serves both proposal and research exam requests — they share the same
 * two-stage verification shape.
 *
 * @mixin ThesisExamRequest
 */
class ExamRequestResource extends JsonResource
{
    use FormatsIndonesianDates;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stage' => $this->stage()->value,
            'stage_label' => $this->stage()->label(),
            'show_url' => route('exam-verifications.show', [
                'stage' => $this->stage(),
                'examRequest' => $this->id,
            ]),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'awaiting_label' => $this->awaitingLabel(),
            'submitted_at' => $this->shortDate($this->created_at),
            'verified_at' => $this->shortDate($this->admin_verified_at ?? $this->supervisor_verified_at),
            'student_notes' => $this->student_notes,
            'documents' => collect($this->documents ?? [])
                ->map(function (array $document): array {
                    // Asked once: on an object store every check is a round trip.
                    $exists = Storage::disk('documents')->exists($document['path']);

                    return [
                        'name' => $document['name'],
                        'kind' => strtoupper(pathinfo($document['name'], PATHINFO_EXTENSION)) ?: 'FILE',
                        'previewable' => $exists && DocumentController::isPreviewable($document['path']),
                        'url' => $exists
                            ? route('documents.show', [
                                'path' => $document['path'],
                                'name' => $document['name'],
                            ])
                            : null,
                        'preview_url' => $exists
                            ? route('documents.show', [
                                'path' => $document['path'],
                                'name' => $document['name'],
                                'preview' => 1,
                            ])
                            : null,
                    ];
                })
                ->values()
                ->all(),
            'exam' => $this->whenLoaded('exam', fn () => $this->exam === null ? null : [
                'id' => $this->exam->id,
                'title' => $this->exam->title,
                'scheduled_at' => $this->scheduleLabel($this->exam->scheduled_at),
                'venue' => $this->exam->venue,
                'status_label' => $this->exam->status->label(),
                'result_label' => $this->exam->result?->label(),
            ]),
        ];
    }
}
