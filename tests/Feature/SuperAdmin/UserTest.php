<?php

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    seedRoles();
});

describe('listing', function (): void {
    it('lists every account with its roles and program studi', function (): void {
        $department = makeDepartment();
        $student = makeStudent($department);
        $superadmin = makeSuperAdmin();

        $this->actingAs($superadmin)
            ->get(route('superadmin.users.index', ['search' => $student->user->email]))
            ->assertInertia(fn ($page) => $page
                ->component('superadmin/users/index')
                ->has('users.data', 1)
                ->where('users.data.0.name', $student->user->name)
                ->where('users.data.0.roles', ['Mahasiswa'])
                ->where('users.data.0.departments', [$department->name]));
    });

    it('narrows the list to one role', function (): void {
        makeStudent();
        $admin = makeAdmin(makeDepartment());

        $this->actingAs(makeSuperAdmin())
            ->get(route('superadmin.users.index', ['role' => UserRole::Admin->value]))
            ->assertInertia(fn ($page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.id', $admin->id));
    });

    it('narrows the list to one program studi, whatever the role', function (): void {
        $department = makeDepartment();
        $student = makeStudent($department);
        $admin = makeAdmin($department);
        $lecturer = assignSupervisor($student);
        makeStudent();

        $this->actingAs(makeSuperAdmin())
            ->get(route('superadmin.users.index', ['department' => $department->id]))
            ->assertInertia(fn ($page) => $page
                ->where('users.total', 3)
                ->where('users.data', fn ($rows): bool => collect($rows)->pluck('id')->sort()->values()->all()
                    === collect([$student->user_id, $admin->id, $lecturer->user_id])->sort()->values()->all()));
    });

    it('treats a search term as text, not as a pattern', function (): void {
        makeStudent();

        $this->actingAs(makeSuperAdmin())
            ->get(route('superadmin.users.index', ['search' => "' OR 1=1 --"]))
            ->assertInertia(fn ($page) => $page->has('users.data', 0));
    });
});

describe('creating', function (): void {
    it('creates an Admin Prodi with no password and mails the activation link', function (): void {
        Notification::fake();
        $department = makeDepartment();
        $superadmin = makeSuperAdmin();

        $response = $this->actingAs($superadmin)->post(route('superadmin.users.store'), [
            'name' => 'Admin Baru',
            'email' => 'Admin.Baru@kampus.test',
            'role' => UserRole::Admin->value,
            'department_ids' => [$department->id],
        ]);

        $user = User::where('email', 'admin.baru@kampus.test')->sole();

        $response->assertRedirect(route('superadmin.users.show', $user));
        Notification::assertSentTo($user, ResetPassword::class);

        expect($user->hasRole(UserRole::Admin->value))->toBeTrue()
            ->and($user->departments()->pluck('departments.id')->all())->toBe([$department->id])
            ->and(Hash::check('password', $user->password))->toBeFalse()
            ->and(AuditLog::where('action', AuditAction::UserCreated->value)->sole()->user_id)->toBe($superadmin->id);
    });

    it('creates another superadmin without any program studi', function (): void {
        Notification::fake();

        $this->actingAs(makeSuperAdmin())->post(route('superadmin.users.store'), [
            'name' => 'Superadmin Kedua',
            'email' => 'kedua@kampus.test',
            'role' => UserRole::SuperAdmin->value,
        ])->assertRedirect();

        $user = User::where('email', 'kedua@kampus.test')->sole();

        expect($user->hasRole(UserRole::SuperAdmin->value))->toBeTrue()
            ->and($user->departments()->count())->toBe(0);
    });

    it('requires a program studi for an Admin Prodi', function (): void {
        $this->actingAs(makeSuperAdmin())
            ->post(route('superadmin.users.store'), [
                'name' => 'Admin Tanpa Prodi',
                'email' => 'tanpa@kampus.test',
                'role' => UserRole::Admin->value,
                'department_ids' => [],
            ])
            ->assertSessionHasErrors([
                'department_ids' => 'Pilih sedikitnya satu program studi untuk Admin Prodi.',
            ]);

        expect(User::where('email', 'tanpa@kampus.test')->exists())->toBeFalse();
    });

    it('rejects a surel that is already registered', function (): void {
        $existing = makeStudent()->user;

        $this->actingAs(makeSuperAdmin())
            ->post(route('superadmin.users.store'), [
                'name' => 'Kembaran',
                'email' => $existing->email,
                'role' => UserRole::SuperAdmin->value,
            ])
            ->assertSessionHasErrors(['email' => 'Surel ini sudah terdaftar.']);
    });

    it('cannot be used to create a mahasiswa or a dosen', function (string $role): void {
        $this->actingAs(makeSuperAdmin())
            ->post(route('superadmin.users.store'), [
                'name' => 'Lewat Belakang',
                'email' => 'belakang@kampus.test',
                'role' => $role,
            ])
            ->assertSessionHasErrors('role');
    })->with([
        'mahasiswa' => [UserRole::Student->value],
        'dosen' => [UserRole::Lecturer->value],
    ]);
});

describe('updating', function (): void {
    it('changes the name and surel of an account', function (): void {
        $user = makeAdmin(makeDepartment());

        $this->actingAs(makeSuperAdmin())
            ->put(route('superadmin.users.update', $user), ['name' => 'Nama Baru', 'email' => 'baru@kampus.test'])
            ->assertRedirect();

        expect($user->refresh()->name)->toBe('Nama Baru')
            ->and($user->email)->toBe('baru@kampus.test');
    });

    it('mails a fresh activation link on request', function (): void {
        Notification::fake();
        $user = makeAdmin(makeDepartment());

        $this->actingAs(makeSuperAdmin())
            ->post(route('superadmin.users.invitation.store', $user))
            ->assertSessionHas('success', "Tautan aktivasi dikirim ke {$user->email}.");

        Notification::assertSentTo($user, ResetPassword::class);
    });
});

describe('roles', function (): void {
    it('makes a dosen the Admin Prodi of the chosen program studi', function (): void {
        $department = makeDepartment();
        $lecturer = makeLecturer();

        $this->actingAs(makeSuperAdmin())
            ->put(route('superadmin.users.roles.update', $lecturer->user_id), [
                'is_super_admin' => false,
                'is_admin' => true,
                'department_ids' => [$department->id],
            ])
            ->assertRedirect();

        $user = User::findOrFail($lecturer->user_id);

        expect($user->hasRole(UserRole::Admin->value))->toBeTrue()
            ->and($user->hasRole(UserRole::Lecturer->value))->toBeTrue()
            ->and($user->departments()->pluck('departments.id')->all())->toBe([$department->id]);
    });

    it('revokes the admin role and every program studi with it', function (): void {
        $admin = makeAdmin(makeDepartment());

        $this->actingAs(makeSuperAdmin())
            ->put(route('superadmin.users.roles.update', $admin), ['is_super_admin' => false, 'is_admin' => false])
            ->assertRedirect();

        $user = User::findOrFail($admin->id);

        expect($user->hasRole(UserRole::Admin->value))->toBeFalse()
            ->and($user->departments()->count())->toBe(0);
    });

    it('requires a program studi when granting the admin role', function (): void {
        $lecturer = makeLecturer();

        $this->actingAs(makeSuperAdmin())
            ->put(route('superadmin.users.roles.update', $lecturer->user_id), [
                'is_super_admin' => false,
                'is_admin' => true,
                'department_ids' => [],
            ])
            ->assertSessionHasErrors([
                'department_ids' => 'Admin Prodi harus ditugaskan ke sedikitnya satu program studi.',
            ]);
    });

    it('does not let a superadmin revoke their own superadmin role', function (): void {
        $superadmin = makeSuperAdmin();

        $this->actingAs($superadmin)
            ->put(route('superadmin.users.roles.update', $superadmin), ['is_super_admin' => false, 'is_admin' => false])
            ->assertSessionHasErrors([
                'is_super_admin' => 'Anda tidak dapat mencabut peran superadmin dari akun sendiri.',
            ]);

        expect(User::findOrFail($superadmin->id)->hasRole(UserRole::SuperAdmin->value))->toBeTrue();
    });

    it('does not give a mahasiswa a managing role', function (): void {
        $student = makeStudent();

        $this->actingAs(makeSuperAdmin())
            ->put(route('superadmin.users.roles.update', $student->user_id), [
                'is_super_admin' => true,
                'is_admin' => false,
            ])
            ->assertSessionHasErrors(['is_admin' => 'Akun mahasiswa tidak dapat diberi peran pengelola.']);

        expect(User::findOrFail($student->user_id)->hasRole(UserRole::SuperAdmin->value))->toBeFalse();
    });
});

describe('deleting', function (): void {
    it('deletes an account once its surel is typed back', function (): void {
        $user = makeAdmin(makeDepartment());

        $this->actingAs(makeSuperAdmin())
            ->delete(route('superadmin.users.destroy', $user), ['email' => $user->email])
            ->assertRedirect(route('superadmin.users.index'));

        $this->assertModelMissing($user);
        expect(AuditLog::where('action', AuditAction::UserDeleted->value)->sole()->changes['email'])
            ->toBe($user->email);
    });

    it('keeps the account when the typed surel does not match', function (): void {
        $user = makeAdmin(makeDepartment());

        $this->actingAs(makeSuperAdmin())
            ->delete(route('superadmin.users.destroy', $user), ['email' => 'salah@kampus.test'])
            ->assertSessionHasErrors(['email' => 'Surel yang diketik tidak sama dengan surel akun ini.']);

        $this->assertModelExists($user);
    });

    it('refuses to delete a dosen other people\'s records depend on', function (): void {
        $lecturer = assignSupervisor(makeStudent());

        $this->actingAs(makeSuperAdmin())
            ->delete(route('superadmin.users.destroy', $lecturer->user_id), ['email' => $lecturer->user->email])
            ->assertForbidden();

        $this->assertModelExists($lecturer);
    });

    it('tells the superadmin on the account page why it cannot be deleted', function (): void {
        $lecturer = assignSupervisor(makeStudent());

        $this->actingAs(makeSuperAdmin())
            ->get(route('superadmin.users.show', $lecturer->user_id))
            ->assertInertia(fn ($page) => $page
                ->where('deletion.allowed', false)
                ->where('deletion.reason', fn (string $reason): bool => str_contains(
                    $reason,
                    'masih tercatat sebagai pembimbing atau penguji mahasiswa',
                )));
    });
});
