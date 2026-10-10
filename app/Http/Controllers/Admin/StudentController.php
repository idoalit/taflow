<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\EnrollStudent;
use App\Concerns\ProfileValidationRules;
use App\Enums\AuditAction;
use App\Enums\StudentStatus;
use App\Enums\SupervisorRole;
use App\Enums\UserRole;
use App\Http\Controllers\Admin\Concerns\ScopesToDepartment;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminStudentResource;
use App\Models\AuditLog;
use App\Models\Lecturer;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    use ProfileValidationRules, ScopesToDepartment;

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

    public function create(Request $request): Response
    {
        Gate::authorize('create', Student::class);

        return Inertia::render('admin/students/create', [
            'department' => $this->department($request)->name,
        ]);
    }

    /**
     * One mahasiswa, for when a spreadsheet is more than the job needs. The
     * account is made without a password; its owner is mailed the link to set
     * one.
     */
    public function store(Request $request, EnrollStudent $enroll): RedirectResponse
    {
        Gate::authorize('create', Student::class);

        $department = $this->department($request);

        $validated = $request->validate([
            ...$this->profileRules(),
            'student_number' => ['required', 'string', 'max:50', Rule::unique(Student::class)],
            'enrollment_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ], [
            'email.unique' => 'Surel ini sudah terdaftar.',
            'student_number.unique' => 'NIM ini sudah terdaftar.',
        ], [
            'name' => 'nama',
            'email' => 'surel',
            'student_number' => 'NIM',
            'enrollment_year' => 'angkatan',
        ]);

        $student = $enroll->handle($department, $validated);

        AuditLog::record(
            $student->user,
            AuditAction::UserCreated,
            $request->user(),
            changes: [
                'name' => $student->user->name,
                'email' => $student->user->email,
                'role' => UserRole::Student->label(),
                'student_number' => $student->student_number,
                'department' => $department->name,
            ],
            request: $request,
        );

        Password::broker()->sendResetLink(['email' => $student->user->email]);

        return to_route('admin.students.show', $student)
            ->with('success', "Mahasiswa ditambahkan. Tautan aktivasi dikirim ke {$student->user->email}.");
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
