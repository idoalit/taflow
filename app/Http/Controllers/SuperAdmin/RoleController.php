<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Peran & Izin: which role may do what. The roles themselves are fixed — the
 * application is built around exactly these four — so the editor changes only
 * what three of them are allowed.
 */
class RoleController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('manage', Role::class);

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions')
            ->withCount('users')
            ->get()
            ->keyBy('name');

        $reserved = PermissionCatalog::reserved();
        $editable = PermissionCatalog::editableRoles();

        return Inertia::render('superadmin/roles/index', [
            'roles' => array_map(fn (UserRole $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
                'editable' => in_array($role, $editable, true),
                'users' => $roles->get($role->value)->users_count ?? 0,
                'permissions' => $roles->get($role->value)?->permissions->pluck('name')->values()->all() ?? [],
                'defaults' => PermissionCatalog::defaultsFor($role),
            ], UserRole::cases()),
            'groups' => collect(PermissionCatalog::groups())
                ->map(fn (array $permissions, string $group): array => [
                    'label' => $group,
                    'permissions' => collect($permissions)
                        ->map(fn (string $label, string $name): array => [
                            'name' => $name,
                            'label' => $label,
                            'reserved' => in_array($name, $reserved, true),
                        ])->values()->all(),
                ])->values(),
        ]);
    }

    public function update(Request $request, UserRole $role): RedirectResponse
    {
        Gate::authorize('manage', Role::class);

        abort_unless(
            in_array($role, PermissionCatalog::editableRoles(), true),
            403,
            'Izin Superadmin tidak dapat diubah: peran ini selalu memegang semuanya.',
        );

        $assignable = array_values(array_diff(PermissionCatalog::all(), PermissionCatalog::reserved()));

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in($assignable)],
        ], [
            'permissions.*.in' => 'Izin tersebut hanya dapat dipegang superadmin.',
        ], [
            'permissions' => 'izin',
        ]);

        $model = Role::query()->where('name', $role->value)->where('guard_name', 'web')->firstOrFail();
        $before = $model->permissions->pluck('name');
        $after = collect(array_values(array_unique($validated['permissions'])));

        $model->syncPermissions($after->all());

        $granted = $after->diff($before)->values()->all();
        $revoked = $before->diff($after)->values()->all();

        if ($granted !== [] || $revoked !== []) {
            AuditLog::record(
                $model,
                AuditAction::RolePermissionsUpdated,
                $request->user(),
                changes: ['name' => $role->label(), 'diberikan' => $granted, 'dicabut' => $revoked],
                request: $request,
            );
        }

        return back()->with('success', "Izin {$role->label()} diperbarui.");
    }
}
