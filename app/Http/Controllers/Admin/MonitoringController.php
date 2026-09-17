<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GuidanceStage;
use App\Http\Controllers\Admin\Concerns\ScopesToDepartment;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminStudentResource;
use App\Http\Resources\GuidanceSessionSummaryResource;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only oversight of how far every student has got. Nothing here changes
 * anything — the design is explicit that this page only watches.
 */
class MonitoringController extends Controller
{
    use ScopesToDepartment;

    public function index(Request $request): Response
    {
        Gate::authorize('monitor', Student::class);

        $department = $this->department($request);

        $students = Student::query()
            ->where('department_id', $department->id)
            ->with(['user', 'supervisorAssignments.lecturer.user'])
            ->join('users', 'users.id', '=', 'students.user_id')
            ->orderBy('users.name')
            ->select('students.*')
            ->get();

        return Inertia::render('admin/monitoring/index', [
            'department' => $department->name,
            'students' => AdminStudentResource::collection($students),
            'stages' => array_map(
                fn (GuidanceStage $stage): array => ['value' => $stage->value, 'label' => $stage->label()],
                GuidanceStage::cases(),
            ),
        ]);
    }

    public function show(Request $request, Student $student): Response
    {
        Gate::authorize('monitor', Student::class);

        $department = $this->department($request);
        abort_unless($student->department_id === $department->id, 404);

        $student->load(['user', 'department', 'supervisorAssignments.lecturer.user']);

        $sessions = $student->guidanceSessions()
            ->with(['student.user'])
            ->orderByDesc('meeting_scheduled_at')
            ->get();

        return Inertia::render('admin/monitoring/show', [
            'student' => (new AdminStudentResource($student))->withDetail(),
            'sessions' => GuidanceSessionSummaryResource::collection($sessions),
            'logbookCount' => $student->researchLogbooks()->count(),
        ]);
    }
}
