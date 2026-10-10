<?php

use App\Enums\AuditAction;
use App\Enums\SupervisorRole;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Lecturer;

beforeEach(function (): void {
    seedRoles();
});

/**
 * A dosen with a homebase and one more program studi they work in.
 *
 * @return array{0: Lecturer, 1: Department, 2: Department}
 */
function lecturerInTwoDepartments(): array
{
    $homebase = makeDepartment();
    $guest = makeDepartment();

    $lecturer = makeLecturer();
    $lecturer->departments()->attach([
        $homebase->id => ['is_primary' => true],
        $guest->id => ['is_primary' => false],
    ]);

    return [$lecturer, $homebase, $guest];
}

it('shows the superadmin where a dosen belongs', function (): void {
    [$lecturer, $homebase, $guest] = lecturerInTwoDepartments();

    $this->actingAs(makeSuperAdmin())
        ->get(route('superadmin.users.show', $lecturer->user))
        ->assertInertia(fn ($page) => $page
            ->where('account.lecturer.homebase_id', $homebase->id)
            ->where('account.lecturer.department_ids', fn ($ids): bool => collect($ids)->sort()->values()->all()
                === collect([$homebase->id, $guest->id])->sort()->values()->all()));
});

it('moves the homebase and adds a program studi in one save', function (): void {
    [$lecturer, $homebase, $guest] = lecturerInTwoDepartments();
    $third = makeDepartment();
    $superadmin = makeSuperAdmin();

    $this->actingAs($superadmin)
        ->put(route('superadmin.users.lecturer-departments.update', $lecturer->user), [
            'department_ids' => [$homebase->id, $guest->id, $third->id],
            'homebase_id' => $guest->id,
        ])
        ->assertSessionHasNoErrors();

    $lecturer->load('departments');

    expect($lecturer->departments)->toHaveCount(3)
        ->and($lecturer->homebase()?->id)->toBe($guest->id)
        ->and($lecturer->departments->where('pivot.is_primary', true))->toHaveCount(1);

    $log = AuditLog::where('action', AuditAction::LecturerDepartmentsUpdated->value)->sole();

    expect($log->user_id)->toBe($superadmin->id)
        ->and($log->changes['homebase'])->toBe($guest->name);
});

it('writes nothing to the trail when nothing changed', function (): void {
    [$lecturer, $homebase, $guest] = lecturerInTwoDepartments();

    $this->actingAs(makeSuperAdmin())
        ->put(route('superadmin.users.lecturer-departments.update', $lecturer->user), [
            'department_ids' => [$guest->id, $homebase->id],
            'homebase_id' => $homebase->id,
        ])
        ->assertSessionHasNoErrors();

    expect(AuditLog::where('action', AuditAction::LecturerDepartmentsUpdated->value)->exists())->toBeFalse();
});

it('requires a homebase among the chosen program studi', function (string $case, string $field): void {
    [$lecturer, $homebase, $guest] = lecturerInTwoDepartments();

    $payload = match ($case) {
        'no program studi at all' => ['department_ids' => [], 'homebase_id' => $homebase->id],
        'a homebase that is not ticked' => ['department_ids' => [$guest->id], 'homebase_id' => $homebase->id],
        'no homebase' => ['department_ids' => [$homebase->id, $guest->id]],
    };

    $this->actingAs(makeSuperAdmin())
        ->put(route('superadmin.users.lecturer-departments.update', $lecturer->user), $payload)
        ->assertSessionHasErrors($field);

    expect($lecturer->fresh()->homebase()?->id)->toBe($homebase->id)
        ->and($lecturer->departments()->count())->toBe(2);
})->with([
    ['no program studi at all', 'department_ids'],
    ['a homebase that is not ticked', 'homebase_id'],
    ['no homebase', 'homebase_id'],
]);

it('refuses to take away a program studi where the dosen still sits on a panel', function (): void {
    [$lecturer, $homebase, $guest] = lecturerInTwoDepartments();

    makeStudent($guest)->supervisorAssignments()->create([
        'lecturer_id' => $lecturer->id,
        'role' => SupervisorRole::Supervisor,
        'order' => 1,
    ]);

    $this->actingAs(makeSuperAdmin())
        ->put(route('superadmin.users.lecturer-departments.update', $lecturer->user), [
            'department_ids' => [$homebase->id],
            'homebase_id' => $homebase->id,
        ])
        ->assertSessionHasErrors('department_ids');

    expect($lecturer->departments()->count())->toBe(2);
});

it('is closed to an admin prodi, and to an account that is not a dosen', function (): void {
    [$lecturer, $homebase] = lecturerInTwoDepartments();
    $payload = ['department_ids' => [$homebase->id], 'homebase_id' => $homebase->id];

    $this->actingAs(makeAdmin($homebase))
        ->put(route('superadmin.users.lecturer-departments.update', $lecturer->user), $payload)
        ->assertForbidden();

    $this->actingAs(makeSuperAdmin())
        ->put(route('superadmin.users.lecturer-departments.update', makeStudent()->user), $payload)
        ->assertNotFound();

    expect($lecturer->departments()->count())->toBe(2);
});
