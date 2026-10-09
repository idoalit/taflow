<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\ThesisRuleValidation;
use App\Http\Controllers\Admin\Concerns\ScopesToDepartment;
use App\Http\Controllers\Controller;
use App\Models\DepartmentThesisSetting;
use App\Support\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ThesisSettingController extends Controller
{
    use ScopesToDepartment, ThesisRuleValidation;

    public function edit(Request $request): Response
    {
        Gate::authorize('manageThesisSettings', DepartmentThesisSetting::class);

        $department = $this->department($request);
        $setting = $department->activeThesisSetting();

        return Inertia::render('admin/settings/thesis', [
            'department' => $department->name,
            'setting' => $setting->only(SystemSettings::THESIS_RULES),
            'alignmentModes' => $this->alignmentModeOptions(),
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

        $validated = $request->validate($this->thesisRules(), [], $this->thesisRuleAttributes());

        $setting = $department->activeThesisSetting();

        // A program studi that has never saved is running on the fallback
        // instance, which this turns into its first row.
        if (! $setting->exists) {
            $setting->fill(['is_active' => true, 'effective_since' => now()->startOfYear()]);
        }

        $setting->fill($validated)->save();

        return back()->with('success', 'Pengaturan tugas akhir tersimpan.');
    }
}
