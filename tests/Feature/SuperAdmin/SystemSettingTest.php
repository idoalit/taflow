<?php

use App\Enums\PerceptionAlignmentMode;
use App\Enums\UserRole;
use App\Models\Department;
use App\Support\SystemSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedRoles();
    Storage::fake('public');
});

/**
 * @return array<string, mixed>
 */
function thesisRules(array $overrides = []): array
{
    return [
        'requires_proposal_exam' => false,
        'min_proposal_guidance_sessions' => 8,
        'min_research_guidance_sessions' => 5,
        'min_logbook_progress' => 75,
        'max_supervisors_per_student' => 3,
        'guidance_slot_minutes' => 30,
        'perception_alignment_mode' => PerceptionAlignmentMode::Skip->value,
        ...$overrides,
    ];
}

describe('institution identity', function (): void {
    it('falls back to the deployment configuration until something is saved', function (): void {
        config(['institution.name' => 'Institut Bawaan', 'institution.city' => 'Semarang']);

        expect(app(SystemSettings::class)->institution())
            ->toMatchArray(['name' => 'Institut Bawaan', 'city' => 'Semarang', 'logo_file' => null]);
    });

    it('puts the saved identity on every letterhead', function (): void {
        config(['institution.name' => 'Institut Bawaan', 'institution.unit' => 'Unit Bawaan']);

        $this->actingAs(makeSuperAdmin())
            ->post(route('superadmin.settings.institution.update'), [
                'name' => 'Universitas Contoh',
                'unit' => '',
                'address' => 'Jl. Pendidikan No. 1',
                'city' => 'Surakarta',
            ])
            ->assertSessionHas('success', 'Identitas institusi tersimpan.');

        // A field the superadmin cleared stays cleared: the configuration is
        // only the starting point, not something that seeps back in.
        expect(app(SystemSettings::class)->institution())->toMatchArray([
            'name' => 'Universitas Contoh',
            'unit' => null,
            'address' => 'Jl. Pendidikan No. 1',
            'city' => 'Surakarta',
        ]);
    });

    it('requires the institution to have a name', function (): void {
        $this->actingAs(makeSuperAdmin())
            ->post(route('superadmin.settings.institution.update'), ['name' => ''])
            ->assertSessionHasErrors('name');
    });

    it('stores an uploaded logo and replaces the one before it', function (): void {
        $superadmin = makeSuperAdmin();

        $this->actingAs($superadmin)->post(route('superadmin.settings.institution.update'), [
            'name' => 'Universitas Contoh',
            'logo' => UploadedFile::fake()->image('lama.png', 120, 120),
        ]);

        $first = app(SystemSettings::class)->get(SystemSettings::INSTITUTION)['logo_path'];

        $this->actingAs($superadmin)->post(route('superadmin.settings.institution.update'), [
            'name' => 'Universitas Contoh',
            'logo' => UploadedFile::fake()->image('baru.jpg', 120, 120),
        ]);

        $second = app(SystemSettings::class)->get(SystemSettings::INSTITUTION)['logo_path'];

        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
        expect(app(SystemSettings::class)->institution()['logo_file'])
            ->toBe(Storage::disk('public')->path($second));
    });

    it('keeps the logo when the identity is saved without a new one', function (): void {
        $superadmin = makeSuperAdmin();

        $this->actingAs($superadmin)->post(route('superadmin.settings.institution.update'), [
            'name' => 'Universitas Contoh',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $path = app(SystemSettings::class)->get(SystemSettings::INSTITUTION)['logo_path'];

        $this->actingAs($superadmin)->post(route('superadmin.settings.institution.update'), [
            'name' => 'Universitas Contoh Baru',
        ]);

        Storage::disk('public')->assertExists($path);
        expect(app(SystemSettings::class)->get(SystemSettings::INSTITUTION)['logo_path'])->toBe($path);
    });

    it('rejects a logo that is not a raster image', function (): void {
        $this->actingAs(makeSuperAdmin())
            ->post(route('superadmin.settings.institution.update'), [
                'name' => 'Universitas Contoh',
                'logo' => UploadedFile::fake()->createWithContent(
                    'logo.svg',
                    '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
                ),
            ])
            ->assertSessionHasErrors('logo');

        expect(Storage::disk('public')->allFiles())->toBe([]);
    });

    it('removes the uploaded logo on request', function (): void {
        $superadmin = makeSuperAdmin();

        $this->actingAs($superadmin)->post(route('superadmin.settings.institution.update'), [
            'name' => 'Universitas Contoh',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $path = app(SystemSettings::class)->get(SystemSettings::INSTITUTION)['logo_path'];

        $this->actingAs($superadmin)
            ->delete(route('superadmin.settings.institution.logo.destroy'))
            ->assertSessionHas('success', 'Logo dihapus.');

        Storage::disk('public')->assertMissing($path);
        expect(app(SystemSettings::class)->institution())
            ->toMatchArray(['name' => 'Universitas Contoh', 'logo_file' => null]);
    });
});

describe('default thesis rules', function (): void {
    it('governs a program studi that has not saved rules of its own', function (): void {
        $unconfigured = Department::factory()->create();

        $this->actingAs(makeSuperAdmin())
            ->put(route('superadmin.settings.thesis-defaults.update'), thesisRules())
            ->assertRedirect();

        $setting = $unconfigured->activeThesisSetting();

        expect($setting->requires_proposal_exam)->toBeFalse()
            ->and($setting->min_proposal_guidance_sessions)->toBe(8)
            ->and($setting->max_supervisors_per_student)->toBe(3)
            ->and($setting->perception_alignment_mode)->toBe(PerceptionAlignmentMode::Skip);
    });

    it('leaves a program studi with its own rules untouched', function (): void {
        $configured = makeDepartment(['min_proposal_guidance_sessions' => 2]);

        $this->actingAs(makeSuperAdmin())
            ->put(route('superadmin.settings.thesis-defaults.update'), thesisRules());

        expect($configured->activeThesisSetting()->min_proposal_guidance_sessions)->toBe(2);
    });

    it('becomes the starting point of an Admin Prodi\'s first save', function (): void {
        $department = Department::factory()->create();
        $admin = makeAdmin($department);

        $this->actingAs(makeSuperAdmin())
            ->put(route('superadmin.settings.thesis-defaults.update'), thesisRules());

        $this->actingAs($admin)
            ->withSession(inWorkspace(UserRole::Admin, $department))
            ->get(route('admin.thesis-settings.edit'))
            ->assertInertia(fn ($page) => $page
                ->where('setting.min_proposal_guidance_sessions', 8)
                ->where('setting.perception_alignment_mode', 'skip'));
    });

    it('holds a default to the same limits as a program studi\'s own rules', function (): void {
        $this->actingAs(makeSuperAdmin())
            ->put(route('superadmin.settings.thesis-defaults.update'), thesisRules(['max_supervisors_per_student' => 9]))
            ->assertSessionHasErrors('max_supervisors_per_student');

        expect(app(SystemSettings::class)->thesisDefaults()['max_supervisors_per_student'])->toBe(2);
    });
});
