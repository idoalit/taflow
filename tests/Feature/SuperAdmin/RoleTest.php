<?php

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Support\Workspace;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    seedRoles();
});

it('shows what each role is allowed, and which permissions are locked', function (): void {
    $this->actingAs(makeSuperAdmin())
        ->get(route('superadmin.roles.index'))
        ->assertInertia(fn ($page) => $page
            ->component('superadmin/roles/index')
            ->has('roles', 4)
            ->where('roles.2.value', 'admin')
            ->where('roles.2.editable', true)
            ->where('roles.3.value', 'super-admin')
            ->where('roles.3.editable', false)
            ->has('groups', 7)
            ->where('groups.6.label', 'Sistem')
            ->where('groups.6.permissions.0.reserved', true)
            ->where('groups.0.permissions.0.reserved', false));
});

it('closes a screen to a role the moment its permission is revoked', function (): void {
    $department = makeDepartment();
    $admin = makeAdmin($department);
    $admin->useWorkspace(new Workspace(UserRole::Admin, $department));

    $this->actingAs($admin)->get(route('admin.reports.index'))->assertOk();

    $remaining = Role::findByName('admin')->permissions->pluck('name')->reject('report.view')->values()->all();

    $this->actingAs(makeSuperAdmin())
        ->put(route('superadmin.roles.update', UserRole::Admin), ['permissions' => $remaining])
        ->assertRedirect();

    $this->actingAs($admin->fresh())->get(route('admin.reports.index'))->assertForbidden();
});

it('records which permissions were granted and revoked', function (): void {
    $superadmin = makeSuperAdmin();
    $before = Role::findByName('lecturer')->permissions->pluck('name');
    $after = $before->reject('logbook.monitor')->push('report.view')->values()->all();

    $this->actingAs($superadmin)
        ->put(route('superadmin.roles.update', UserRole::Lecturer), ['permissions' => $after])
        ->assertRedirect();

    $log = AuditLog::where('action', AuditAction::RolePermissionsUpdated->value)->sole();

    expect($log->user_id)->toBe($superadmin->id)
        ->and($log->changes['diberikan'])->toBe(['report.view'])
        ->and($log->changes['dicabut'])->toBe(['logbook.monitor']);
});

it('never hands a superadmin-only permission to another role', function (string $permission): void {
    $this->actingAs(makeSuperAdmin())
        ->put(route('superadmin.roles.update', UserRole::Admin), ['permissions' => ['student.view', $permission]])
        ->assertSessionHasErrors(['permissions.1' => 'Izin tersebut hanya dapat dipegang superadmin.']);

    expect(Role::findByName('admin')->hasPermissionTo($permission))->toBeFalse();
})->with([
    'mengelola peran' => ['role.manage'],
    'mengelola pengguna' => ['user.manage'],
    'mengelola program studi' => ['department.manage'],
]);

it('rejects a permission the application does not have', function (): void {
    $this->actingAs(makeSuperAdmin())
        ->put(route('superadmin.roles.update', UserRole::Student), ['permissions' => ['segalanya.boleh']])
        ->assertSessionHasErrors('permissions.0');
});

it('does not let the superadmin role be edited', function (): void {
    $this->actingAs(makeSuperAdmin())
        ->put(route('superadmin.roles.update', UserRole::SuperAdmin), ['permissions' => []])
        ->assertForbidden();

    expect(Role::findByName('super-admin')->hasPermissionTo('role.manage'))->toBeTrue();
});

it('keeps an edited role as it is when the roles are seeded again', function (): void {
    $this->actingAs(makeSuperAdmin())
        ->put(route('superadmin.roles.update', UserRole::Student), ['permissions' => ['proposal.view']]);

    seedRoles();

    expect(Role::findByName('student')->permissions->pluck('name')->all())->toBe(['proposal.view']);
});
