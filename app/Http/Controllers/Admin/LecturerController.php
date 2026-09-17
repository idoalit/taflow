<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LecturerStatus;
use App\Enums\SupervisorRole;
use App\Http\Controllers\Admin\Concerns\ScopesToDepartment;
use App\Http\Controllers\Controller;
use App\Models\Lecturer;
use App\Models\StudentSupervisor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LecturerController extends Controller
{
    use ScopesToDepartment;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Lecturer::class);

        $department = $this->department($request);

        $lecturers = $this->departmentLecturers($request)->get();

        return Inertia::render('admin/lecturers/index', [
            'department' => $department->name,
            'lecturers' => $lecturers->map(fn (Lecturer $lecturer): array => $this->row($lecturer))->values(),
            'statuses' => array_map(
                fn (LecturerStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                LecturerStatus::cases(),
            ),
        ]);
    }

    public function show(Request $request, Lecturer $lecturer): Response
    {
        Gate::authorize('view', $lecturer);

        $department = $this->department($request);
        abort_unless($lecturer->departments()->whereKey($department->id)->exists(), 404);

        $lecturer->load('user');

        $assignments = StudentSupervisor::query()
            ->where('lecturer_id', $lecturer->id)
            ->whereHas('student', fn ($query) => $query->where('department_id', $department->id))
            ->with(['student.user'])
            ->get();

        return Inertia::render('admin/lecturers/show', [
            'lecturer' => [
                ...$this->row($lecturer),
                'initials' => $lecturer->user->initials(),
                'departments' => $lecturer->departments()->pluck('name')->all(),
            ],
            'students' => $assignments->map(fn (StudentSupervisor $assignment): array => [
                'id' => $assignment->student_id,
                'name' => $assignment->student->user->name,
                'student_number' => $assignment->student->student_number,
                'role_label' => $assignment->positionLabel(),
            ])->values(),
            'statuses' => array_map(
                fn (LecturerStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                LecturerStatus::cases(),
            ),
        ]);
    }

    /**
     * Quota and status are the two things a program studi sets on a dosen; the
     * rest of the profile belongs to the person, not to the prodi.
     */
    public function update(Request $request, Lecturer $lecturer): RedirectResponse
    {
        Gate::authorize('manage', $lecturer);

        $department = $this->department($request);
        abort_unless($lecturer->departments()->whereKey($department->id)->exists(), 404);

        $validated = $request->validate([
            'max_supervisions' => ['required', 'integer', 'min:0', 'max:50'],
            'academic_position' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::enum(LecturerStatus::class)],
        ], [], [
            'max_supervisions' => 'kuota bimbingan',
            'academic_position' => 'jabatan akademik',
            'status' => 'status',
        ]);

        $lecturer->update($validated);

        return back()->with('success', 'Data dosen diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Lecturer $lecturer): array
    {
        $load = $lecturer->supervisorAssignments()
            ->where('role', SupervisorRole::Supervisor)
            ->count();

        return [
            'id' => $lecturer->id,
            'name' => $lecturer->user->name,
            'email' => $lecturer->user->email,
            'lecturer_number' => $lecturer->lecturer_number,
            'academic_position' => $lecturer->academic_position,
            'status' => $lecturer->status->value,
            'status_label' => $lecturer->status->label(),
            'max_supervisions' => $lecturer->max_supervisions,
            'load' => $load,
            'load_percent' => $lecturer->max_supervisions === 0
                ? 100
                : min(100, (int) round($load / $lecturer->max_supervisions * 100)),
            'at_capacity' => $load >= $lecturer->max_supervisions,
        ];
    }

    /**
     * @return Builder<Lecturer>
     */
    private function departmentLecturers(Request $request): Builder
    {
        $department = $this->department($request);

        return Lecturer::query()
            ->whereHas('departments', fn ($query) => $query->whereKey($department->id))
            ->with('user')
            ->join('users', 'users.id', '=', 'lecturers.user_id')
            ->orderBy('users.name')
            ->select('lecturers.*');
    }
}
