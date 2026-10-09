<?php

namespace App\Concerns;

use App\Enums\PerceptionAlignmentMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The rules a program studi sets for its tugas akhir. Shared by the Admin
 * Prodi's own form and the superadmin's defaults, so a default can never be
 * saved that a program studi would not be allowed to choose.
 */
trait ThesisRuleValidation
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function thesisRules(): array
    {
        return [
            'requires_proposal_exam' => ['required', 'boolean'],
            'min_proposal_guidance_sessions' => ['required', 'integer', 'min:0', 'max:50'],
            'min_research_guidance_sessions' => ['required', 'integer', 'min:0', 'max:50'],
            'min_logbook_progress' => ['required', 'integer', 'min:0', 'max:100'],
            'max_supervisors_per_student' => ['required', 'integer', 'min:1', 'max:5'],
            'guidance_slot_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'perception_alignment_mode' => ['required', Rule::enum(PerceptionAlignmentMode::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function thesisRuleAttributes(): array
    {
        return [
            'requires_proposal_exam' => 'aktifkan ujian proposal',
            'min_proposal_guidance_sessions' => 'jumlah minimum bimbingan proposal',
            'min_research_guidance_sessions' => 'jumlah minimum bimbingan hasil',
            'min_logbook_progress' => 'progres logbook minimum',
            'max_supervisors_per_student' => 'jumlah pembimbing',
            'guidance_slot_minutes' => 'durasi slot bimbingan',
            'perception_alignment_mode' => 'pihak kedua pada persamaan persepsi',
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function alignmentModeOptions(): array
    {
        return array_map(
            fn (PerceptionAlignmentMode $mode): array => ['value' => $mode->value, 'label' => $mode->label()],
            PerceptionAlignmentMode::cases(),
        );
    }
}
