<?php

namespace App\Policies;

use App\Models\User;

class RolePolicy
{
    /**
     * Registered against spatie's Role model in AppServiceProvider, since that
     * class is not ours to annotate.
     */
    public function manage(User $user): bool
    {
        return $user->can('role.manage');
    }
}
