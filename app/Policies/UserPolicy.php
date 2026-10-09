<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ExamMinutes;
use App\Models\ExamParticipant;
use App\Models\ProposalExam;
use App\Models\ResearchExam;
use App\Models\StudentSupervisor;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('user.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->can('user.view');
    }

    public function create(User $user): bool
    {
        return $user->can('user.manage');
    }

    public function update(User $user, User $target): bool
    {
        return $user->can('user.manage');
    }

    /**
     * Removing an account takes its proposals, guidance sessions, logbook and
     * exam records with it, so it is never self-service. A superadmin may
     * remove anyone whose removal harms nobody else; a program admin only the
     * students of the departments they administer.
     */
    public function delete(User $user, User $target): Response
    {
        if ($user->is($target)) {
            return Response::deny(
                'Akun tidak dapat dihapus sendiri. Hubungi admin program studi apabila '
                .'akun Anda perlu dinonaktifkan.'
            );
        }

        if ($user->primaryRole() === UserRole::SuperAdmin) {
            return $this->deleteAsSuperAdmin($target);
        }

        if ($user->primaryRole() !== UserRole::Admin) {
            return Response::deny('Hanya admin program studi atau superadmin yang dapat menghapus akun.');
        }

        $student = $target->student;

        if ($student === null || ! $student->isVisibleTo($user)) {
            return Response::deny('Anda hanya dapat menghapus akun mahasiswa pada program studi Anda.');
        }

        return Response::allow();
    }

    /**
     * What stops even a superadmin is an account whose rows other people's
     * records hang from. Exams cascade from whoever scheduled them and panels
     * from whoever sat on them, so deleting such an account would quietly
     * delete a student's exam. The system can never be left without a
     * superadmin this way: the only one who could be the last is the one
     * asking, and nobody deletes themselves.
     */
    private function deleteAsSuperAdmin(User $target): Response
    {
        $trace = $this->traceInOthersRecords($target);

        if ($trace !== null) {
            return Response::deny(
                "Akun ini tidak dapat dihapus karena {$trace}. Menghapusnya akan ikut menghapus data ujian "
                .'atau bimbingan mahasiswa. Cabut perannya atau nonaktifkan statusnya sebagai gantinya.'
            );
        }

        return Response::allow();
    }

    private function traceInOthersRecords(User $target): ?string
    {
        $scheduled = ProposalExam::query()->where('scheduled_by', $target->id)->exists()
            || ResearchExam::query()->where('scheduled_by', $target->id)->exists();

        if ($scheduled) {
            return 'pernah menjadwalkan ujian';
        }

        if (ExamMinutes::query()->where('decided_by', $target->id)->exists()) {
            return 'pernah menetapkan berita acara ujian';
        }

        $lecturer = $target->lecturer;

        if ($lecturer === null) {
            return null;
        }

        if (StudentSupervisor::query()->where('lecturer_id', $lecturer->id)->exists()) {
            return 'masih tercatat sebagai pembimbing atau penguji mahasiswa';
        }

        if (ExamParticipant::query()->where('lecturer_id', $lecturer->id)->exists()) {
            return 'pernah duduk di panel ujian';
        }

        return null;
    }
}
