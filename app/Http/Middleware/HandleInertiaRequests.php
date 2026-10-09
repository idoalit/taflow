<?php

namespace App\Http\Middleware;

use App\Http\Resources\NotificationResource;
use App\Models\User;
use App\Support\Impersonation;
use App\Support\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $this->authUser($this->currentUser($request)),
            ],
            // Null for everybody but a superadmin looking through another
            // account, where the shell has to say so on every page.
            'impersonation' => fn (): ?array => $this->impersonation($request),
            'thesis' => $this->thesisSettings($this->currentUser($request)),
            'notificationFeed' => $this->notificationFeed($this->currentUser($request)),
            'flash' => [
                'success' => fn () => $this->flash($request, 'success'),
                'error' => fn () => $this->flash($request, 'error'),
                'importResult' => fn () => $request->hasSession()
                    ? $request->session()->get('importResult')
                    : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Shape the authenticated user for the client, including the role and
     * permission set the interface uses to decide what to render.
     *
     * @return array<string, mixed>|null
     */
    private function authUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            ...$user->only(['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at']),
            'initials' => $user->initials(),
            'role' => $user->primaryRole()?->value,
            'role_label' => $user->primaryRole()?->label(),
            // Narrowed to the active workspace's role by ResolveWorkspace, so a
            // user who wears two hats only ships the permissions of the one
            // they are currently wearing.
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'workspace' => $user->activeWorkspace()?->toArray(),
            'workspaces' => $user->workspaces()
                ->map(fn (Workspace $workspace): array => $workspace->toArray())
                ->values(),
        ];
    }

    /**
     * Error pages are rendered with shared data even for requests that never
     * reached the web middleware — an unmatched 404, say — so neither the guard
     * nor the session can be assumed to exist here.
     */
    private function currentUser(Request $request): ?User
    {
        return $request->hasSession() ? $request->user() : null;
    }

    /**
     * @return array{impersonator: string}|null
     */
    private function impersonation(Request $request): ?array
    {
        $impersonator = Impersonation::impersonator($request);

        return $impersonator === null ? null : ['impersonator' => $impersonator->name];
    }

    private function flash(Request $request, string $key): ?string
    {
        return $request->hasSession() ? $request->session()->get($key) : null;
    }

    /**
     * The bell sits in the header on every screen, so its badge and preview
     * list travel as shared data rather than being fetched per page. Named
     * distinctly from the Notifikasi page's own `notifications` prop, which
     * would otherwise overwrite it on that one route.
     *
     * @return array{unread: int, recent: AnonymousResourceCollection}|null
     */
    private function notificationFeed(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'unread' => $user->unreadNotifications()->count(),
            'recent' => NotificationResource::collection($user->notifications()->limit(5)->get()),
        ];
    }

    /**
     * Per-program-studi rules the interface needs in order to decide what to
     * show — currently only whether a proposal exam exists at all, which the
     * sidebar uses to drop "Pengajuan Ujian". Null for anyone who is not a
     * student, since the setting is scoped to their own department.
     *
     * @return array{requiresProposalExam: bool}|null
     */
    private function thesisSettings(?User $user): ?array
    {
        $student = $user?->student;

        if ($student === null) {
            return null;
        }

        return [
            'requiresProposalExam' => $student->department->activeThesisSetting()->requires_proposal_exam,
        ];
    }
}
