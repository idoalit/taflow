<?php

namespace App\Http\Controllers;

use App\Actions\Thesis\NotifyThesisParticipants;
use App\Enums\ApprovalStatus;
use App\Enums\ExamStage;
use App\Enums\NotificationCategory;
use App\Http\Resources\ExamRequestResource;
use App\Models\Student;
use App\Models\ThesisExamRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The supervisor's gate on a student's exam request, for either stage: Admin
 * Prodi cannot schedule anything until this is signed off.
 */
class ExamVerificationController extends Controller
{
    public function index(Request $request): Response
    {
        $counts = [];
        $collected = [];

        foreach (ExamStage::cases() as $case) {
            Gate::authorize('viewAny', $case->requestModelClass());

            $collected[$case->value] = $this->visibleRequests($request, $case)
                ->with(['student.user', 'thesisProposal', 'exam'])
                ->latest('id')
                ->get();

            $counts[$case->value] = $collected[$case->value]->count();
        }

        $stage = $this->resolveStage($request, $counts);

        return Inertia::render('thesis/exam-verifications/index', [
            'stage' => $stage->value,
            'stages' => array_map(fn (ExamStage $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'count' => $counts[$case->value],
            ], ExamStage::cases()),
            'requests' => $collected[$stage->value]
                ->map(fn (ThesisExamRequest $examRequest): array => [
                    ...(new ExamRequestResource($examRequest))->toArray($request),
                    'student' => $examRequest->student->user->name,
                    'student_number' => $examRequest->student->student_number,
                    'title' => $examRequest->thesisProposal->title,
                    'supervisor_status' => $examRequest->supervisor_status->value,
                    'supervisor_status_label' => $examRequest->supervisor_status->label(),
                ])->values(),
        ]);
    }

    public function show(Request $request, ExamStage $stage, int $examRequest): Response
    {
        $thesisExamRequest = $this->findRequest($stage, $examRequest);

        Gate::authorize('view', $thesisExamRequest);

        $thesisExamRequest->load(['student.user', 'student.department', 'thesisProposal', 'exam']);
        $student = $thesisExamRequest->student;
        $setting = $student->department->activeThesisSetting();

        return Inertia::render('thesis/exam-verifications/show', [
            'request' => [
                ...(new ExamRequestResource($thesisExamRequest))->toArray($request),
                'student' => $student->user->name,
                'student_number' => $student->student_number,
                'department' => $student->department->name,
                'title' => $thesisExamRequest->thesisProposal->title,
                'supervisor_status_label' => $thesisExamRequest->supervisor_status->label(),
                'supervisor_notes' => $thesisExamRequest->supervisor_notes,
                'completed_sessions' => $student->completedGuidanceCount($stage->guidanceStage()),
                'required_sessions' => $stage === ExamStage::Proposal
                    ? $setting->min_proposal_guidance_sessions
                    : $setting->min_research_guidance_sessions,
            ],
            'canVerify' => Gate::allows('verifyAsSupervisor', $thesisExamRequest),
        ]);
    }

    public function verify(
        Request $request,
        ExamStage $stage,
        int $examRequest,
        NotifyThesisParticipants $notify,
    ): RedirectResponse {
        $thesisExamRequest = $this->findRequest($stage, $examRequest);

        Gate::authorize('verifyAsSupervisor', $thesisExamRequest);

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'supervisor_notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'decision' => 'keputusan verifikasi',
            'supervisor_notes' => 'catatan verifikasi',
        ]);

        $approved = $validated['decision'] === 'approved';

        $thesisExamRequest->supervisor_status = $approved
            ? ApprovalStatus::Approved
            : ApprovalStatus::Rejected;
        $thesisExamRequest->supervisor_verified_by = $request->user()->id;
        $thesisExamRequest->supervisor_verified_at = now();
        $thesisExamRequest->supervisor_notes = $validated['supervisor_notes'] ?? null;
        $thesisExamRequest->syncOverallStatus();
        $thesisExamRequest->save();

        $label = strtolower($stage->title());

        $notify->student(
            $thesisExamRequest->student,
            $approved ? NotificationCategory::Approval : NotificationCategory::Document,
            $approved ? 'Pengajuan ujian disetujui pembimbing' : 'Pengajuan ujian ditolak pembimbing',
            $approved
                ? "Pengajuan {$label} Anda disetujui dan diteruskan ke admin program studi."
                : ($validated['supervisor_notes'] ?? "Pengajuan {$label} Anda ditolak pembimbing."),
            route($stage->requestRouteName()),
        );

        return to_route('exam-verifications.index', ['stage' => $stage])
            ->with('success', $approved ? 'Pengajuan ujian disetujui.' : 'Pengajuan ujian ditolak.');
    }

    /**
     * @return Builder<ThesisExamRequest>
     */
    private function visibleRequests(Request $request, ExamStage $stage): Builder
    {
        return $stage->requestModelClass()::query()
            ->whereIn('student_id', Student::query()->visibleTo($request->user())->select('id'));
    }

    private function findRequest(ExamStage $stage, int $id): ThesisExamRequest
    {
        return $stage->requestModelClass()::query()->findOrFail($id);
    }

    /**
     * Honour an explicit stage; otherwise open on whichever tab has something
     * waiting, preferring proposals.
     *
     * @param  array<string, int>  $counts
     */
    private function resolveStage(Request $request, array $counts): ExamStage
    {
        $requested = ExamStage::tryFrom($request->string('stage')->toString());

        if ($requested !== null) {
            return $requested;
        }

        return $counts[ExamStage::Proposal->value] === 0 && $counts[ExamStage::Research->value] > 0
            ? ExamStage::Research
            : ExamStage::Proposal;
    }
}
