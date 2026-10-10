<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Password;

it('creates the first superadmin and prints a link that sets its password', function (): void {
    seedRoles();

    $exit = Artisan::call('fipro:superadmin', ['email' => 'Kepala.TI@Kampus.ac.id', '--name' => 'Kepala TI']);
    $output = Artisan::output();

    $user = User::where('email', 'kepala.ti@kampus.ac.id')->sole();

    preg_match('#reset-password/([^?\s]+)#', $output, $matches);

    expect($exit)->toBe(0)
        ->and($user->name)->toBe('Kepala TI')
        ->and($user->hasRole(UserRole::SuperAdmin->value))->toBeTrue()
        ->and($output)->toContain('email=kepala.ti%40kampus.ac.id')
        ->and(Password::broker()->tokenExists($user, $matches[1]))->toBeTrue();
});

it('gives an existing superadmin a fresh link instead of a second account', function (): void {
    seedRoles();
    $superadmin = makeSuperAdmin();

    $this->artisan('fipro:superadmin', ['email' => $superadmin->email])
        ->expectsOutputToContain('sudah ada; tautan baru dibuat')
        ->assertSuccessful();

    expect(User::count())->toBe(1);
});

it('does not promote an account that is not a superadmin', function (): void {
    seedRoles();
    $student = makeStudent();

    $this->artisan('fipro:superadmin', ['email' => $student->user->email])
        ->expectsOutputToContain('sudah dipakai akun lain yang bukan superadmin')
        ->assertFailed();

    expect($student->user->refresh()->hasRole(UserRole::SuperAdmin->value))->toBeFalse();
});

it('rejects something that is not a surel', function (): void {
    seedRoles();

    $this->artisan('fipro:superadmin', ['email' => 'bukan-surel'])
        ->expectsOutputToContain('bukan alamat surel yang sah')
        ->assertFailed();

    expect(User::count())->toBe(0);
});

it('says what to run when the roles have not been seeded yet', function (): void {
    $this->artisan('fipro:superadmin', ['email' => 'admin@kampus.ac.id'])
        ->expectsOutputToContain('db:seed --class=RolePermissionSeeder')
        ->assertFailed();

    expect(User::count())->toBe(0);
});
