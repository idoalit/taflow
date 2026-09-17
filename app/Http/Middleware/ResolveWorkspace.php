<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the request into the workspace the user last chose. A stored choice that
 * no longer belongs to them — a prodi they were removed from, a role that was
 * revoked — is dropped rather than honoured, so losing access takes effect on
 * the next request instead of at the next login.
 */
class ResolveWorkspace
{
    public const SESSION_KEY = 'workspace';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $request->hasSession()) {
            return $next($request);
        }

        $stored = $request->session()->get(self::SESSION_KEY);
        $workspace = is_string($stored) ? $user->workspaceFromKey($stored) : null;

        if ($workspace === null) {
            $request->session()->forget(self::SESSION_KEY);
            $workspace = $user->workspaces()->first();
        }

        if ($workspace !== null) {
            $user->useWorkspace($workspace);
        }

        return $next($request);
    }
}
