<?php

namespace App\Support;

use App\Enums\UserRole;

/**
 * Every permission the application knows, what it is called in the interface,
 * and which of them each role starts out with. The seeder plants this and the
 * Peran & Izin editor renders it, so the two cannot drift apart.
 */
final class PermissionCatalog
{
    /**
     * Permissions grouped by the area of the application they gate, each with
     * the label the role editor shows.
     *
     * @var array<string, array<string, string>>
     */
    private const GROUPS = [
        'Proposal' => [
            'proposal.view' => 'Melihat proposal',
            'proposal.create' => 'Mengajukan proposal',
            'proposal.review' => 'Meninjau proposal',
            'proposal.approve' => 'Menyetujui proposal',
        ],
        'Bimbingan' => [
            'guidance.view' => 'Melihat sesi bimbingan',
            'guidance.create' => 'Membuka sesi bimbingan',
            'guidance.participate' => 'Berdiskusi dalam sesi bimbingan',
            'guidance.complete' => 'Menyelesaikan sesi bimbingan',
            'guidance.monitor' => 'Memantau bimbingan seluruh mahasiswa',
        ],
        'Penelitian' => [
            'logbook.view' => 'Melihat logbook penelitian',
            'logbook.create' => 'Mengisi logbook penelitian',
            'logbook.monitor' => 'Memantau logbook mahasiswa',
        ],
        'Ujian' => [
            'exam.request' => 'Mengajukan ujian',
            'exam.verify' => 'Memverifikasi pengajuan ujian',
            'exam.schedule' => 'Menjadwalkan dan merilis ujian',
            'exam.evaluate' => 'Menilai ujian',
            'exam.view' => 'Melihat ujian',
            'rubric.manage' => 'Mengelola rubrik ujian',
        ],
        'Akademik' => [
            'student.view' => 'Melihat data mahasiswa',
            'student.manage' => 'Mengelola data mahasiswa',
            'lecturer.view' => 'Melihat data dosen',
            'lecturer.manage' => 'Mengelola data dosen',
            'supervisor.assign' => 'Menugaskan pembimbing dan penguji',
        ],
        'Administrasi' => [
            'department.view' => 'Melihat program studi',
            'department.manage' => 'Mengelola program studi',
            'thesis-setting.manage' => 'Mengatur aturan tugas akhir',
            'report.view' => 'Melihat laporan dan rekap',
            'data.import' => 'Mengimpor data massal',
        ],
        'Sistem' => [
            'user.view' => 'Melihat seluruh pengguna',
            'user.manage' => 'Mengelola pengguna',
            'role.manage' => 'Mengelola peran dan izin',
            'system-setting.manage' => 'Mengatur pengaturan sistem',
            'activity-log.view' => 'Melihat log aktivitas',
            'broadcast.manage' => 'Mengirim siaran',
        ],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const DEFAULTS = [
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

    /**
     * Permissions only the superadmin may ever hold. Handing any of them to
     * another role would let that role widen its own reach — an Admin Prodi
     * with `role.manage` could grant itself the rest.
     *
     * @return array<int, string>
     */
    public static function reserved(): array
    {
        return [...array_keys(self::GROUPS['Sistem']), 'department.manage'];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return self::GROUPS;
    }

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_merge(...array_map(array_keys(...), array_values(self::GROUPS)));
    }

    /**
     * What a role is granted before anyone has edited it. The superadmin holds
     * everything, always.
     *
     * @return array<int, string>
     */
    public static function defaultsFor(UserRole $role): array
    {
        return $role === UserRole::SuperAdmin ? self::all() : self::DEFAULTS[$role->value];
    }

    /**
     * The roles whose permissions the editor may change.
     *
     * @return array<int, UserRole>
     */
    public static function editableRoles(): array
    {
        return [UserRole::Student, UserRole::Lecturer, UserRole::Admin];
    }
}
