<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('user.view');
    }

    /**
     * Removing an account takes its proposals, guidance sessions, logbook and
     * exam records with it, so it is never self-service. A superadmin may
     * remove anyone; a program admin only the students of the departments they
     * administer.
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
            return Response::allow();
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
}
