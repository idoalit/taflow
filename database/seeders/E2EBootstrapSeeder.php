<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The smallest database the application can be driven from: the roles and
 * permissions it cannot run without, and the one superadmin who has to exist
 * before anybody can sign in.
 *
 * Everything else — the program studi, its Admin Prodi, dosen, mahasiswa,
 * pengaturan, rubrik, proposal, bimbingan, ujian — is created through the
 * interface by the browser tests, so that the chain they walk is the real one
 * and not something a seeder arranged for them.
 */
class E2EBootstrapSeeder extends Seeder
{
    public const SUPERADMIN_EMAIL = 'superadmin.e2e@kampus.test';

    public const SUPERADMIN_PASSWORD = 'password';

    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $superadmin = User::firstOrCreate(
            ['email' => self::SUPERADMIN_EMAIL],
            [
                'name' => 'Superadmin E2E',
                'password' => self::SUPERADMIN_PASSWORD,
                'email_verified_at' => now(),
            ],
        );

        $superadmin->syncRoles([UserRole::SuperAdmin->value]);
    }
}
