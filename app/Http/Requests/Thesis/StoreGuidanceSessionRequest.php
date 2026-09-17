<?php

namespace App\Http\Requests\Thesis;

use App\Actions\Thesis\SupervisorSlotConflict;
use App\Actions\Thesis\ThesisPhaseAccess;
use App\Enums\GuidanceStage;
use App\Enums\GuidanceTargetType;
use App\Enums\GuidanceType;
use App\Enums\MeetingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGuidanceSessionRequest extends FormRequest
{
    /**
     * Face-to-face sessions carry a schedule and a place; async ones are a
     * running discussion and carry neither.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isFaceToFace = $this->input('guidance_type') === GuidanceType::FaceToFace->value;
        $isOffline = $this->input('meeting_type') === MeetingType::Offline->value;

        return [
            'title' => ['required', 'string', 'max:255'],
            'stage' => ['required', Rule::enum(GuidanceStage::class)->only($this->openStages())],
            'target_type' => ['required', Rule::enum(GuidanceTargetType::class)],
            'guidance_type' => ['required', Rule::enum(GuidanceType::class)],
            'meeting_type' => [Rule::requiredIf($isFaceToFace), 'nullable', Rule::enum(MeetingType::class)],
            'meeting_location' => [Rule::requiredIf($isFaceToFace && $isOffline), 'nullable', 'string', 'max:255'],
            'meeting_link' => [Rule::requiredIf($isFaceToFace && ! $isOffline), 'nullable', 'url', 'max:255'],
            'meeting_scheduled_at' => [Rule::requiredIf($isFaceToFace), 'nullable', 'date', 'after:now'],
            'document' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'judul sesi',
            'stage' => 'tahap bimbingan',
            'target_type' => 'target pembimbing',
            'guidance_type' => 'tipe bimbingan',
            'meeting_type' => 'jenis pertemuan',
            'meeting_location' => 'lokasi pertemuan',
            'meeting_link' => 'link meeting',
            'meeting_scheduled_at' => 'jadwal pertemuan',
            'document' => 'dokumen',
            'notes' => 'catatan awal',
        ];
    }

    /**
     * Restricting the accepted stages here turns a locked choice into an inline
     * field error rather than a refusal that discards the filled-in form.
     *
     * @return array<int, GuidanceStage>
     */
    private function openStages(): array
    {
        $student = $this->user()?->student;

        return $student === null ? [] : ThesisPhaseAccess::forStudent($student)->openStages();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stage.enum' => 'Tahap bimbingan tersebut belum terbuka untuk Anda.',
        ];
    }

    /**
     * A supervisor cannot be booked twice in the same slot, so a clash is
     * reported on the schedule field rather than accepted and sorted out later.
     *
     * @return array<int, callable>
     */
    public function after(SupervisorSlotConflict $conflicts): array
    {
        return [function (Validator $validator) use ($conflicts): void {
            $student = $this->user()?->student;
            $scheduledAt = $this->input('meeting_scheduled_at');

            if ($student === null
                || $validator->errors()->isNotEmpty()
                || $this->input('guidance_type') !== GuidanceType::FaceToFace->value
                || blank($scheduledAt)) {
                return;
            }

            $clash = $conflicts->find(
                $student,
                GuidanceTargetType::from($this->input('target_type')),
                Date::parse($scheduledAt),
            );

            if ($clash === null) {
                return;
            }

            $slot = $student->department->activeThesisSetting()->guidance_slot_minutes;

            $validator->errors()->add(
                'meeting_scheduled_at',
                'Pembimbing Anda sudah memiliki jadwal bimbingan pada '
                .$clash->meeting_scheduled_at->isoFormat('dddd, D MMMM YYYY HH:mm')
                .". Setiap sesi tatap muka memerlukan jeda {$slot} menit, silakan pilih waktu lain."
            );
        }];
    }
}
