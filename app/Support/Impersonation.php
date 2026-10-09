<?php

namespace App\Support;

use App\Http\Middleware\ResolveWorkspace;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A superadmin looking at the application through somebody else's account.
 *
 * The session is the other person's for as long as it lasts — that is what
 * makes the screens, the menu and the data exactly theirs — and all it carries
 * of the superadmin is an id to go back to. Nothing can be changed meanwhile:
 * RestrictImpersonation refuses every write, because a signature or a mark
 * made here would be recorded as the other person's own.
 */
final class Impersonation
{
    public const SESSION_KEY = 'impersonator_id';

    public static function active(Request $request): bool
    {
        return $request->hasSession() && $request->session()->has(self::SESSION_KEY);
    }

    /**
     * The superadmin behind the session, or null when nobody is being
     * impersonated — or when the one who started it no longer exists.
     */
    public static function impersonator(Request $request): ?User
    {
        if (! self::active($request)) {
            return null;
        }

        return User::query()->whereKey($request->session()->get(self::SESSION_KEY))->first();
    }

    /**
     * Hand the session to the target. What the superadmin had confirmed or
     * chosen does not come along: a password they confirmed must not open the
     * target's security settings, and their workspace is not one of the
     * target's.
     */
    public static function start(Request $request, User $impersonator, User $target): void
    {
        Auth::login($target);

        $request->session()->forget([ResolveWorkspace::SESSION_KEY, 'auth.password_confirmed_at']);
        $request->session()->put(self::SESSION_KEY, $impersonator->id);
    }

    public static function stop(Request $request, User $impersonator): void
    {
        Auth::login($impersonator);

        $request->session()->forget([
            self::SESSION_KEY,
            ResolveWorkspace::SESSION_KEY,
            'auth.password_confirmed_at',
        ]);
    }
}
