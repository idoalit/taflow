<?php

namespace App\Policies;

use App\Enums\ApprovalStatus;
use App\Models\PerceptionAlignment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PerceptionAlignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('proposal.review');
    }

    public function view(User $user, PerceptionAlignment $alignment): bool
    {
        return $user->can('proposal.view') && $alignment->student->isVisibleTo($user);
    }

    /**
     * Only the supervisor the slot belongs to may write in it, and only once
     * the supervisor ahead of them has had their say.
     */
    public function giveFeedback(User $user, PerceptionAlignment $alignment): Response
    {
        if ($user->lecturer?->id !== $alignment->lecturer_id) {
            return Response::deny('Umpan balik hanya dapat diisi oleh pembimbing yang bersangkutan.');
        }

        if ($alignment->status !== ApprovalStatus::Pending) {
            return Response::deny('Umpan balik untuk persamaan persepsi ini sudah tersimpan.');
        }

        if (! $alignment->isMyTurn()) {
            return Response::deny('Menunggu Pembimbing Utama mengisi umpan balik terlebih dahulu.');
        }

        return Response::allow();
    }

    /**
     * Rejecting sends the proposal back to the student, so it stays with the
     * supervisors on the proposal.
     */
    public function reject(User $user, PerceptionAlignment $alignment): bool
    {
        return $user->can('proposal.review')
            && $user->lecturer?->id === $alignment->lecturer_id
            && $alignment->status === ApprovalStatus::Pending;
    }
}
