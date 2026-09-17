<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('student.view');
    }

    public function view(User $user, Student $student): bool
    {
        return $user->can('student.view') && $student->isVisibleTo($user);
    }

    /**
     * Watching the whole cohort's progress, which is a different right from
     * being able to change any of it.
     */
    public function monitor(User $user): bool
    {
        return $user->can('guidance.monitor');
    }

    public function viewReports(User $user): bool
    {
        return $user->can('report.view');
    }

    public function import(User $user): bool
    {
        return $user->can('data.import');
    }

    public function manage(User $user, Student $student): bool
    {
        return $user->can('student.manage') && $student->isVisibleTo($user);
    }

    /**
     * Building a student's panel is the program studi's job, and only for the
     * program studi the workspace is currently in.
     */
    public function assignSupervisors(User $user, Student $student): Response
    {
        if (! $user->can('supervisor.assign')) {
            return Response::deny('Anda tidak berwenang menugaskan dosen pembimbing.');
        }

        return $student->isVisibleTo($user)
            ? Response::allow()
            : Response::deny('Mahasiswa ini bukan mahasiswa program studi yang sedang Anda kelola.');
    }
}
