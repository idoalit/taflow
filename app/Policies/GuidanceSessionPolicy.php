<?php

namespace App\Policies;

use App\Actions\Thesis\ThesisPhaseAccess;
use App\Enums\GuidanceStage;
use App\Enums\GuidanceStatus;
use App\Models\GuidanceSession;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GuidanceSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('guidance.view');
    }

    public function view(User $user, GuidanceSession $session): bool
    {
        return $user->can('guidance.view') && $session->student->isVisibleTo($user);
    }

    /**
     * Sessions are opened by the student, and only for a stage that is actually
     * open to them — research guidance sits behind the proposal exam. The rule
     * itself lives in ThesisPhaseAccess; this is the authorization backstop
     * for a stale link or a hand-crafted request.
     */
    public function create(User $user, ?GuidanceStage $stage = null): Response
    {
        if (! $user->can('guidance.create') || $user->student === null) {
            return Response::deny('Hanya mahasiswa yang dapat membuat sesi bimbingan.');
        }

        $access = ThesisPhaseAccess::forStudent($user->student);
        $reason = $access->reasonFor($stage ?? GuidanceStage::Proposal);

        return $reason === null ? Response::allow() : Response::deny($reason);
    }

    /**
     * Posting a message needs an ongoing async session the user takes part in.
     */
    public function participate(User $user, GuidanceSession $session): bool
    {
        return $user->can('guidance.participate')
            && $session->status === GuidanceStatus::Ongoing
            && $session->isAsync()
            && ($session->student->user_id === $user->id || $session->student->isSupervisedBy($user));
    }

    public function cancel(User $user, GuidanceSession $session): bool
    {
        return $session->status === GuidanceStatus::Ongoing
            && ($session->student->user_id === $user->id || $session->student->isSupervisedBy($user));
    }

    public function complete(User $user, GuidanceSession $session): bool
    {
        return $user->can('guidance.complete')
            && $session->status === GuidanceStatus::Ongoing
            && $session->student->isSupervisedBy($user);
    }
}
