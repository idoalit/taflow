<?php

namespace App\Policies;

use App\Models\User;

class DepartmentPolicy
{
    /**
     * Program studi are created, renamed and archived by the superadmin alone;
     * an Admin Prodi runs the one it was given.
     */
    public function manage(User $user): bool
    {
        return $user->can('department.manage');
    }
}
