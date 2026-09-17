<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Enums\UserRole;
use App\Models\Department;
use Illuminate\Http\Request;

/**
 * Every Admin Prodi screen works on exactly one program studi: the one the
 * active workspace names. Reading it here rather than in each controller keeps
 * a screen from ever widening its own scope by accident.
 */
trait ScopesToDepartment
{
    protected function department(Request $request): Department
    {
        $workspace = $request->user()->activeWorkspace();

        abort_if(
            $workspace === null || $workspace->role !== UserRole::Admin || $workspace->department === null,
            403,
            'Halaman ini hanya tersedia pada workspace Admin Program Studi. '
            .'Gunakan menu "Ganti Workspace" di bawah sidebar untuk berpindah.',
        );

        return $workspace->department;
    }
}
