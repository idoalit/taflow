<?php

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Middleware\ResolveWorkspace;
use App\Models\AuditLog;
use App\Models\ThesisProposal;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

beforeEach(function (): void {
    seedRoles();
});

/**
 * A request made from inside an impersonation: signed in as the target, with
 * the superadmin's id carried in the session.
 */
function impersonating(User $superadmin, User $target): TestCase
{
    return test()->actingAs($target)->withSession([Impersonation::SESSION_KEY => $superadmin->id]);
}

/**
 * @return array<string, string>
 */
function proposalPayload(): array
{
    return [
        'title' => 'Judul yang Tidak Boleh Tersimpan',
        'background' => 'Latar belakang.',
        'problem_formulation' => 'Rumusan masalah.',
        'objectives' => 'Tujuan.',
        'methodology' => 'Metodologi.',
    ];
}

describe('starting', function (): void {
    it('hands the session to the account and records who did it', function (): void {
        $superadmin = makeSuperAdmin();
        $student = makeStudent();

        $this->actingAs($superadmin)
            ->post(route('superadmin.users.impersonation.store', $student->user))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(Impersonation::SESSION_KEY, $superadmin->id);

        $this->assertAuthenticatedAs($student->user);

        $log = AuditLog::where('action', AuditAction::ImpersonationStarted->value)->sole();

        expect($log->user_id)->toBe($superadmin->id)
            ->and($log->auditable_id)->toBe($student->user_id);
    });

    it('leaves behind what the superadmin had confirmed and chosen', function (): void {
        $student = makeStudent();

        $this->actingAs(makeSuperAdmin())
            ->withSession([
                'auth.password_confirmed_at' => time(),
                ResolveWorkspace::SESSION_KEY => 'super-admin:all',
            ])
            ->post(route('superadmin.users.impersonation.store', $student->user))
            ->assertSessionMissing('auth.password_confirmed_at')
            ->assertSessionMissing(ResolveWorkspace::SESSION_KEY);
    });

    it('shows the application as the account sees it, and says whose screen it is', function (): void {
        $superadmin = makeSuperAdmin();
        $student = makeStudent();

        impersonating($superadmin, $student->user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('thesis/dashboard/student')
                ->where('auth.user.id', $student->user_id)
                ->where('auth.user.role', 'student')
                ->where('impersonation.impersonator', $superadmin->name));
    });

    it('says nothing about impersonation to somebody signed in as themselves', function (): void {
        $this->actingAs(makeStudent()->user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('impersonation', null));
    });

    it('refuses an account that must not be looked through', function (Closure $target, string $reason): void {
        $superadmin = makeSuperAdmin();
        $account = $target($superadmin);

        $this->actingAs($superadmin)
            ->post(route('superadmin.users.impersonation.store', $account))
            ->assertForbidden()
            ->assertInertia(fn ($page) => $page->where('reason', $reason));

        $this->assertAuthenticatedAs($superadmin);
        expect(AuditLog::count())->toBe(0);
    })->with([
        'the superadmin\'s own' => [
            fn (User $superadmin): User => $superadmin,
            'Ini akun Anda sendiri.',
        ],
        'another superadmin\'s' => [
            fn (): User => makeSuperAdmin(),
            'Akun superadmin lain tidak dapat dilihat dengan cara ini.',
        ],
        'one with no workspace' => [
            fn (): User => User::factory()->create(),
            'Akun ini belum memiliki workspace yang dapat dibuka, jadi belum ada yang bisa dilihat.',
        ],
    ]);

    it('is not available to an Admin Prodi', function (): void {
        $department = makeDepartment();
        $admin = makeAdmin($department);
        $student = makeStudent($department);

        $this->actingAs($admin)
            ->post(route('superadmin.users.impersonation.store', $student->user))
            ->assertForbidden();

        $this->assertAuthenticatedAs($admin);
    });

    it('tells the superadmin on the account page whether it can be looked through', function (): void {
        $superadmin = makeSuperAdmin();

        $this->actingAs($superadmin)
            ->get(route('superadmin.users.show', makeStudent()->user))
            ->assertInertia(fn ($page) => $page
                ->where('impersonable.allowed', true)
                // The shell's own flag stays off: nobody is being looked through.
                ->where('impersonation', null));

        $this->actingAs($superadmin)
            ->get(route('superadmin.users.show', makeSuperAdmin()))
            ->assertInertia(fn ($page) => $page
                ->where('impersonable.allowed', false)
                ->where('impersonable.reason', 'Akun superadmin lain tidak dapat dilihat dengan cara ini.'));
    });
});

describe('looking only', function (): void {
    it('lets every screen of the account be read', function (): void {
        $student = makeStudent();
        ThesisProposal::factory()->for($student)->for($student->department)->create();

        impersonating(makeSuperAdmin(), $student->user)
            ->get(route('proposals.index'))
            ->assertOk();
    });

    it('refuses a write and says why', function (): void {
        $student = makeStudent();

        impersonating(makeSuperAdmin(), $student->user)
            ->post(route('proposals.store'), proposalPayload())
            ->assertForbidden()
            ->assertInertia(fn ($page) => $page->where(
                'reason',
                fn (string $reason): bool => str_contains($reason, "melihat sebagai {$student->user->name}")
                    && str_contains($reason, 'tidak ada yang dapat diubah'),
            ));

        expect(ThesisProposal::count())->toBe(0);
    });

    it('sends a refused form back to its page as a failure, not as a save', function (): void {
        $student = makeStudent();

        impersonating(makeSuperAdmin(), $student->user)
            ->from(route('proposals.create'))
            ->withHeader('X-Inertia', 'true')
            ->post(route('proposals.store'), proposalPayload())
            ->assertRedirect(route('proposals.create'))
            ->assertSessionHasErrors('impersonation')
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'tidak ada yang dapat diubah'));

        expect(ThesisProposal::count())->toBe(0);
    });

    it('refuses every kind of write, not only posts', function (string $method, string $route, array $payload): void {
        $student = makeStudent();
        $before = $student->user->only(['name', 'email', 'password']);

        impersonating(makeSuperAdmin(), $student->user)
            ->{$method}(route($route), $payload)
            ->assertForbidden();

        expect($student->user->refresh()->only(['name', 'email', 'password']))->toBe($before);
    })->with([
        'changing the name and surel' => ['patch', 'profile.update', ['name' => 'Diganti', 'email' => 'diganti@kampus.test']],
        'changing the password' => ['put', 'user-password.update', [
            'current_password' => 'password',
            'password' => 'KataSandiBaru!2026',
            'password_confirmation' => 'KataSandiBaru!2026',
        ]],
        'marking notifications read' => ['post', 'notifications.read-all', []],
    ]);

    it('does not sign the account\'s owner out of their own devices', function (): void {
        $student = makeStudent();
        $student->user->forceFill(['remember_token' => 'token-milik-pemilik'])->save();

        impersonating(makeSuperAdmin(), $student->user)
            ->post(route('logout'))
            ->assertForbidden();

        expect($student->user->refresh()->remember_token)->toBe('token-milik-pemilik');
    });

    it('still lets the superadmin move between the account\'s workspaces', function (): void {
        $first = makeDepartment();
        $second = makeDepartment();
        $lecturer = assignSupervisor(makeStudent($first));
        assignSupervisor(makeStudent($second), $lecturer);

        impersonating(makeSuperAdmin(), $lecturer->user)
            ->post(route('workspace.update'), ['workspace' => "lecturer:{$second->id}"])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas(ResolveWorkspace::SESSION_KEY, "lecturer:{$second->id}");
    });

    it('never opens the system screens, whoever is behind the session', function (): void {
        $admin = makeAdmin(makeDepartment());

        impersonating(makeSuperAdmin(), $admin)
            ->get(route('superadmin.users.index'))
            ->assertForbidden();
    });
});

describe('leaving', function (): void {
    it('gives the session back to the superadmin and records it', function (): void {
        $superadmin = makeSuperAdmin();
        $student = makeStudent();

        impersonating($superadmin, $student->user)
            ->delete(route('impersonation.destroy'))
            ->assertRedirect(route('superadmin.users.show', $student->user))
            ->assertSessionMissing(Impersonation::SESSION_KEY);

        $this->assertAuthenticatedAs($superadmin);

        $log = AuditLog::where('action', AuditAction::ImpersonationEnded->value)->sole();

        expect($log->user_id)->toBe($superadmin->id)
            ->and($log->auditable_id)->toBe($student->user_id);
    });

    it('changes nothing for somebody who was never impersonating', function (): void {
        $student = makeStudent();

        $this->actingAs($student->user)
            ->delete(route('impersonation.destroy'))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($student->user);
        expect(AuditLog::count())->toBe(0);
    });

    it('ends the session outright when there is no superadmin left to return to', function (): void {
        $superadmin = makeSuperAdmin();
        $student = makeStudent();
        $student->user->forceFill(['remember_token' => 'token-milik-pemilik'])->save();
        $superadmin->syncRoles([UserRole::Admin->value]);

        /** @var TestResponse $response */
        $response = impersonating($superadmin, $student->user)->delete(route('impersonation.destroy'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        expect($student->user->refresh()->remember_token)->toBe('token-milik-pemilik');
    });
});
