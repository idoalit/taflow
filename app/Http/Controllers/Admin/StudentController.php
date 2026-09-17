<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StudentStatus;
use App\Enums\SupervisorRole;
use App\Http\Controllers\Admin\Concerns\ScopesToDepartment;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminStudentResource;
use App\Models\Lecturer;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    use ScopesToDepartment;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Student::class);

        $department = $this->department($request);

        $students = Student::query()
            ->where('department_id', $department->id)
            ->with(['user', 'supervisorAssignments.lecturer.user'])
            ->join('users', 'users.id', '=', 'students.user_id')
            ->orderBy('users.name')
            ->select('students.*')
            ->get();

        return Inertia::render('admin/students/index', [
            'department' => $department->name,
            'students' => AdminStudentResource::collection($students),
            'statuses' => array_map(
                fn (StudentStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                StudentStatus::cases(),
            ),
        ]);
    }

    public function show(Request $request, Student $student): Response
    {
        Gate::authorize('view', $student);

        $department = $this->department($request);
        abort_unless($student->department_id === $department->id, 404);

        $student->load(['user', 'department', 'supervisorAssignments.lecturer.user', 'thesisProposals']);

        return Inertia::render('admin/students/show', [
            'student' => (new AdminStudentResource($student))->withDetail(),
            'lecturers' => $this->assignableLecturers($request),
            'maxSupervisors' => $department->activeThesisSetting()->max_supervisors_per_student,
            'roles' => array_map(
                fn (SupervisorRole $role): array => ['value' => $role->value, 'label' => $role->label()],
                SupervisorRole::cases(),
            ),
        ]);
    }

    /**
     * Replace a student's panel in one move. Sending the whole list rather than
     * adding one at a time is what lets the program studi's supervisor limit be
     * enforced on the result instead of on each step.
     */
    public function updateSupervisors(Request $request, Student $student): RedirectResponse
    {
        Gate::authorize('assignSupervisors', $student);

        $department = $this->department($request);
        abort_unless($student->department_id === $department->id, 404);

        $limit = $department->activeThesisSetting()->max_supervisors_per_student;
        $lecturerIds = Lecturer::query()
            ->whereHas('departments', fn ($query) => $query->whereKey($department->id))
            ->pluck('id');

        $validated = $request->validate([
            'assignments' => ['present', 'array'],
            'assignments.*.lecturer_id' => ['required', Rule::in($lecturerIds)],
            'assignments.*.role' => ['required', Rule::enum(SupervisorRole::class)],
        ], [
            'assignments.*.lecturer_id.in' => 'Dosen tersebut bukan anggota program studi ini.',
        ], [
            'assignments' => 'daftar pembimbing dan penguji',
        ]);

        $this->guardAgainstDuplicates($validated['assignments']);
        $this->guardSupervisorLimit($validated['assignments'], $limit);

        $student->supervisorAssignments()->delete();

        foreach (array_values($validated['assignments']) as $index => $assignment) {
            $student->supervisorAssignments()->create([
                'lecturer_id' => $assignment['lecturer_id'],
                'role' => $assignment['role'],
                'order' => $index + 1,
                'assigned_by' => $request->user()->id,
                'assigned_at' => now(),
            ]);
        }

        return back()->with('success', 'Pembimbing dan penguji tersimpan.');
    }

    /**
     * @param  array<int, array{lecturer_id: int, role: string}>  $assignments
     */
    private function guardAgainstDuplicates(array $assignments): void
    {
        $lecturers = array_column($assignments, 'lecturer_id');

        // Not once per role — once, full stop. A dosen who is both pembimbing
        // and penguji would occupy two seats on the exam panel while only ever
        // being able to give one verdict.
        abort_if(
            count($lecturers) !== count(array_unique($lecturers)),
            422,
            'Seorang dosen hanya boleh tercatat satu kali untuk mahasiswa ini, '
            .'baik sebagai pembimbing maupun penguji.',
        );
    }

    /**
     * @param  array<int, array{lecturer_id: int, role: string}>  $assignments
     */
    private function guardSupervisorLimit(array $assignments, int $limit): void
    {
        $supervisors = count(array_filter(
            $assignments,
            fn (array $row): bool => $row['role'] === SupervisorRole::Supervisor->value,
        ));

        abort_if(
            $supervisors > $limit,
            422,
            "Program studi ini membatasi {$limit} dosen pembimbing per mahasiswa.",
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function assignableLecturers(Request $request): array
    {
        $department = $this->department($request);

        return Lecturer::query()
            ->whereHas('departments', fn ($query) => $query->whereKey($department->id))
            ->with('user')
            ->get()
            ->map(fn (Lecturer $lecturer): array => [
                'id' => $lecturer->id,
                'name' => $lecturer->user->name,
                'load' => $lecturer->supervisedStudents()->count(),
                'max' => $lecturer->max_supervisions,
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }
}
