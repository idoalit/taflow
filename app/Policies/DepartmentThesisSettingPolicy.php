<?php

namespace App\Policies;

use App\Models\User;

class DepartmentThesisSettingPolicy
{
    public function manageThesisSettings(User $user): bool
    {
        return $user->can('thesis-setting.manage');
    }
}
