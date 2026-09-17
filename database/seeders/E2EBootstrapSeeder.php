<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The smallest database the application can be driven from: the roles and
 * permissions it cannot run without, one program studi, and the one Admin Prodi
 * account that has to exist before anybody can sign in.
 *
 * Everything else — dosen, mahasiswa, pengaturan, rubrik, proposal, bimbingan,
 * ujian — is created through the interface by the browser tests, so that the
 * chain they walk is the real one and not something a seeder arranged for them.
 */
class E2EBootstrapSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin.e2e@fipro.test';

    public const ADMIN_PASSWORD = 'password';

    public const DEPARTMENT_CODE = 'TI';

    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $department = Department::firstOrCreate(
            ['code' => self::DEPARTMENT_CODE],
            [
                'name' => 'Teknik Informatika',
                'description' => 'Program Studi Teknik Informatika',
                'is_active' => true,
            ],
        );

        $admin = User::firstOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'name' => 'Admin Prodi E2E',
                'password' => self::ADMIN_PASSWORD,
                'email_verified_at' => now(),
            ],
        );

        $admin->syncRoles([UserRole::Admin->value]);
        $admin->departments()->syncWithoutDetaching([$department->id]);

        // The Form Penilaian Proses Bimbingan is faculty-standard master data,
        // and no screen creates it: without these indicators a pembimbing meets
        // a 403 and the final grade can never be composed.
        app(SupervisionIndicatorSeeder::class)->run($department);
    }
}
