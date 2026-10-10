<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * The way into a database nobody has signed in to yet. Every other account is
 * made from a screen, and every screen needs somebody already signed in — so
 * the first superadmin has to come from the command line.
 *
 * It hands out a link rather than a password: the account's owner sets their
 * own, exactly as every other account does, and it works before any mail
 * server has been configured.
 */
#[Signature('fipro:superadmin {email : Surel akun superadmin} {--name=Superadmin : Nama yang ditampilkan}')]
#[Description('Buat akun superadmin dan cetak tautan untuk menyetel kata sandinya')]
class CreateSuperAdmin extends Command
{
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->fails()) {
            $this->components->error("\"{$email}\" bukan alamat surel yang sah.");

            return self::FAILURE;
        }

        if (! Role::query()->where('name', UserRole::SuperAdmin->value)->exists()) {
            $this->components->error(
                'Peran superadmin belum ada. Jalankan dulu: php artisan db:seed --class=RolePermissionSeeder --force',
            );

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        // An account that is something else is not quietly promoted from here:
        // that is a decision for a superadmin to make on the Pengguna screen.
        if ($existing !== null && ! $existing->isSuperAdmin()) {
            $this->components->error(
                "Surel {$email} sudah dipakai akun lain yang bukan superadmin. "
                .'Ubah perannya dari layar Pengguna, atau pakai surel lain.',
            );

            return self::FAILURE;
        }

        $user = $existing ?? DB::transaction(function () use ($email): User {
            $user = User::create([
                'name' => (string) $this->option('name'),
                'email' => $email,
                'password' => Str::password(40),
            ]);

            $user->assignRole(UserRole::SuperAdmin->value);

            return $user;
        });

        $link = route('password.reset', [
            'token' => Password::broker()->createToken($user),
            'email' => $user->email,
        ]);

        $this->components->info($existing === null
            ? "Akun superadmin {$email} dibuat."
            : "Akun superadmin {$email} sudah ada; tautan baru dibuat untuknya.");

        $this->line('Buka tautan ini untuk menyetel kata sandi (berlaku sekali, '
            .config('auth.passwords.users.expire').' menit):');
        $this->newLine();
        $this->line($link);

        return self::SUCCESS;
    }
}
