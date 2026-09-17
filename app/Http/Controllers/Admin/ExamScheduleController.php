<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Thesis\ScheduleExam;
use App\Enums\ApprovalStatus;
use App\Enums\ExamParticipantRole;
use App\Enums\ExamStage;
use App\Enums\ExamStatus;
use App\Enums\RubricStatus;
use App\Enums\SupervisorRole;
use App\Http\Controllers\Admin\Concerns\ScopesToDepartment;
use App\Http\Controllers\Controller;
use App\Http\Resources\ThesisExamSummaryResource;
use App\Models\DepartmentRubric;
use App\Models\Lecturer;
use App\Models\Rubric;
use App\Models\ThesisExam;
use App\Models\ThesisExamRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pelaksanaan Ujian for the program studi: what is on the calendar, and what
 * has been approved but is still waiting for a slot.
 */
class ExamScheduleController extends Controller
{
    use ScopesToDepartment;

    public function index(Request $request): Response
    {
        Gate::authorize('schedule', ThesisExam::class);

        $department = $this->department($request);
        $stage = ExamStage::tryFrom($request->string('stage')->toString()) ?? ExamStage::Proposal;

        $exams = $this->exams($request, $stage)
            ->with(['student.user', 'evaluations'])
            ->latest('scheduled_at')
            ->get();

        $counts = [];

        foreach (ExamStage::cases() as $case) {
            $counts[$case->value] = $this->exams($request, $case)->count();
        }

        return Inertia::render('admin/exams/index', [
            'department' => $department->name,
            'stage' => $stage->value,
            'stages' => array_map(fn (ExamStage $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'count' => $counts[$case->value],
            ], ExamStage::cases()),
            'exams' => ThesisExamSummaryResource::collection($exams),
            'waiting' => $this->waitingToSchedule($request, $stage),
        ]);
    }

    public function create(Request $request, ExamStage $stage, int $examRequest): Response
    {
        Gate::authorize('schedule', ThesisExam::class);

        $thesisExamRequest = $this->findSchedulableRequest($request, $stage, $examRequest);
        $student = $thesisExamRequest->student;
        $assignment = DepartmentRubric::activeFor($student->department, $stage);

        return Inertia::render('admin/exams/create', [
            'stage' => $stage->value,
            'stage_label' => $stage->title(),
            'request' => [
                'id' => $thesisExamRequest->id,
                'student' => $student->user->name,
                'student_number' => $student->student_number,
                'supervisors' => $student->supervisorAssignments
                    ->where('role', SupervisorRole::Supervisor)
                    ->map(fn ($assignment): string => $assignment->lecturer->user->name)
                    ->values(),
                'title' => $thesisExamRequest->thesisProposal->title,
            ],
            'lecturers' => $this->departmentLecturers($request),
            'rubrics' => $this->publishedRubrics($request),
            'defaultRubricId' => $assignment?->rubric_id,
            'roles' => array_map(
                fn (ExamParticipantRole $role): array => ['value' => $role->value, 'label' => $role->label()],
                ExamParticipantRole::panelRoles(),
            ),
            'minExaminers' => $student->department->activeThesisSetting()->min_examiners,
            'allowsSupervisorAsExaminer' => $student->department
                ->activeThesisSetting()->allows_supervisor_as_examiner,
        ]);
    }

    public function store(
        Request $request,
        ExamStage $stage,
        int $examRequest,
        ScheduleExam $schedule,
    ): RedirectResponse {
        Gate::authorize('schedule', ThesisExam::class);

        $thesisExamRequest = $this->findSchedulableRequest($request, $stage, $examRequest);
        $department = $this->department($request);

        $lecturerIds = Lecturer::query()
            ->whereHas('departments', fn (Builder $query) => $query->whereKey($department->id))
            ->pluck('id');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:300'],
            'venue' => ['nullable', 'string', 'max:255'],
            'preparation_notes' => ['nullable', 'string', 'max:2000'],
            'rubric_id' => ['nullable', Rule::in($this->publishedRubricIds($request))],
            'panel' => ['required', 'array', 'min:1'],
            'panel.*.lecturer_id' => ['required', Rule::in($lecturerIds)],
            'panel.*.role' => ['required', Rule::enum(ExamParticipantRole::class)],
        ], [
            'scheduled_at.after' => 'Jadwal ujian harus di masa depan.',
            'panel.required' => 'Tentukan sedikitnya satu anggota panel penguji.',
            'panel.*.lecturer_id.in' => 'Dosen tersebut bukan anggota program studi ini.',
        ], [
            'title' => 'judul ujian',
            'scheduled_at' => 'tanggal dan waktu',
            'duration_minutes' => 'durasi',
            'venue' => 'tempat',
            'rubric_id' => 'rubrik ujian',
            'panel' => 'panel penguji',
        ]);

        $exam = $schedule->handle(
            examRequest: $thesisExamRequest,
            scheduledBy: $request->user(),
            title: $validated['title'],
            scheduledAt: now()->parse($validated['scheduled_at']),
            durationMinutes: $validated['duration_minutes'],
            venue: $validated['venue'] ?? null,
            preparationNotes: $validated['preparation_notes'] ?? null,
            panel: $validated['panel'],
            rubricId: $validated['rubric_id'] ?? null,
        );

        return to_route('admin.exams.index', ['stage' => $stage])
            ->with('success', "Jadwal {$stage->title()} untuk {$exam->student->user->name} tersimpan.");
    }

    /**
     * Approved requests with nowhere to sit yet — the design's "siap dijadwalkan"
     * queue beside the calendar.
     *
     * @return array<int, array<string, mixed>>
     */
    private function waitingToSchedule(Request $request, ExamStage $stage): array
    {
        $department = $this->department($request);

        return $stage->requestModelClass()::query()
            ->whereHas('student', fn (Builder $query) => $query->where('department_id', $department->id))
            ->where('status', ApprovalStatus::Approved)
            ->whereNull($stage === ExamStage::Proposal ? 'proposal_exam_id' : 'research_exam_id')
            ->with(['student.user', 'thesisProposal'])
            ->latest('admin_verified_at')
            ->get()
            ->map(fn (ThesisExamRequest $examRequest): array => [
                'id' => $examRequest->id,
                'student' => $examRequest->student->user->name,
                'student_number' => $examRequest->student->student_number,
                'title' => $examRequest->thesisProposal->title,
                'approved_at' => $examRequest->admin_verified_at?->translatedFormat('d M Y') ?? '—',
                'schedule_url' => route('admin.exams.create', [
                    'stage' => $stage,
                    'examRequest' => $examRequest->id,
                ]),
            ])
            ->values()
            ->all();
    }

    /**
     * @return Builder<ThesisExam>
     */
    private function exams(Request $request, ExamStage $stage): Builder
    {
        $department = $this->department($request);

        return $stage->modelClass()::query()
            ->whereHas('student', fn (Builder $query) => $query->where('department_id', $department->id))
            ->whereNot('status', ExamStatus::Cancelled);
    }

    private function findSchedulableRequest(Request $request, ExamStage $stage, int $id): ThesisExamRequest
    {
        $department = $this->department($request);

        $examRequest = $stage->requestModelClass()::query()
            ->whereHas('student', fn (Builder $query) => $query->where('department_id', $department->id))
            ->with(['student.user', 'student.supervisorAssignments.lecturer.user', 'thesisProposal'])
            ->findOrFail($id);

        abort_unless(
            $examRequest->status === ApprovalStatus::Approved,
            403,
            'Pengajuan ini belum disetujui kedua gerbang verifikasi, sehingga belum dapat dijadwalkan.',
        );

        abort_unless(
            $examRequest->exam === null,
            403,
            'Pengajuan ini sudah memiliki jadwal ujian.',
        );

        return $examRequest;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function departmentLecturers(Request $request): array
    {
        $department = $this->department($request);

        return Lecturer::query()
            ->whereHas('departments', fn (Builder $query) => $query->whereKey($department->id))
            ->with('user')
            ->get()
            ->map(fn (Lecturer $lecturer): array => [
                'id' => $lecturer->id,
                'name' => $lecturer->user->name,
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function publishedRubrics(Request $request): array
    {
        return $this->publishedRubricQuery($request)
            ->get()
            ->map(fn (Rubric $rubric): array => ['id' => $rubric->id, 'name' => $rubric->name])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function publishedRubricIds(Request $request): array
    {
        return $this->publishedRubricQuery($request)->pluck('id')->all();
    }

    /**
     * @return Builder<Rubric>
     */
    private function publishedRubricQuery(Request $request): Builder
    {
        return Rubric::query()
            ->where('department_id', $this->department($request)->id)
            ->where('status', RubricStatus::Published)
            ->orderBy('name');
    }
}
