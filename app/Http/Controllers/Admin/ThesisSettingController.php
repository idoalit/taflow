<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PerceptionAlignmentMode;
use App\Http\Controllers\Admin\Concerns\ScopesToDepartment;
use App\Http\Controllers\Controller;
use App\Models\DepartmentThesisSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ThesisSettingController extends Controller
{
    use ScopesToDepartment;

    public function edit(Request $request): Response
    {
        Gate::authorize('manageThesisSettings', DepartmentThesisSetting::class);

        $department = $this->department($request);
        $setting = $department->activeThesisSetting();

        return Inertia::render('admin/settings/thesis', [
            'department' => $department->name,
            'setting' => [
                'requires_proposal_exam' => $setting->requires_proposal_exam,
                'min_proposal_guidance_sessions' => $setting->min_proposal_guidance_sessions,
                'min_research_guidance_sessions' => $setting->min_research_guidance_sessions,
                'min_logbook_progress' => $setting->min_logbook_progress,
                'max_supervisors_per_student' => $setting->max_supervisors_per_student,
                'guidance_slot_minutes' => $setting->guidance_slot_minutes,
                'perception_alignment_mode' => $setting->perception_alignment_mode->value,
            ],
            'alignmentModes' => array_map(
                fn (PerceptionAlignmentMode $mode): array => ['value' => $mode->value, 'label' => $mode->label()],
                PerceptionAlignmentMode::cases(),
            ),
        ]);
    }

    /**
     * Settings are versioned by `effective_since`, so saving updates the row in
     * force rather than creating a new one — a program studi tuning its own
     * numbers is not a new policy period.
     */
    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('manageThesisSettings', DepartmentThesisSetting::class);

        $department = $this->department($request);

        $validated = $request->validate([
            'requires_proposal_exam' => ['required', 'boolean'],
            'min_proposal_guidance_sessions' => ['required', 'integer', 'min:0', 'max:50'],
            'min_research_guidance_sessions' => ['required', 'integer', 'min:0', 'max:50'],
            'min_logbook_progress' => ['required', 'integer', 'min:0', 'max:100'],
            'max_supervisors_per_student' => ['required', 'integer', 'min:1', 'max:5'],
            'guidance_slot_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'perception_alignment_mode' => ['required', Rule::enum(PerceptionAlignmentMode::class)],
        ], [], [
            'requires_proposal_exam' => 'aktifkan ujian proposal',
            'min_proposal_guidance_sessions' => 'jumlah minimum bimbingan proposal',
            'min_research_guidance_sessions' => 'jumlah minimum bimbingan hasil',
            'min_logbook_progress' => 'progres logbook minimum',
            'max_supervisors_per_student' => 'jumlah pembimbing',
            'guidance_slot_minutes' => 'durasi slot bimbingan',
            'perception_alignment_mode' => 'pihak kedua pada persamaan persepsi',
        ]);

        $setting = $department->activeThesisSetting();

        if ($setting->exists) {
            $setting->update($validated);
        } else {
            $department->thesisSettings()->create([
                ...$validated,
                'is_active' => true,
                'effective_since' => now()->startOfYear(),
            ]);
        }

        return back()->with('success', 'Pengaturan tugas akhir tersimpan.');
    }
}
