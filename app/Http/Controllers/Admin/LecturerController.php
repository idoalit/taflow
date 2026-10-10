<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\RegisterLecturer;
use App\Concerns\ProfileValidationRules;
use App\Enums\AuditAction;
use App\Enums\LecturerStatus;
use App\Enums\SupervisorRole;
use App\Enums\UserRole;
use App\Http\Controllers\Admin\Concerns\ScopesToDepartment;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Lecturer;
use App\Models\StudentSupervisor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LecturerController extends Controller
{
    use ProfileValidationRules, ScopesToDepartment;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Lecturer::class);

        $department = $this->department($request);

        $lecturers = $this->departmentLecturers($request)->get();

        return Inertia::render('admin/lecturers/index', [
            'department' => $department->name,
            'lecturers' => $lecturers->map(fn (Lecturer $lecturer): array => $this->row($lecturer, $department))->values(),
            'statuses' => array_map(
                fn (LecturerStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                LecturerStatus::cases(),
            ),
        ]);
    }

    /**
     * Adding a dosen starts from their NIP, because the answer decides which
     * form follows: somebody the system already knows is added to this prodi
     * as they are, and only somebody new is asked for a name and a surel.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Lecturer::class);

        $department = $this->department($request);

        $request->validate(['nip' => ['nullable', 'string', 'max:50']], [], ['nip' => 'NIP']);

        $number = $request->string('nip')->trim()->toString();

        $match = $number === ''
            ? null
            : Lecturer::query()->where('lecturer_number', $number)->with(['user', 'departments'])->first();

        return Inertia::render('admin/lecturers/create', [
            'department' => $department->name,
            'nip' => $number,
            'match' => $match === null ? null : [
                'id' => $match->id,
                'name' => $match->user->name,
                'lecturer_number' => $match->lecturer_number,
                'academic_position' => $match->academic_position,
                'homebase' => $match->homebase()?->name,
                'is_member' => $match->departments->contains($department),
            ],
            'defaultQuota' => RegisterLecturer::DEFAULT_QUOTA,
        ]);
    }

    /**
     * A dosen nobody has registered yet. This program studi becomes their
     * homebase.
     */
    public function store(Request $request, RegisterLecturer $register): RedirectResponse
    {
        Gate::authorize('create', Lecturer::class);

        $department = $this->department($request);

        $validated = $request->validate([
            ...$this->profileRules(),
            'lecturer_number' => ['required', 'string', 'max:50', Rule::unique(Lecturer::class)],
            'academic_position' => ['nullable', 'string', 'max:100'],
            'max_supervisions' => ['required', 'integer', 'min:0', 'max:50'],
        ], [
            'email.unique' => 'Surel ini sudah terdaftar.',
            'lecturer_number.unique' => 'NIP ini sudah terdaftar. Periksa NIP-nya untuk menambahkan dosen yang sudah ada.',
        ], [
            'name' => 'nama',
            'email' => 'surel',
            'lecturer_number' => 'NIP',
            'academic_position' => 'jabatan akademik',
            'max_supervisions' => 'kuota bimbingan',
        ]);

        $lecturer = $register->handle($department, $validated);

        AuditLog::record(
            $lecturer->user,
            AuditAction::UserCreated,
            $request->user(),
            changes: [
                'name' => $lecturer->user->name,
                'email' => $lecturer->user->email,
                'role' => UserRole::Lecturer->label(),
                'lecturer_number' => $lecturer->lecturer_number,
                'homebase' => $department->name,
            ],
            request: $request,
        );

        Password::broker()->sendResetLink(['email' => $lecturer->user->email]);

        return to_route('admin.lecturers.show', $lecturer)
            ->with('success', "Dosen ditambahkan. Tautan aktivasi dikirim ke {$lecturer->user->email}.");
    }

    public function show(Request $request, Lecturer $lecturer): Response
    {
        Gate::authorize('view', $lecturer);

        $department = $this->department($request);
        abort_unless($lecturer->departments()->whereKey($department->id)->exists(), 404);

        $lecturer->load(['user', 'departments']);

        $homebase = $lecturer->homebase();
        $release = Gate::inspect('release', [$lecturer, $department]);

        $assignments = StudentSupervisor::query()
            ->where('lecturer_id', $lecturer->id)
            ->whereHas('student', fn ($query) => $query->where('department_id', $department->id))
            ->with(['student.user'])
            ->get();

        return Inertia::render('admin/lecturers/show', [
            'department' => $department->name,
            'lecturer' => [
                ...$this->row($lecturer, $department),
                'initials' => $lecturer->user->initials(),
                // The homebase first, then the rest by name.
                'departments' => $lecturer->departments
                    ->sortBy(fn (Department $member): string => ($member->is($homebase) ? '0' : '1').$member->name)
                    ->map(fn (Department $member): array => [
                        'name' => $member->name,
                        'is_homebase' => $member->is($homebase),
                    ])->values(),
            ],
            'release' => [
                'allowed' => $release->allowed(),
                'reason' => $release->message(),
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
    private function row(Lecturer $lecturer, Department $department): array
    {
        $homebase = $lecturer->homebase();

        $load = $lecturer->supervisorAssignments()
            ->where('role', SupervisorRole::Supervisor)
            ->count();

        return [
            'id' => $lecturer->id,
            'name' => $lecturer->user->name,
            'email' => $lecturer->user->email,
            'lecturer_number' => $lecturer->lecturer_number,
            'academic_position' => $lecturer->academic_position,
            'homebase' => $homebase?->name,
            'is_homebase' => $homebase?->is($department) ?? false,
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
            ->with(['user', 'departments'])
            ->join('users', 'users.id', '=', 'lecturers.user_id')
            ->orderBy('users.name')
            ->select('lecturers.*');
    }
}
