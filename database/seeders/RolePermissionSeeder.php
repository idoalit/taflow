<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Permissions grouped by the area of the application they gate. The
     * Superadmin role editor renders these groups directly.
     *
     * @var array<string, array<int, string>>
     */
    private const PERMISSION_GROUPS = [
        'Proposal' => [
            'proposal.view', 'proposal.create', 'proposal.review', 'proposal.approve',
        ],
        'Bimbingan' => [
            'guidance.view', 'guidance.create', 'guidance.participate', 'guidance.complete', 'guidance.monitor',
        ],
        'Penelitian' => [
            'logbook.view', 'logbook.create', 'logbook.monitor',
        ],
        'Ujian' => [
            'exam.request', 'exam.verify', 'exam.schedule', 'exam.evaluate', 'exam.view', 'rubric.manage',
        ],
        'Akademik' => [
            'student.view', 'student.manage', 'lecturer.view', 'lecturer.manage', 'supervisor.assign',
        ],
        'Administrasi' => [
            'department.view', 'department.manage', 'thesis-setting.manage', 'report.view', 'data.import',
        ],
        'Sistem' => [
            'user.view', 'user.manage', 'role.manage', 'system-setting.manage', 'activity-log.view', 'broadcast.manage',
        ],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const ROLE_PERMISSIONS = [
        'student' => [
            'proposal.view', 'proposal.create',
            'guidance.view', 'guidance.create', 'guidance.participate',
            'logbook.view', 'logbook.create',
            'exam.request', 'exam.view',
        ],
        'lecturer' => [
            'proposal.view', 'proposal.review', 'proposal.approve',
            'guidance.view', 'guidance.participate', 'guidance.complete',
            'logbook.view', 'logbook.monitor',
            'exam.verify', 'exam.evaluate', 'exam.view',
            'student.view',
        ],
        'admin' => [
            'proposal.view',
            'guidance.view', 'guidance.monitor',
            'logbook.view', 'logbook.monitor',
            'exam.verify', 'exam.schedule', 'exam.view', 'rubric.manage',
            'student.view', 'student.manage', 'lecturer.view', 'lecturer.manage', 'supervisor.assign',
            'department.view', 'thesis-setting.manage', 'report.view', 'data.import',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSION_GROUPS as $permissions) {
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }
        }

        // The registrar caches the permission table; roles are synced by name
        // below, so the freshly created rows have to be visible first.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            $model = Role::findOrCreate($role->value, 'web');

            $model->syncPermissions(
                $role === UserRole::SuperAdmin
                    ? Permission::all()
                    : self::ROLE_PERMISSIONS[$role->value]
            );
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function permissionGroups(): array
    {
        return self::PERMISSION_GROUPS;
    }
}
