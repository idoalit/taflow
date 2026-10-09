<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;

/**
 * Sends an account the link it sets its password with. No account is ever
 * given a password by somebody else, so this is both the invitation for a new
 * one and the way back in for a locked-out one.
 */
class UserInvitationController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            return back()->with('error', __($status));
        }

        AuditLog::record(
            $user,
            AuditAction::UserInvited,
            $request->user(),
            changes: ['name' => $user->name, 'email' => $user->email],
            request: $request,
        );

        return back()->with('success', "Tautan aktivasi dikirim ke {$user->email}.");
    }
}
