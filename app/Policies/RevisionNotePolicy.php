<?php

namespace App\Policies;

use App\Models\RevisionNote;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RevisionNotePolicy
{
    /**
     * The checklist is the student's to work through, and only once the result
     * has been released to them.
     */
    public function toggleRevision(User $user, RevisionNote $note): Response
    {
        $exam = $note->thesisExam();

        if ($exam === null || ! $exam->isReleased()) {
            return Response::deny('Daftar revisi belum dirilis.');
        }

        return $exam->student->user_id === $user->id
            ? Response::allow()
            : Response::deny('Hanya mahasiswa yang bersangkutan dapat menandai revisi.');
    }
}
