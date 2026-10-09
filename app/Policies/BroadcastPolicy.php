<?php

namespace App\Policies;

use App\Models\User;

class BroadcastPolicy
{
    public function manage(User $user): bool
    {
        return $user->can('broadcast.manage');
    }
}
