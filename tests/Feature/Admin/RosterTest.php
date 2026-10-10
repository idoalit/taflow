<?php

use App\Enums\AuditAction;
use App\Enums\SupervisorRole;
use App\Enums\UserRole;
use App\Imports\LecturerImport;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    seedRoles();
});

/**
 * An admin sitting in their own program studi's workspace.
 *
 * @return array{0: User, 1: Department}
 */
function rosterAdmin(?Department $department = null): array
{
    $department ??= makeDepartment();
    $admin = makeAdmin($department);
    $admin->useWorkspace(new Workspace(UserRole::Admin, $department));

    return [$admin, $department];
}

/**
 * A dosen whose homebase is the given program studi.
 */
function lecturerOf(Department $homebase): Lecturer
{
    $lecturer = makeLecturer();
    $lecturer->departments()->attach($homebase->id, ['is_primary' => true]);

    return $lecturer;
}

describe('adding a mahasiswa', function (): void {
    it('enrols a mahasiswa in the workspace program studi and mails the activation link', function (): void {
        Notification::fake();
        [$admin, $department] = rosterAdmin();

        $this->actingAs($admin)
            ->post(route('admin.students.store'), [
                'name' => 'Sari Wulandari',
                'email' => 'Sari@Kampus.test',
                'student_number' => '21060001',
                'enrollment_year' => 2021,
            ])
            ->assertSessionHasNoErrors();

        $student = Student::where('student_number', '21060001')->sole();

        expect($student->department_id)->toBe($department->id)
            ->and($student->enrollment_year)->toBe(2021)
            ->and($student->user->email)->toBe('sari@kampus.test')
            ->and($student->user->hasRole(UserRole::Student->value))->toBeTrue()
            ->and(AuditLog::where('action', AuditAction::UserCreated->value)->where('user_id', $admin->id)->count())->toBe(1);

        Notification::assertSentTo($student->user, ResetPassword::class);
    });

    it('refuses a NIM or a surel that is already taken', function (): void {
        [$admin, $department] = rosterAdmin();
        $existing = makeStudent($department);

        $this->actingAs($admin)
            ->post(route('admin.students.store'), [
                'name' => 'Orang Lain',
                'email' => $existing->user->email,
                'student_number' => $existing->student_number,
                'enrollment_year' => 2022,
            ])
            ->assertSessionHasErrors(['email', 'student_number']);

        expect(Student::count())->toBe(1);
    });

    it('is closed to a dosen', function (): void {
        $lecturer = assignSupervisor(makeStudent());

        $this->actingAs($lecturer->user)
            ->post(route('admin.students.store'), [
                'name' => 'Sari Wulandari',
                'email' => 'sari@kampus.test',
                'student_number' => '21060001',
                'enrollment_year' => 2021,
            ])
            ->assertForbidden();

        expect(Student::where('student_number', '21060001')->exists())->toBeFalse();
    });
});

describe('adding a dosen', function (): void {
    it('registers a new dosen with the workspace program studi as homebase', function (): void {
        Notification::fake();
        [$admin, $department] = rosterAdmin();

        $this->actingAs($admin)
            ->post(route('admin.lecturers.store'), [
                'name' => 'Dr. Bima Aditya',
                'email' => 'bima@kampus.test',
                'lecturer_number' => '198501012010011001',
                'academic_position' => 'Lektor',
                'max_supervisions' => 6,
            ])
            ->assertSessionHasNoErrors();

        $lecturer = Lecturer::where('lecturer_number', '198501012010011001')->sole();

        expect($lecturer->homebase()?->id)->toBe($department->id)
            ->and($lecturer->max_supervisions)->toBe(6)
            ->and($lecturer->user->hasRole(UserRole::Lecturer->value))->toBeTrue();

        Notification::assertSentTo($lecturer->user, ResetPassword::class);
    });

    it('refuses to register a NIP that already exists', function (): void {
        [$admin] = rosterAdmin();
        $existing = lecturerOf(makeDepartment());

        $this->actingAs($admin)
            ->post(route('admin.lecturers.store'), [
                'name' => 'Nama Lain',
                'email' => 'lain@kampus.test',
                'lecturer_number' => $existing->lecturer_number,
                'max_supervisions' => 5,
            ])
            ->assertSessionHasErrors('lecturer_number');

        expect(User::where('email', 'lain@kampus.test')->exists())->toBeFalse();
    });

    it('finds a dosen another program studi registered by their NIP', function (): void {
        [$admin] = rosterAdmin();
        $homebase = makeDepartment();
        $existing = lecturerOf($homebase);

        $this->actingAs($admin)
            ->get(route('admin.lecturers.create', ['nip' => $existing->lecturer_number]))
            ->assertInertia(fn ($page) => $page
                ->component('admin/lecturers/create')
                ->where('match.id', $existing->id)
                ->where('match.name', $existing->user->name)
                ->where('match.homebase', $homebase->name)
                ->where('match.is_member', false));
    });

    it('offers the new-dosen form for a NIP nobody has registered', function (): void {
        [$admin] = rosterAdmin();

        $this->actingAs($admin)
            ->get(route('admin.lecturers.create', ['nip' => '1999']))
            ->assertInertia(fn ($page) => $page->where('nip', '1999')->where('match', null));
    });
});

describe('adding an existing dosen to the program studi', function (): void {
    it('adds the dosen as a guest, keeps their homebase, and tells those concerned', function (): void {
        [$admin, $department] = rosterAdmin();
        $homebase = makeDepartment();
        $homebaseAdmin = makeAdmin($homebase);
        $lecturer = lecturerOf($homebase);

        $this->actingAs($admin)
            ->post(route('admin.lecturers.membership.store', $lecturer))
            ->assertRedirect(route('admin.lecturers.show', $lecturer));

        $lecturer->load('departments');

        expect($lecturer->departments->modelKeys())->toEqualCanonicalizing([$homebase->id, $department->id])
            ->and($lecturer->homebase()?->id)->toBe($homebase->id)
            ->and($lecturer->user->notifications()->count())->toBe(1)
            ->and($homebaseAdmin->notifications()->sole()->data['message'])->toContain($department->name)
            ->and($admin->notifications()->count())->toBe(0);

        $log = AuditLog::where('action', AuditAction::LecturerAssigned->value)->sole();

        expect($log->user_id)->toBe($admin->id)
            ->and($log->changes)->toMatchArray(['department' => $department->name, 'homebase' => $homebase->name]);
    });

    it('does nothing the second time', function (): void {
        [$admin] = rosterAdmin();
        $lecturer = lecturerOf(makeDepartment());

        $this->actingAs($admin)->post(route('admin.lecturers.membership.store', $lecturer));
        $this->actingAs($admin)->post(route('admin.lecturers.membership.store', $lecturer));

        expect($lecturer->departments()->count())->toBe(2)
            ->and(AuditLog::where('action', AuditAction::LecturerAssigned->value)->count())->toBe(1)
            ->and($lecturer->user->notifications()->count())->toBe(1);
    });

    it('goes through the same door when the dosen arrives in an import', function (): void {
        [$admin, $department] = rosterAdmin();
        $homebase = makeDepartment();
        $homebaseAdmin = makeAdmin($homebase);
        $lecturer = lecturerOf($homebase);

        $import = new LecturerImport($department, $admin);
        $import->collection(collect([
            collect(['nama' => 'Diabaikan', 'email' => 'diabaikan@kampus.test', 'nip' => $lecturer->lecturer_number]),
        ]));

        expect($import->attached)->toBe(1)
            ->and($import->imported)->toBe(0)
            ->and($lecturer->fresh()->homebase()?->id)->toBe($homebase->id)
            ->and($homebaseAdmin->notifications()->count())->toBe(1)
            ->and(AuditLog::where('action', AuditAction::LecturerAssigned->value)->count())->toBe(1);
    });
});

describe('releasing a dosen from the program studi', function (): void {
    it('lets go of a guest dosen and tells them', function (): void {
        [$admin, $department] = rosterAdmin();
        $homebase = makeDepartment();
        $lecturer = lecturerOf($homebase);
        $lecturer->departments()->attach($department->id, ['is_primary' => false]);

        $this->actingAs($admin)
            ->delete(route('admin.lecturers.membership.destroy', $lecturer))
            ->assertRedirect(route('admin.lecturers.index'));

        expect($lecturer->departments()->pluck('departments.id')->all())->toBe([$homebase->id])
            ->and($lecturer->user->notifications()->count())->toBe(1)
            ->and(AuditLog::where('action', AuditAction::LecturerReleased->value)->sole()->changes)
            ->toMatchArray(['department' => $department->name]);
    });

    it('refuses to release a dosen from their own homebase', function (): void {
        [$admin, $department] = rosterAdmin();
        $lecturer = lecturerOf($department);

        $this->actingAs($admin)
            ->delete(route('admin.lecturers.membership.destroy', $lecturer))
            ->assertForbidden();

        expect($lecturer->departments()->count())->toBe(1);
    });

    it('refuses while the dosen still sits on a panel in the program studi', function (): void {
        [$admin, $department] = rosterAdmin();
        $lecturer = lecturerOf(makeDepartment());
        $lecturer->departments()->attach($department->id, ['is_primary' => false]);

        makeStudent($department)->supervisorAssignments()->create([
            'lecturer_id' => $lecturer->id,
            'role' => SupervisorRole::Examiner,
            'order' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.lecturers.show', $lecturer))
            ->assertInertia(fn ($page) => $page
                ->where('release.allowed', false)
                ->where('release.reason', fn (string $reason): bool => str_contains($reason, '1 mahasiswa')));

        $this->actingAs($admin)
            ->delete(route('admin.lecturers.membership.destroy', $lecturer))
            ->assertForbidden();

        expect($lecturer->departments()->count())->toBe(2);
    });

    it('cannot reach a dosen who is not in the program studi', function (): void {
        [$admin] = rosterAdmin();
        $lecturer = lecturerOf(makeDepartment());

        $this->actingAs($admin)
            ->delete(route('admin.lecturers.membership.destroy', $lecturer))
            ->assertNotFound();

        expect($lecturer->departments()->count())->toBe(1);
    });
});

it('marks on the list which dosen have the program studi as homebase', function (): void {
    [$admin, $department] = rosterAdmin();
    $own = lecturerOf($department);
    $homebase = makeDepartment();
    $guest = lecturerOf($homebase);
    $guest->departments()->attach($department->id, ['is_primary' => false]);

    $this->actingAs($admin)
        ->get(route('admin.lecturers.index'))
        ->assertInertia(fn ($page) => $page
            ->has('lecturers', 2)
            ->where('lecturers', fn ($rows): bool => collect($rows)
                ->mapWithKeys(fn (array $row): array => [$row['id'] => [$row['homebase'], $row['is_homebase']]])
                ->all() == [
                    $own->id => [$department->name, true],
                    $guest->id => [$homebase->name, false],
                ]));
});
