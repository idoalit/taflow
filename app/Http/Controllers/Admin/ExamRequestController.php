<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Thesis\NotifyThesisParticipants;
use App\Enums\ApprovalStatus;
use App\Enums\ExamStage;
use App\Enums\NotificationCategory;
use App\Http\Controllers\Admin\Concerns\ScopesToDepartment;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExamRequestResource;
use App\Models\ThesisExamRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The program studi's gate, which opens only after the supervisor's. Approving
 * here is what makes a request schedulable.
 */
class ExamRequestController extends Controller
{
    use ScopesToDepartment;

    public function index(Request $request): Response
    {
        $department = $this->department($request);
        $counts = [];
        $collected = [];

        foreach (ExamStage::cases() as $case) {
            Gate::authorize('viewAny', $case->requestModelClass());

            $collected[$case->value] = $this->requests($request, $case)
                ->with(['student.user', 'thesisProposal', 'exam'])
                ->latest('id')
                ->get();

            $counts[$case->value] = $collected[$case->value]->count();
        }

        $stage = ExamStage::tryFrom($request->string('stage')->toString()) ?? ExamStage::Proposal;

        return Inertia::render('admin/exam-requests/index', [
            'department' => $department->name,
            'stage' => $stage->value,
            'stages' => array_map(fn (ExamStage $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'count' => $counts[$case->value],
            ], ExamStage::cases()),
            'requests' => $collected[$stage->value]
                ->map(fn (ThesisExamRequest $examRequest): array => $this->row($request, $examRequest))
                ->values(),
        ]);
    }

    public function show(Request $request, ExamStage $stage, int $examRequest): Response
    {
        $thesisExamRequest = $this->find($request, $stage, $examRequest);

        Gate::authorize('view', $thesisExamRequest);

        $thesisExamRequest->load(['student.user', 'student.department', 'thesisProposal', 'exam']);
        $student = $thesisExamRequest->student;
        $setting = $student->department->activeThesisSetting();

        return Inertia::render('admin/exam-requests/show', [
            'request' => [
                ...$this->row($request, $thesisExamRequest),
                'department' => $student->department->name,
                'supervisor_notes' => $thesisExamRequest->supervisor_notes,
                'admin_notes' => $thesisExamRequest->admin_notes,
                'completed_sessions' => $student->completedGuidanceCount($stage->guidanceStage()),
                'required_sessions' => $stage === ExamStage::Proposal
                    ? $setting->min_proposal_guidance_sessions
                    : $setting->min_research_guidance_sessions,
            ],
            'canVerify' => Gate::allows('verifyAsAdmin', $thesisExamRequest),
        ]);
    }

    public function verify(
        Request $request,
        ExamStage $stage,
        int $examRequest,
        NotifyThesisParticipants $notify,
    ): RedirectResponse {
        $thesisExamRequest = $this->find($request, $stage, $examRequest);

        Gate::authorize('verifyAsAdmin', $thesisExamRequest);

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'decision' => 'keputusan verifikasi',
            'admin_notes' => 'catatan admin',
        ]);

        $approved = $validated['decision'] === 'approved';

        $thesisExamRequest->admin_status = $approved ? ApprovalStatus::Approved : ApprovalStatus::Rejected;
        $thesisExamRequest->admin_verified_by = $request->user()->id;
        $thesisExamRequest->admin_verified_at = now();
        $thesisExamRequest->admin_notes = $validated['admin_notes'] ?? null;
        $thesisExamRequest->syncOverallStatus();
        $thesisExamRequest->save();

        $label = strtolower($stage->title());

        $notify->student(
            $thesisExamRequest->student,
            $approved ? NotificationCategory::Approval : NotificationCategory::Document,
            $approved ? 'Pengajuan ujian disetujui admin prodi' : 'Pengajuan ujian ditolak admin prodi',
            $approved
                ? "Pengajuan {$label} Anda disetujui. Menunggu penjadwalan."
                : ($validated['admin_notes'] ?? "Pengajuan {$label} Anda ditolak admin program studi."),
            route($stage->requestRouteName()),
        );

        return to_route('admin.exam-requests.index', ['stage' => $stage])
            ->with('success', $approved ? 'Pengajuan ujian disetujui.' : 'Pengajuan ujian ditolak.');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Request $request, ThesisExamRequest $examRequest): array
    {
        return [
            ...(new ExamRequestResource($examRequest))->toArray($request),
            'student' => $examRequest->student->user->name,
            'student_number' => $examRequest->student->student_number,
            'title' => $examRequest->thesisProposal->title,
            'supervisor_status_label' => $examRequest->supervisor_status->label(),
            'admin_status' => $examRequest->admin_status->value,
            'admin_status_label' => $examRequest->admin_status->label(),
            'schedulable' => $examRequest->status === ApprovalStatus::Approved
                && $examRequest->exam === null,
            'admin_show_url' => route('admin.exam-requests.show', [
                'stage' => $examRequest->stage(),
                'examRequest' => $examRequest->id,
            ]),
        ];
    }

    /**
     * @return Builder<ThesisExamRequest>
     */
    private function requests(Request $request, ExamStage $stage): Builder
    {
        $department = $this->department($request);

        return $stage->requestModelClass()::query()
            ->whereHas('student', fn (Builder $query) => $query->where('department_id', $department->id));
    }

    private function find(Request $request, ExamStage $stage, int $id): ThesisExamRequest
    {
        return $this->requests($request, $stage)->findOrFail($id);
    }
}
