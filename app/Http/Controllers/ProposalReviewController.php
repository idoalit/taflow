<?php

namespace App\Http\Controllers;

use App\Actions\Thesis\NotifyThesisParticipants;
use App\Actions\Thesis\OpenPerceptionAlignment;
use App\Enums\NotificationCategory;
use App\Enums\ProposalStatus;
use App\Models\ThesisProposal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProposalReviewController extends Controller
{
    /**
     * A supervisor's approval is what starts Persamaan Persepsi — the student
     * cannot open guidance sessions until this happens.
     */
    public function approve(
        Request $request,
        ThesisProposal $proposal,
        OpenPerceptionAlignment $openAlignment,
        NotifyThesisParticipants $notify,
    ): RedirectResponse {
        Gate::authorize('review', $proposal);

        DB::transaction(function () use ($request, $proposal, $openAlignment): void {
            $proposal->update([
                'status' => ProposalStatus::Approved,
                'approved_by' => $request->user()->lecturer->id,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            $openAlignment($proposal);
        });

        $notify->student(
            $proposal->student,
            NotificationCategory::Approval,
            'Proposal Anda disetujui',
            "{$request->user()->name} menyetujui proposal \"{$proposal->title}\".",
            route('proposals.show', $proposal),
        );

        return back()->with('success', 'Proposal disetujui. Persamaan Persepsi dimulai.');
    }

    public function reject(
        Request $request,
        ThesisProposal $proposal,
        NotifyThesisParticipants $notify,
    ): RedirectResponse {
        Gate::authorize('review', $proposal);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], ['rejection_reason' => 'alasan penolakan']);

        $proposal->update([
            'status' => ProposalStatus::NeedsRevision,
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        $notify->student(
            $proposal->student,
            NotificationCategory::Document,
            'Proposal perlu revisi',
            "{$request->user()->name} meminta revisi: {$validated['rejection_reason']}",
            route('proposals.show', $proposal),
        );

        return back()->with('success', 'Proposal ditandai perlu revisi.');
    }
}
