<?php

namespace App\Http\Controllers;

use App\Actions\Thesis\NotifyThesisParticipants;
use App\Actions\Thesis\StoreThesisDocument;
use App\Enums\NotificationCategory;
use App\Enums\ProposalStatus;
use App\Http\Requests\Thesis\StoreThesisProposalRequest;
use App\Http\Resources\ProposalDetailResource;
use App\Http\Resources\ProposalSummaryResource;
use App\Models\Student;
use App\Models\ThesisProposal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ThesisProposalController extends Controller
{
    /**
     * The student's proposal history. A student may only ever have one active
     * proposal, so the list doubles as the "can I submit a new one?" answer.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ThesisProposal::class);

        $proposals = ThesisProposal::query()
            ->whereIn('student_id', Student::query()->visibleTo($request->user())->select('id'))
            ->with('student.user')
            ->latest('id')
            ->get();

        return Inertia::render('thesis/proposals/index', [
            'proposals' => ProposalSummaryResource::collection($proposals),
            'canCreate' => Gate::allows('create', ThesisProposal::class),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', ThesisProposal::class);

        return Inertia::render('thesis/proposals/create');
    }

    public function store(
        StoreThesisProposalRequest $request,
        StoreThesisDocument $storeDocument,
        NotifyThesisParticipants $notify,
    ): RedirectResponse {
        Gate::authorize('create', ThesisProposal::class);

        $student = $request->user()->student;
        $document = $storeDocument($request->file('document'), $student, 'proposals');

        $proposal = ThesisProposal::create([
            ...$request->safe()->except('document'),
            'student_id' => $student->id,
            'department_id' => $student->department_id,
            'status' => ProposalStatus::Submitted,
            'document_path' => $document['path'],
            'document_name' => $document['name'],
            'document_size' => $document['size'],
        ]);

        $notify->supervisors(
            $student,
            NotificationCategory::Document,
            'Proposal baru diajukan',
            "{$student->user->name} mengajukan proposal \"{$proposal->title}\" untuk ditinjau.",
            route('proposals.show', $proposal),
        );

        return to_route('proposals.show', $proposal)
            ->with('success', 'Proposal berhasil diajukan dan menunggu tinjauan pembimbing.');
    }

    public function show(Request $request, ThesisProposal $proposal): Response
    {
        Gate::authorize('view', $proposal);

        $proposal->load([
            'student.user', 'student.department', 'student.supervisors.user',
            'approvedBy.user', 'perceptionAlignments.lecturer.user',
        ]);

        return Inertia::render('thesis/proposals/show', [
            'proposal' => new ProposalDetailResource($proposal),
            'can' => [
                'review' => Gate::allows('review', $proposal) && $proposal->isAwaitingReview(),
            ],
            'alignmentStatus' => $proposal->perceptionAlignments->isEmpty()
                ? null
                : $proposal->alignmentStatusLabel(),
        ]);
    }
}
