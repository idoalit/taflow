<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Thesis\ProvisionDepartment;
use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('manage', Department::class);

        $departments = Department::query()
            ->withCount(['students', 'lecturers', 'admins'])
            ->orderBy('name')
            ->get();

        return Inertia::render('superadmin/departments/index', [
            'departments' => $departments->map(fn (Department $department): array => $this->row($department))->values(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('manage', Department::class);

        return Inertia::render('superadmin/departments/form', ['department' => null]);
    }

    /**
     * A program studi is not usable the moment its row exists: it also needs
     * the faculty's supervision form, which no screen creates.
     */
    public function store(Request $request, ProvisionDepartment $provision): RedirectResponse
    {
        Gate::authorize('manage', Department::class);

        $validated = $this->validated($request);

        $department = DB::transaction(function () use ($validated, $provision): Department {
            $department = Department::create($validated);
            $provision->handle($department);

            return $department;
        });

        AuditLog::record(
            $department,
            AuditAction::DepartmentCreated,
            $request->user(),
            changes: ['name' => $department->name, 'code' => $department->code],
            request: $request,
        );

        return to_route('superadmin.departments.show', $department)
            ->with('success', "Program studi {$department->name} dibuat.");
    }

    public function show(Department $department): Response
    {
        Gate::authorize('manage', Department::class);

        $department->loadCount(['students', 'lecturers', 'admins']);

        $admins = $department->admins()->orderBy('name')->get();

        // Anyone but a mahasiswa may administer a prodi — a dosen who also runs
        // the program is the ordinary case.
        $candidates = User::query()
            ->whereDoesntHave('student')
            ->whereKeyNot($admins->modelKeys())
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('superadmin/departments/show', [
            'department' => [
                ...$this->row($department),
                'is_empty' => $this->isEmpty($department),
            ],
            'admins' => $admins->map(fn (User $admin): array => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'initials' => $admin->initials(),
            ])->values(),
            'candidates' => $candidates->map(fn (User $candidate): array => [
                'id' => $candidate->id,
                'name' => $candidate->name,
                'email' => $candidate->email,
            ])->values(),
        ]);
    }

    public function edit(Department $department): Response
    {
        Gate::authorize('manage', Department::class);

        return Inertia::render('superadmin/departments/form', [
            'department' => $this->row($department),
        ]);
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        Gate::authorize('manage', Department::class);

        $department->update($this->validated($request, $department));

        if ($department->wasChanged()) {
            AuditLog::record(
                $department,
                AuditAction::DepartmentUpdated,
                $request->user(),
                changes: ['name' => $department->name, ...Arr::except($department->getChanges(), 'updated_at')],
                request: $request,
            );
        }

        return to_route('superadmin.departments.show', $department)
            ->with('success', 'Program studi diperbarui.');
    }

    /**
     * Deleting a program studi takes its students, their proposals and their
     * exams with it, so only one that never held anybody may go. Anything else
     * is archived instead.
     */
    public function destroy(Request $request, Department $department): RedirectResponse
    {
        Gate::authorize('manage', Department::class);

        if (! $this->isEmpty($department)) {
            return back()->with(
                'error',
                'Program studi ini masih memiliki mahasiswa atau dosen, sehingga tidak dapat dihapus. '
                .'Nonaktifkan saja agar tidak lagi dapat dibuka.',
            );
        }

        AuditLog::record(
            $department,
            AuditAction::DepartmentDeleted,
            $request->user(),
            changes: ['name' => $department->name, 'code' => $department->code],
            request: $request,
        );

        $department->delete();

        return to_route('superadmin.departments.index')
            ->with('success', "Program studi {$department->name} dihapus.");
    }

    private function isEmpty(Department $department): bool
    {
        return ! $department->students()->exists() && ! $department->lecturers()->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Department $department = null): array
    {
        $request->merge(['code' => str($request->string('code'))->trim()->upper()->toString()]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', Rule::unique(Department::class)->ignore($department)],
            'faculty' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ], [
            'code.unique' => 'Kode ini sudah dipakai program studi lain.',
        ], [
            'name' => 'nama program studi',
            'code' => 'kode',
            'faculty' => 'fakultas',
            'city' => 'kota',
            'description' => 'keterangan',
            'is_active' => 'status',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Department $department): array
    {
        return [
            'id' => $department->id,
            'name' => $department->name,
            'code' => $department->code,
            'faculty' => $department->faculty,
            'city' => $department->city,
            'description' => $department->description,
            'is_active' => $department->is_active,
            'status_label' => $department->is_active ? 'Aktif' : 'Tidak Aktif',
            'students' => $department->students_count ?? 0,
            'lecturers' => $department->lecturers_count ?? 0,
            'admins' => $department->admins_count ?? 0,
            // The seat a superadmin steps into to run this prodi as its admin.
            'workspace' => (new Workspace(UserRole::Admin, $department))->key(),
        ];
    }
}
