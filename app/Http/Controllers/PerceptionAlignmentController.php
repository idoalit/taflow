<?php

namespace App\Http\Controllers;

use App\Actions\Thesis\NotifyThesisParticipants;
use App\Enums\ApprovalStatus;
use App\Enums\NotificationCategory;
use App\Enums\ProposalStatus;
use App\Http\Resources\PerceptionAlignmentResource;
use App\Http\Resources\ProposalDetailResource;
use App\Models\PerceptionAlignment;
use App\Models\Student;
use App\Models\ThesisProposal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PerceptionAlignmentController extends Controller
{
    /**
     * Persamaan Persepsi is tracked per proposal, so the list is a list of
     * proposals that have an open or finished alignment round.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PerceptionAlignment::class);

        $proposals = ThesisProposal::query()
            ->whereIn('student_id', Student::query()->visibleTo($request->user())->select('id'))
            ->whereHas('perceptionAlignments')
            ->with(['student.user', 'perceptionAlignments.lecturer.user'])
            ->latest('id')
            ->get();

        $lecturerId = $request->user()->lecturer?->id;

        return Inertia::render('thesis/perception-alignments/index', [
            'rounds' => $proposals->map(fn (ThesisProposal $proposal): array => [
                'id' => $proposal->id,
                'title' => $proposal->title,
                'student' => $proposal->student->user->name,
                'student_number' => $proposal->student->student_number,
                'my_role' => $this->roleLabel($proposal, $lecturerId),
                'status_label' => $proposal->alignmentStatusLabel(),
                'needs_me' => $proposal->perceptionAlignments
                    ->contains(fn (PerceptionAlignment $a): bool => $a->lecturer_id === $lecturerId && $a->isMyTurn()),
            ])->values(),
        ]);
    }

    public function show(Request $request, ThesisProposal $proposal): Response
    {
        Gate::authorize('view', $proposal);

        $proposal->load([
            'student.user', 'student.department', 'student.supervisors.user',
            'approvedBy.user', 'perceptionAlignments.lecturer.user', 'perceptionAlignments.thesisProposal',
        ]);

        return Inertia::render('thesis/perception-alignments/show', [
            'proposal' => new ProposalDetailResource($proposal),
            'alignments' => PerceptionAlignmentResource::collection(
                $proposal->perceptionAlignments->sortBy('supervisor_order')->values()
            ),
            'statusLabel' => $proposal->alignmentStatusLabel(),
        ]);
    }

    public function feedback(
        Request $request,
        PerceptionAlignment $perceptionAlignment,
        NotifyThesisParticipants $notify,
    ): RedirectResponse {
        Gate::authorize('giveFeedback', $perceptionAlignment);

        $validated = $request->validate([
            'feedback' => ['required', 'string', 'min:10', 'max:2000'],
            'preparation_requirements' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'feedback' => 'umpan balik',
            'preparation_requirements' => 'arahan persiapan',
        ]);

        $perceptionAlignment->update([
            ...$validated,
            'status' => ApprovalStatus::Approved,
            'responded_at' => now(),
        ]);

        $this->notifyNextStep($perceptionAlignment, $request, $notify);

        return back()->with('success', 'Umpan balik tersimpan.');
    }

    /**
     * Rejecting at this stage sends the proposal back for revision, which
     * closes the whole round.
     */
    public function reject(
        Request $request,
        PerceptionAlignment $perceptionAlignment,
        NotifyThesisParticipants $notify,
    ): RedirectResponse {
        Gate::authorize('reject', $perceptionAlignment);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], ['rejection_reason' => 'alasan penolakan']);

        $perceptionAlignment->update([
            'status' => ApprovalStatus::Rejected,
            'feedback' => $validated['rejection_reason'],
            'responded_at' => now(),
        ]);

        $perceptionAlignment->thesisProposal->update([
            'status' => ProposalStatus::NeedsRevision,
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        $notify->student(
            $perceptionAlignment->student,
            NotificationCategory::Document,
            'Proposal perlu revisi',
            "{$request->user()->name} menolak proposal pada tahap Persamaan Persepsi: {$validated['rejection_reason']}",
            route('proposals.show', $perceptionAlignment->thesisProposal),
        );

        return to_route('perception-alignments.index')->with('success', 'Proposal ditolak dan dikembalikan ke mahasiswa.');
    }

    /**
     * Tells whoever is next: the following supervisor, or the student once the
     * round is complete.
     */
    private function notifyNextStep(
        PerceptionAlignment $alignment,
        Request $request,
        NotifyThesisParticipants $notify,
    ): void {
        $proposal = $alignment->thesisProposal;
        $remaining = $proposal->perceptionAlignments()
            ->where('status', ApprovalStatus::Pending)
            ->exists();

        if ($remaining) {
            $notify->supervisors(
                $proposal->student,
                NotificationCategory::Approval,
                'Giliran umpan balik Persamaan Persepsi',
                "{$request->user()->name} telah mengisi umpan balik untuk \"{$proposal->title}\". Giliran Anda berikutnya.",
                route('perception-alignments.show', $proposal),
                $request->user(),
            );

            return;
        }

        $notify->student(
            $proposal->student,
            NotificationCategory::Approval,
            'Persamaan Persepsi selesai',
            'Seluruh pembimbing telah memberikan umpan balik. Anda dapat melanjutkan ke sesi bimbingan.',
            route('proposals.show', $proposal),
        );
    }

    private function roleLabel(ThesisProposal $proposal, ?int $lecturerId): string
    {
        $mine = $proposal->perceptionAlignments
            ->firstWhere('lecturer_id', $lecturerId);

        return $mine === null ? 'Penguji' : "Pembimbing {$mine->supervisor_order}";
    }
}
