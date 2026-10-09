<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The two roles a superadmin hands out by hand. Mahasiswa and dosen are not
 * here: those follow from a profile the program studi imports, not from a
 * switch somebody flips.
 */
class UserRoleController extends Controller
{
    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $validated = $request->validate([
            'is_super_admin' => ['required', 'boolean'],
            'is_admin' => ['required', 'boolean'],
            'department_ids' => ['array', Rule::requiredIf($request->boolean('is_admin'))],
            'department_ids.*' => [Rule::exists(Department::class, 'id')],
        ], [
            'department_ids.required' => 'Admin Prodi harus ditugaskan ke sedikitnya satu program studi.',
        ], [
            'department_ids' => 'program studi',
        ]);

        $this->guard($request->user(), $user, $validated);

        DB::transaction(function () use ($user, $validated): void {
            $validated['is_super_admin']
                ? $user->assignRole(UserRole::SuperAdmin->value)
                : $user->removeRole(UserRole::SuperAdmin->value);

            if ($validated['is_admin']) {
                $user->assignRole(UserRole::Admin->value);
                $user->departments()->sync($validated['department_ids']);
            } else {
                $user->removeRole(UserRole::Admin->value);
                $user->departments()->detach();
            }
        });

        AuditLog::record(
            $user,
            AuditAction::UserRolesUpdated,
            $request->user(),
            changes: [
                'name' => $user->name,
                'superadmin' => $validated['is_super_admin'],
                'admin_prodi' => $validated['is_admin']
                    ? $user->departments()->orderBy('name')->pluck('name')->all()
                    : [],
            ],
            request: $request,
        );

        return back()->with('success', 'Peran akun diperbarui.');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function guard(User $actor, User $user, array $validated): void
    {
        // Nobody else can be the last superadmin while the actor is one, so
        // this single rule is also what keeps the system from being orphaned.
        if ($actor->is($user) && ! $validated['is_super_admin']) {
            throw ValidationException::withMessages([
                'is_super_admin' => 'Anda tidak dapat mencabut peran superadmin dari akun sendiri.',
            ]);
        }

        if ($user->student !== null && ($validated['is_super_admin'] || $validated['is_admin'])) {
            throw ValidationException::withMessages([
                'is_admin' => 'Akun mahasiswa tidak dapat diberi peran pengelola.',
            ]);
        }
    }
}
