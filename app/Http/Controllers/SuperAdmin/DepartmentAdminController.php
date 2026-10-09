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
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Who runs a program studi. Being named here is what makes an account an Admin
 * Prodi: the role and the prodi are granted together and taken away together,
 * so neither is ever left standing without the other.
 */
class DepartmentAdminController extends Controller
{
    public function store(Request $request, Department $department): RedirectResponse
    {
        Gate::authorize('manage', Department::class);

        $validated = $request->validate([
            'user_id' => ['required', Rule::exists(User::class, 'id')],
        ], [], ['user_id' => 'akun']);

        $user = User::query()->whereKey($validated['user_id'])->firstOrFail();

        if ($user->student !== null) {
            throw ValidationException::withMessages([
                'user_id' => 'Akun mahasiswa tidak dapat ditugaskan sebagai Admin Prodi.',
            ]);
        }

        $department->admins()->syncWithoutDetaching([$user->id]);
        $user->assignRole(UserRole::Admin->value);

        AuditLog::record(
            $department,
            AuditAction::DepartmentAdminAssigned,
            $request->user(),
            changes: ['name' => $department->name, 'admin' => $user->name, 'email' => $user->email],
            request: $request,
        );

        return back()->with('success', "{$user->name} kini Admin Prodi {$department->name}.");
    }

    public function destroy(Request $request, Department $department, User $user): RedirectResponse
    {
        Gate::authorize('manage', Department::class);

        $department->admins()->detach($user->id);

        if (! $user->departments()->exists()) {
            $user->removeRole(UserRole::Admin->value);
        }

        AuditLog::record(
            $department,
            AuditAction::DepartmentAdminRemoved,
            $request->user(),
            changes: ['name' => $department->name, 'admin' => $user->name, 'email' => $user->email],
            request: $request,
        );

        return back()->with('success', "{$user->name} dilepas dari Admin Prodi {$department->name}.");
    }
}
