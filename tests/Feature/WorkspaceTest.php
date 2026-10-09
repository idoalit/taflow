<?php

use App\Enums\UserRole;
use App\Http\Middleware\ResolveWorkspace;
use App\Models\User;

beforeEach(function (): void {
    seedRoles();
});

/**
 * A dosen in two program studi who also administers the second one — three
 * seats on one account, which is what the switcher exists for.
 */
function multiRoleLecturer(): array
{
    $first = makeDepartment();
    $second = makeDepartment();

    $mine = makeStudent($first);
    $lecturer = assignSupervisor($mine);

    $theirs = makeStudent($second);
    assignSupervisor($theirs, $lecturer);

    $user = $lecturer->user;
    $user->assignRole(UserRole::Admin->value);
    $user->departments()->attach($second);

    return [$user, $first, $second, $mine, $theirs];
}

it('offers one workspace per role and program studi', function (): void {
    [$user, $first, $second] = multiRoleLecturer();

    expect($user->workspaces()->map->key()->all())->toBe([
        "lecturer:{$first->id}",
        "lecturer:{$second->id}",
        "admin:{$second->id}",
    ]);
});

it('gives a student a single workspace', function (): void {
    $student = makeStudent();

    expect($student->user->workspaces())->toHaveCount(1)
        ->and($student->user->activeWorkspace()->label())
        ->toBe('Mahasiswa · '.$student->department->name);
});

it('gives the superadmin its own workspace and an Admin Prodi seat in every program studi', function (): void {
    $department = makeDepartment();
    $user = makeSuperAdmin();

    expect($user->workspaces()->map->key()->all())->toBe(['super-admin:all', "admin:{$department->id}"])
        ->and($user->activeWorkspace()->department)->toBeNull()
        ->and($user->activeWorkspace()->label())->toBe('Superadmin');
});

it('lets a superadmin run a program studi from its Admin Prodi seat', function (): void {
    $department = makeDepartment();
    $mine = makeStudent($department);
    makeStudent();

    $this->actingAs(makeSuperAdmin())
        ->withSession(inWorkspace(UserRole::Admin, $department))
        ->get(route('admin.students.index'))
        ->assertInertia(fn ($page) => $page
            ->has('students', 1)
            ->where('students.0.name', $mine->user->name)
            ->where('auth.user.role', 'admin')
            ->where('auth.user.workspace.label', "Admin Prodi · {$department->name}"));
});

it('gives a superadmin in an Admin Prodi seat the admin\'s permissions and no more', function (): void {
    $department = makeDepartment();
    $superadmin = makeSuperAdmin();

    $superadmin->useWorkspace($superadmin->workspaceFromKey("admin:{$department->id}"));

    expect($superadmin->can('student.manage'))->toBeTrue()
        ->and($superadmin->can('role.manage'))->toBeFalse();
});

it('offers a superadmin who also administers a program studi that seat only once', function (): void {
    $department = makeDepartment();
    $user = makeAdmin($department);
    $user->assignRole(UserRole::SuperAdmin->value);

    expect($user->workspaces()->map->key()->all())->toBe(["admin:{$department->id}", 'super-admin:all']);
});

it('offers nobody but the superadmin a seat in an archived program studi', function (): void {
    $department = makeDepartment();
    $student = makeStudent($department);
    $lecturer = assignSupervisor($student);
    $admin = makeAdmin($department);

    $department->update(['is_active' => false]);

    expect($student->user->workspaces())->toBeEmpty()
        ->and($lecturer->user->workspaces())->toBeEmpty()
        ->and($admin->workspaces())->toBeEmpty()
        ->and(makeSuperAdmin()->workspaces()->map->key()->all())->toContain("admin:{$department->id}");
});

it('shuts an account out of an archived program studi altogether', function (): void {
    $department = makeDepartment();
    $student = makeStudent($department);

    $this->actingAs($student->user)->get(route('proposals.index'))->assertOk();

    $department->update(['is_active' => false]);

    // A later request loads the user afresh, as a real one would.
    $archived = User::findOrFail($student->user_id);

    $this->actingAs($archived)->get(route('proposals.index'))->assertForbidden();
    $this->actingAs($archived)->get(route('dashboard'))->assertForbidden();
});

it('keeps a dosen working in the program studi that is still active', function (): void {
    [$user, $first, $second] = multiRoleLecturer();

    $first->update(['is_active' => false]);

    expect(User::findOrFail($user->id)->workspaces()->map->key()->all())->toBe([
        "lecturer:{$second->id}",
        "admin:{$second->id}",
    ]);
});

it('shows only the active program studi\'s students to a dosen', function (): void {
    [$user, $first, , $mine, $theirs] = multiRoleLecturer();

    $user->useWorkspace($user->workspaceFromKey("lecturer:{$first->id}"));

    expect($mine->isVisibleTo($user))->toBeTrue()
        ->and($theirs->isVisibleTo($user))->toBeFalse();
});

it('follows the user into the other program studi', function (): void {
    [$user, , $second, $mine, $theirs] = multiRoleLecturer();

    $user->useWorkspace($user->workspaceFromKey("lecturer:{$second->id}"));

    expect($theirs->isVisibleTo($user))->toBeTrue()
        ->and($mine->isVisibleTo($user))->toBeFalse();
});

it('narrows permissions to the role of the active workspace', function (): void {
    [$user, $first, $second] = multiRoleLecturer();

    $user->useWorkspace($user->workspaceFromKey("lecturer:{$first->id}"));
    $asLecturer = $user->getAllPermissions()->pluck('name');

    $user->useWorkspace($user->workspaceFromKey("admin:{$second->id}"));
    $asAdmin = $user->getAllPermissions()->pluck('name');

    // Each seat grants its own things, and neither leaks into the other.
    expect($asAdmin->diff($asLecturer))->not->toBeEmpty()
        ->and($asLecturer->diff($asAdmin))->not->toBeEmpty();

    $adminOnly = $asAdmin->diff($asLecturer)->first();

    $user->useWorkspace($user->workspaceFromKey("lecturer:{$first->id}"));

    expect($user->can($adminOnly))->toBeFalse();
});

it('switches workspace and remembers it for the next request', function (): void {
    [$user, , $second] = multiRoleLecturer();

    $this->actingAs($user)
        ->post(route('workspace.update'), ['workspace' => "admin:{$second->id}"])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas(ResolveWorkspace::SESSION_KEY, "admin:{$second->id}");
});

it('refuses a workspace the user does not hold', function (): void {
    [$user] = multiRoleLecturer();
    $other = makeDepartment();

    $this->actingAs($user)
        ->post(route('workspace.update'), ['workspace' => "admin:{$other->id}"])
        ->assertSessionHasErrors('workspace');
});

it('shares the active workspace and the full list with every page', function (): void {
    [$user, $first] = multiRoleLecturer();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.workspace.key', "lecturer:{$first->id}")
            ->where('auth.user.workspace.department_code', $first->code)
            ->where('auth.user.role', 'lecturer')
            ->has('auth.user.workspaces', 3));
});

it('drops a stored workspace the user no longer has access to', function (): void {
    [$user, $first, $second] = multiRoleLecturer();

    $this->actingAs($user)
        ->post(route('workspace.update'), ['workspace' => "admin:{$second->id}"]);

    $user->departments()->detach($second);

    // A later request loads the user afresh, which is what makes the stored key
    // fail its check rather than being taken on trust.
    $this->actingAs(User::findOrFail($user->id))
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.user.workspace.key', "lecturer:{$first->id}"));
});

it('refuses a dashboard to an account whose role has no data behind it', function (): void {
    $user = User::factory()->create();
    $user->syncRoles([UserRole::Admin->value]);

    $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
});
