<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    seedRoles();
});

/**
 * Deleting an account destroys the whole thesis record behind it, so the rule
 * lives in one policy rather than being implied by the absence of a route.
 */
it('never lets anyone delete their own account', function (): void {
    $superadmin = User::factory()->create();
    $superadmin->syncRoles([UserRole::SuperAdmin->value]);

    $denial = Gate::forUser($superadmin)->inspect('delete', $superadmin);

    expect($denial->allowed())->toBeFalse()
        ->and($denial->message())->toContain('tidak dapat dihapus sendiri');
});

it('lets a superadmin delete any account', function (): void {
    $superadmin = User::factory()->create();
    $superadmin->syncRoles([UserRole::SuperAdmin->value]);

    expect(Gate::forUser($superadmin)->allows('delete', makeStudent()->user))->toBeTrue()
        ->and(Gate::forUser($superadmin)->allows('delete', makeLecturer()->user))->toBeTrue();
});

it('lets a program admin delete a student in their own department only', function (): void {
    $department = makeDepartment();
    $admin = makeAdmin($department);

    $mine = makeStudent($department);
    $theirs = makeStudent();

    expect(Gate::forUser($admin)->allows('delete', $mine->user))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('delete', $theirs->user))->toBeFalse();
});

it('does not let a program admin delete a lecturer', function (): void {
    $admin = makeAdmin(makeDepartment());

    expect(Gate::forUser($admin)->allows('delete', makeLecturer()->user))->toBeFalse();
});

it('does not let students or lecturers delete anyone', function (): void {
    $student = makeStudent();
    $lecturer = makeLecturer();
    $victim = makeStudent();

    expect(Gate::forUser($student->user)->allows('delete', $victim->user))->toBeFalse()
        ->and(Gate::forUser($lecturer->user)->allows('delete', $victim->user))->toBeFalse();
});
