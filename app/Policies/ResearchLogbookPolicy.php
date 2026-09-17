<?php

namespace App\Policies;

use App\Actions\Thesis\ThesisPhaseAccess;
use App\Models\ResearchLogbook;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ResearchLogbookPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('logbook.view');
    }

    public function view(User $user, ResearchLogbook $logbook): bool
    {
        return $user->can('logbook.view') && $logbook->student->isVisibleTo($user);
    }

    /**
     * The logbook records research activity, so it only opens once the student
     * has actually reached the research phase — the same gate as research-stage
     * guidance. A student still working on their proposal has nothing to log.
     */
    public function create(User $user): Response
    {
        if (! $user->can('logbook.create') || $user->student === null) {
            return Response::deny('Hanya mahasiswa yang dapat mengisi logbook penelitian.');
        }

        $access = ThesisPhaseAccess::forStudent($user->student);

        return $access->researchPhaseOpen()
            ? Response::allow()
            : Response::deny($access->researchPhaseReason());
    }

    /**
     * Editing an existing entry stays with its owner. The phase gate is not
     * repeated here: an entry can only exist if the phase was open when it was
     * written, and a student must still be able to correct it afterwards.
     */
    public function update(User $user, ResearchLogbook $logbook): bool
    {
        return $user->student?->id === $logbook->student_id;
    }

    public function delete(User $user, ResearchLogbook $logbook): bool
    {
        return $this->update($user, $logbook);
    }
}
