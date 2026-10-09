<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The system screens answer only to the Superadmin seat. A superadmin who has
 * stepped into a prodi as its Admin Prodi is, for that while, an Admin Prodi —
 * and is told how to step back out rather than being let through.
 */
class EnsureSuperAdminWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(
            $request->user()?->activeWorkspace()?->role !== UserRole::SuperAdmin,
            403,
            'Halaman ini hanya tersedia pada workspace Superadmin. '
            .'Gunakan menu "Ganti Workspace" di bawah sidebar untuk berpindah.',
        );

        return $next($request);
    }
}
