<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * "Lihat sebagai": the superadmin sees the application exactly as one account
 * sees it, to find out why something does or does not appear for them. Both
 * ends of it are written to the audit trail.
 */
class ImpersonationController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('impersonate', $user);

        $impersonator = $request->user();

        AuditLog::record(
            $user,
            AuditAction::ImpersonationStarted,
            $impersonator,
            changes: ['name' => $user->name, 'email' => $user->email],
            request: $request,
        );

        Impersonation::start($request, $impersonator, $user);

        return to_route('dashboard');
    }

    /**
     * Reached from the impersonated session, so it sits outside the superadmin
     * routes: for as long as this lasts the signed-in account is not one.
     */
    public function destroy(Request $request): RedirectResponse
    {
        if (! Impersonation::active($request)) {
            return to_route('dashboard');
        }

        $impersonator = Impersonation::impersonator($request);
        $target = $request->user();

        // The superadmin's account was removed, or lost the role, while this
        // was going on. There is nobody to hand the session back to — and the
        // session is ended on this device only, so the account's real owner
        // stays signed in on theirs.
        if ($impersonator === null || ! $impersonator->isSuperAdmin()) {
            Auth::guard('web')->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login');
        }

        Impersonation::stop($request, $impersonator);

        AuditLog::record(
            $target,
            AuditAction::ImpersonationEnded,
            $impersonator,
            changes: ['name' => $target->name, 'email' => $target->email],
            request: $request,
        );

        return to_route('superadmin.users.show', $target)
            ->with('success', "Anda kembali ke akun sendiri setelah melihat sebagai {$target->name}.");
    }
}
