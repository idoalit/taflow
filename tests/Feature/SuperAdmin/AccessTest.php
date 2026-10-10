<?php

use App\Enums\UserRole;
use App\Models\User;

beforeEach(function (): void {
    seedRoles();
});

/**
 * Every system screen, so a route added without the gate shows up here rather
 * than in production.
 */
dataset('system screens', [
    'program studi' => ['superadmin.departments.index'],
    'pengguna' => ['superadmin.users.index'],
    'peran & izin' => ['superadmin.roles.index'],
    'log aktivitas' => ['superadmin.activity-logs.index'],
    'siaran' => ['superadmin.broadcasts.index'],
    'pengaturan sistem' => ['superadmin.settings.edit'],
]);

it('redirects a guest to the login page', function (string $route): void {
    $this->get(route($route))->assertRedirect(route('login'));
})->with('system screens');

it('opens the system screens to a superadmin', function (string $route): void {
    $this->actingAs(makeSuperAdmin())->get(route($route))->assertOk();
})->with('system screens');

it('forbids the system screens to an Admin Prodi', function (string $route): void {
    $this->actingAs(makeAdmin(makeDepartment()))->get(route($route))->assertForbidden();
})->with('system screens');

it('forbids the system screens to a dosen', function (string $route): void {
    $lecturer = assignSupervisor(makeStudent());

    $this->actingAs($lecturer->user)->get(route($route))->assertForbidden();
})->with('system screens');

it('forbids the system screens to a mahasiswa', function (string $route): void {
    $this->actingAs(makeStudent()->user)->get(route($route))->assertForbidden();
})->with('system screens');

it('forbids the system screens to a superadmin sitting in an Admin Prodi seat', function (string $route): void {
    $department = makeDepartment();

    $this->actingAs(makeSuperAdmin())
        ->withSession(inWorkspace(UserRole::Admin, $department))
        ->get(route($route))
        ->assertForbidden();
})->with('system screens');

it('tells a superadmin in an Admin Prodi seat how to get back', function (): void {
    $department = makeDepartment();

    $this->actingAs(makeSuperAdmin())
        ->withSession(inWorkspace(UserRole::Admin, $department))
        ->get(route('superadmin.departments.index'))
        ->assertInertia(fn ($page) => $page
            ->component('error')
            ->where('reason', fn (string $reason): bool => str_contains($reason, 'workspace Superadmin')
                && str_contains($reason, 'pemilih workspace di kanan atas')));
});

it('tells a superadmin in the Superadmin seat that the prodi screens need a prodi', function (): void {
    makeDepartment();

    $this->actingAs(makeSuperAdmin())
        ->get(route('admin.students.index'))
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page
            ->component('error')
            ->where('reason', fn (string $reason): bool => str_contains($reason, 'workspace Admin Program Studi')));
});

it('forbids a write to somebody who is not a superadmin', function (): void {
    $admin = makeAdmin(makeDepartment());

    $this->actingAs($admin)
        ->post(route('superadmin.users.store'), [
            'name' => 'Penyusup',
            'email' => 'penyusup@kampus.test',
            'role' => UserRole::SuperAdmin->value,
        ])
        ->assertForbidden();

    expect(User::where('email', 'penyusup@kampus.test')->exists())->toBeFalse();
});
