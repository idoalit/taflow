<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Concerns\FormatsIndonesianDates;
use App\Concerns\ProfileValidationRules;
use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    use FormatsIndonesianDates, ProfileValidationRules;

    /**
     * Every account in the institution. Filtered and paged on the server: this
     * is the one list that grows with every intake of every program studi.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'department' => ['nullable', 'integer'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $role = $filters['role'] ?? null;
        $department = $filters['department'] ?? null;

        $users = User::query()
            ->with(['roles', 'student.department', 'lecturer.departments', 'departments'])
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query
                    ->whereLike('name', "%{$search}%")
                    ->orWhereLike('email', "%{$search}%"),
            ))
            ->when($role !== null, fn (Builder $query) => $query->whereHas(
                'roles',
                fn (Builder $query) => $query->where('name', $role),
            ))
            ->when($department !== null, fn (Builder $query) => $query->where(
                fn (Builder $query) => $query
                    ->whereHas('student', fn (Builder $query) => $query->where('department_id', $department))
                    ->orWhereHas('lecturer.departments', fn (Builder $query) => $query->whereKey($department))
                    ->orWhereHas('departments', fn (Builder $query) => $query->whereKey($department)),
            ))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $user): array => $this->row($user));

        return Inertia::render('superadmin/users/index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'role' => $role ?? 'all',
                'department' => $department === null ? 'all' : (string) $department,
            ],
            'roles' => array_map(
                fn (UserRole $role): array => ['value' => $role->value, 'label' => $role->label()],
                UserRole::cases(),
            ),
            'departments' => $this->departmentOptions(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('superadmin/users/create', [
            'departments' => $this->departmentOptions(),
            'preselectedDepartment' => $request->integer('department') ?: null,
        ]);
    }

    /**
     * Only the two kinds of account no import can make: an Admin Prodi and
     * another superadmin. Nobody is handed a password — the new account gets
     * the same reset link everyone else activates with.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $validated = $request->validate([
            ...$this->profileRules(),
            'role' => ['required', Rule::in([UserRole::Admin->value, UserRole::SuperAdmin->value])],
            'department_ids' => ['array', Rule::requiredIf($request->input('role') === UserRole::Admin->value)],
            'department_ids.*' => [Rule::exists(Department::class, 'id')],
        ], [
            'email.unique' => 'Surel ini sudah terdaftar.',
            'department_ids.required' => 'Pilih sedikitnya satu program studi untuk Admin Prodi.',
        ], [
            'name' => 'nama',
            'email' => 'surel',
            'role' => 'peran',
            'department_ids' => 'program studi',
        ]);

        $role = UserRole::from($validated['role']);

        $user = DB::transaction(function () use ($validated, $role): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => Str::lower($validated['email']),
                'password' => Str::password(40),
            ]);

            $user->assignRole($role->value);

            if ($role === UserRole::Admin) {
                $user->departments()->sync($validated['department_ids']);
            }

            return $user;
        });

        AuditLog::record(
            $user,
            AuditAction::UserCreated,
            $request->user(),
            changes: ['name' => $user->name, 'email' => $user->email, 'role' => $role->label()],
            request: $request,
        );

        Password::broker()->sendResetLink(['email' => $user->email]);

        return to_route('superadmin.users.show', $user)
            ->with('success', "Akun dibuat. Tautan aktivasi dikirim ke {$user->email}.");
    }

    public function show(Request $request, User $user): Response
    {
        Gate::authorize('view', $user);

        $user->load(['roles', 'student.department', 'lecturer.departments', 'departments']);

        $roles = $this->heldRoles($user);
        $deletion = Gate::inspect('delete', $user);
        $impersonation = Gate::inspect('impersonate', $user);

        return Inertia::render('superadmin/users/show', [
            'account' => [
                ...$this->row($user),
                'is_self' => $user->is($request->user()),
                'is_super_admin' => $roles->contains(UserRole::SuperAdmin),
                'is_admin' => $roles->contains(UserRole::Admin),
                'admin_department_ids' => $user->departments->modelKeys(),
                'student' => $user->student === null ? null : [
                    'number' => $user->student->student_number,
                    'department' => $user->student->department->name,
                ],
                'lecturer' => $user->lecturer === null ? null : [
                    'number' => $user->lecturer->lecturer_number,
                    'departments' => $user->lecturer->departments->pluck('name')->all(),
                ],
                'created_at' => $this->longDateTime($user->created_at),
            ],
            'deletion' => [
                'allowed' => $deletion->allowed(),
                'reason' => $deletion->message(),
            ],
            // Not `impersonation`: that is the shared prop the shell reads to
            // know whether a superadmin is looking through an account, and a
            // page prop of the same name would overwrite it on this screen.
            'impersonable' => [
                'allowed' => $impersonation->allowed(),
                'reason' => $impersonation->message(),
            ],
            'departments' => $this->departmentOptions(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $validated = $request->validate($this->profileRules($user->id), [
            'email.unique' => 'Surel ini sudah terdaftar.',
        ], [
            'name' => 'nama',
            'email' => 'surel',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
        ]);

        if ($user->wasChanged()) {
            AuditLog::record(
                $user,
                AuditAction::UserUpdated,
                $request->user(),
                changes: ['name' => $user->name, ...Arr::except($user->getChanges(), 'updated_at')],
                request: $request,
            );
        }

        return back()->with('success', 'Data akun diperbarui.');
    }

    /**
     * The policy decides whether the account may go at all; typing the surel
     * is only there so that a slip of the hand cannot do it.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $request->validate([
            'email' => ['required', Rule::in([$user->email])],
        ], [
            'email.in' => 'Surel yang diketik tidak sama dengan surel akun ini.',
        ], [
            'email' => 'surel',
        ]);

        AuditLog::record(
            $user,
            AuditAction::UserDeleted,
            $request->user(),
            changes: [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $this->heldRoles($user)->map->label()->values()->all(),
            ],
            request: $request,
        );

        $user->delete();

        return to_route('superadmin.users.index')->with('success', "Akun {$user->name} dihapus.");
    }

    /**
     * @return Collection<int, UserRole>
     */
    private function heldRoles(User $user): Collection
    {
        return $user->roles->pluck('name')
            ->map(fn (string $name): ?UserRole => UserRole::tryFrom($name))
            ->filter()
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $user): array
    {
        $departments = collect([$user->student?->department])
            ->concat($user->lecturer->departments ?? [])
            ->concat($user->departments)
            ->filter()
            ->unique('id');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'initials' => $user->initials(),
            'roles' => $this->heldRoles($user)->map(fn (UserRole $role): string => $role->label())->all(),
            'departments' => $departments->pluck('name')->values()->all(),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, is_active: bool}>
     */
    private function departmentOptions(): array
    {
        return Department::query()->orderBy('name')->get()
            ->map(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
                'is_active' => $department->is_active,
            ])->all();
    }
}
