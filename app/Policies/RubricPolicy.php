<?php

namespace App\Policies;

use App\Models\User;

class RubricPolicy
{
    public function manage(User $user): bool
    {
        return $user->can('rubric.manage');
    }
}
