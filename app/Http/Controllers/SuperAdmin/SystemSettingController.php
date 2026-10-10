<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Concerns\ThesisRuleValidation;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Support\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengaturan Sistem: the institution's letterhead, and the thesis rules a
 * program studi starts from before it sets its own.
 */
class SystemSettingController extends Controller
{
    use ThesisRuleValidation;

    private const TEXT_FIELDS = ['name', 'unit', 'address', 'phone', 'website', 'city'];

    public function edit(SystemSettings $settings): Response
    {
        Gate::authorize('manage', SystemSetting::class);

        return Inertia::render('superadmin/settings/index', [
            'institution' => [
                ...$settings->institution(),
                'logo' => $settings->logo(),
                'has_uploaded_logo' => $settings->uploadedLogoPath() !== null,
            ],
            'thesisDefaults' => $settings->thesisDefaults(),
            'alignmentModes' => $this->alignmentModeOptions(),
        ]);
    }

    public function updateInstitution(Request $request, SystemSettings $settings): RedirectResponse
    {
        Gate::authorize('manage', SystemSetting::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            // Raster only: the logo is printed into PDFs and shown back in the
            // browser, and an SVG is a document that can carry script.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
        ], [
            'logo.mimes' => 'Logo harus berformat PNG atau JPG.',
            'logo.max' => 'Ukuran logo paling besar 1 MB.',
        ], [
            'name' => 'nama institusi',
            'unit' => 'unit',
            'address' => 'alamat',
            'phone' => 'telepon',
            'website' => 'situs',
            'city' => 'kota',
            'logo' => 'logo',
        ]);

        $logoPath = $settings->uploadedLogoPath();

        if ($request->hasFile('logo')) {
            $stored = $request->file('logo')->store('institution', 'documents');

            abort_if($stored === false, 500, 'Gagal menyimpan logo yang diunggah.');

            if ($logoPath !== null) {
                Storage::disk('documents')->delete($logoPath);
            }

            $logoPath = $stored;
        }

        $setting = $settings->put(SystemSettings::INSTITUTION, [
            ...Arr::only($validated, self::TEXT_FIELDS),
            'logo_path' => $logoPath,
        ]);

        $this->audit($request, $setting, 'Identitas institusi');

        return back()->with('success', 'Identitas institusi tersimpan.');
    }

    /**
     * Drops the uploaded logo, which puts the one named in configuration — or
     * none — back on the letterhead.
     */
    public function destroyLogo(Request $request, SystemSettings $settings): RedirectResponse
    {
        Gate::authorize('manage', SystemSetting::class);

        $logoPath = $settings->uploadedLogoPath();

        if ($logoPath === null) {
            return back();
        }

        Storage::disk('documents')->delete($logoPath);

        $setting = $settings->put(SystemSettings::INSTITUTION, [
            ...($settings->get(SystemSettings::INSTITUTION) ?? []),
            'logo_path' => null,
        ]);

        $this->audit($request, $setting, 'Logo institusi dihapus');

        return back()->with('success', 'Logo dihapus.');
    }

    public function updateThesisDefaults(Request $request, SystemSettings $settings): RedirectResponse
    {
        Gate::authorize('manage', SystemSetting::class);

        $validated = $request->validate($this->thesisRules(), [], $this->thesisRuleAttributes());

        $setting = $settings->put(SystemSettings::THESIS_DEFAULTS, $validated);

        $this->audit($request, $setting, 'Aturan tugas akhir bawaan');

        return back()->with('success', 'Aturan bawaan tersimpan. Program studi yang sudah mengatur sendiri tidak berubah.');
    }

    private function audit(Request $request, SystemSetting $setting, string $group): void
    {
        AuditLog::record(
            $setting,
            AuditAction::SystemSettingUpdated,
            $request->user(),
            changes: ['name' => $group],
            request: $request,
        );
    }
}
