<?php

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a superadmin is looking through somebody else's account, the account
 * can be read and nothing in it can be written. Whatever is saved, approved,
 * marked or signed in this application is recorded under the name of whoever
 * is signed in, and here that name belongs to someone who is not at the
 * keyboard.
 *
 * Refusing by method rather than by route means a screen added later is
 * read-only from the day it is written, without anybody remembering to list it.
 */
class RestrictImpersonation
{
    /**
     * The two writes that change nothing of the account's own: leaving, and
     * moving between the workspaces it already has. Signing out is not one of
     * them — it would cycle the account's "remember me" token and sign its
     * real owner out of their own devices.
     */
    private const ALLOWED = ['impersonation.destroy', 'workspace.update'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! Impersonation::active($request) || $request->isMethodSafe()) {
            return $next($request);
        }

        if ($request->routeIs(...self::ALLOWED)) {
            return $next($request);
        }

        $message = 'Anda sedang melihat sebagai '.$request->user()?->name.'. '
            .'Dalam mode ini tidak ada yang dapat diubah. Kembali ke akun Anda untuk melakukan perubahan.';

        // An Inertia form is sent back to the page it came from with the reason
        // as a toast, so the screen being inspected stays on screen. It goes
        // back as an error, not merely a redirect: a form that saw a plain
        // redirect would tick "Tersimpan" for something that was refused.
        if ($request->header('X-Inertia')) {
            return back()->withErrors(['impersonation' => $message])->with('error', $message);
        }

        abort(403, $message);
    }
}
