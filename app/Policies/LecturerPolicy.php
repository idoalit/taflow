<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Lecturer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LecturerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('lecturer.view');
    }

    public function view(User $user, Lecturer $lecturer): bool
    {
        return $user->can('lecturer.view');
    }

    /**
     * Registering a new dosen, or adding an existing one to the program studi
     * the workspace is in.
     */
    public function create(User $user): bool
    {
        return $user->can('lecturer.manage');
    }

    public function manage(User $user, Lecturer $lecturer): bool
    {
        return $user->can('lecturer.manage');
    }

    /**
     * Taking a dosen out of a program studi. A prodi may let go of a dosen it
     * borrowed, never of its own: where a dosen belongs is the institution's
     * decision, made by the superadmin.
     */
    public function release(User $user, Lecturer $lecturer, Department $department): Response
    {
        if (! $user->can('lecturer.manage')) {
            return Response::deny('Anda tidak berwenang mengelola data dosen.');
        }

        if ($lecturer->homebase()?->is($department)) {
            return Response::deny(
                "{$department->name} adalah homebase dosen ini. Hanya superadmin yang dapat memindahkan homebase.",
            );
        }

        $seats = $lecturer->panelSeatsIn($department);

        if ($seats > 0) {
            return Response::deny(
                "Dosen ini masih menjadi pembimbing atau penguji bagi {$seats} mahasiswa {$department->name}. "
                .'Ganti dulu penugasannya pada mahasiswa tersebut.',
            );
        }

        return Response::allow();
    }
}
