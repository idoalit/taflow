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

it('gives the superadmin one workspace spanning every program studi', function (): void {
    makeDepartment();
    $user = User::factory()->create();
    $user->syncRoles([UserRole::SuperAdmin->value]);

    expect($user->workspaces())->toHaveCount(1)
        ->and($user->activeWorkspace()->department)->toBeNull()
        ->and($user->activeWorkspace()->label())->toBe('Superadmin');
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
