<?php

namespace App\Http\Controllers;

use App\Actions\Thesis\ExamEligibility;
use App\Actions\Thesis\NotifyThesisParticipants;
use App\Actions\Thesis\StoreThesisDocument;
use App\Enums\ApprovalStatus;
use App\Enums\NotificationCategory;
use App\Http\Requests\Thesis\StoreExamRequestRequest;
use App\Http\Resources\ExamRequestResource;
use App\Models\ProposalExamRequest;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProposalExamRequestController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ProposalExamRequest::class);

        $requests = ProposalExamRequest::query()
            ->whereIn('student_id', Student::query()->visibleTo($request->user())->select('id'))
            ->with('exam')
            ->latest('id')
            ->get();

        return Inertia::render('thesis/proposal-exam-requests/index', [
            'requests' => ExamRequestResource::collection($requests),
            'eligibility' => $this->eligibility($request),
            'canCreate' => Gate::allows('create', ProposalExamRequest::class),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', ProposalExamRequest::class);

        return Inertia::render('thesis/proposal-exam-requests/create', [
            'eligibility' => $this->eligibility($request),
        ]);
    }

    public function store(
        StoreExamRequestRequest $request,
        StoreThesisDocument $storeDocument,
        NotifyThesisParticipants $notify,
    ): RedirectResponse {
        Gate::authorize('create', ProposalExamRequest::class);

        $student = $request->user()->student;
        $eligibility = ExamEligibility::forProposalExam($student);

        // The design disables the submit button when the requirements are not
        // met; this is the same rule enforced server-side.
        if (! $eligibility->eligible) {
            return back()->with('error', $eligibility->summary);
        }

        $documents = [];

        foreach (array_keys($request->input('documents', [])) as $index) {
            $upload = $request->file("documents.{$index}.file");

            if (! $upload instanceof UploadedFile) {
                continue;
            }

            $documents[] = [
                'name' => (string) $request->input("documents.{$index}.name"),
                'path' => $storeDocument($upload, $student, 'exam-requests')['path'],
            ];
        }

        ProposalExamRequest::create([
            'student_id' => $student->id,
            'thesis_proposal_id' => $student->activeProposal()->id,
            'student_notes' => $request->validated('student_notes'),
            'documents' => $documents,
            'status' => ApprovalStatus::Pending,
        ]);

        $notify->supervisors(
            $student,
            NotificationCategory::Document,
            'Pengajuan ujian proposal',
            "{$student->user->name} mengajukan ujian proposal dan menunggu verifikasi Anda.",
        );

        return to_route('proposal-exam-requests.index')->with('success', 'Pengajuan ujian proposal berhasil dikirim dan menunggu verifikasi.');
    }

    /**
     * @return array{eligible: bool, checks: array<int, array{label: string, met: bool, detail: string}>, summary: string}|null
     */
    private function eligibility(Request $request): ?array
    {
        $student = $request->user()->student;

        return $student === null ? null : ExamEligibility::forProposalExam($student)->toArray();
    }
}
