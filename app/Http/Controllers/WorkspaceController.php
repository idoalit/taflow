<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveWorkspace;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkspaceController extends Controller
{
    /**
     * Switch seats. The key is checked against the user's own workspaces, so a
     * forged one buys nothing.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            ['workspace' => ['required', 'string']],
            [],
            ['workspace' => 'workspace'],
        );

        $workspace = $request->user()->workspaceFromKey($validated['workspace']);

        if (! $workspace instanceof Workspace) {
            throw ValidationException::withMessages([
                'workspace' => 'Anda tidak memiliki akses ke workspace tersebut.',
            ]);
        }

        $request->session()->put(ResolveWorkspace::SESSION_KEY, $workspace->key());

        // Landing on the dashboard rather than back: the previous page often
        // belongs to the seat just left, and would only refuse entry.
        return to_route('dashboard')->with('success', "Berpindah ke {$workspace->label()}.");
    }
}
