<?php

namespace App\Policies;

use App\Models\Lecturer;
use App\Models\User;

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

    public function manage(User $user, Lecturer $lecturer): bool
    {
        return $user->can('lecturer.manage');
    }
}
