<?php

namespace App\Enums;

/**
 * Everything the audit trail records. The value is what is stored; the label
 * is what the Log Aktivitas screen reads out.
 */
enum AuditAction: string
{
    case ExamReleased = 'exam.released';
    case ExamUnlocked = 'exam.unlocked';
    case MinutesDecided = 'minutes.decided';
    case ParticipantSigned = 'participant.signed';
    case DepartmentCreated = 'department.created';
    case DepartmentUpdated = 'department.updated';
    case DepartmentDeleted = 'department.deleted';
    case DepartmentAdminAssigned = 'department.admin-assigned';
    case DepartmentAdminRemoved = 'department.admin-removed';
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserDeleted = 'user.deleted';
    case UserRolesUpdated = 'user.roles-updated';
    case UserInvited = 'user.invited';
    case RolePermissionsUpdated = 'role.permissions-updated';
    case BroadcastSent = 'broadcast.sent';
    case SystemSettingUpdated = 'system-setting.updated';

    /**
     * Human readable label, in Bahasa Indonesia, as shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::ExamReleased => 'Hasil ujian dirilis',
            self::ExamUnlocked => 'Kunci hasil ujian dibuka',
            self::MinutesDecided => 'Berita acara ditetapkan',
            self::ParticipantSigned => 'Dokumen ujian ditandatangani',
            self::DepartmentCreated => 'Program studi dibuat',
            self::DepartmentUpdated => 'Program studi diubah',
            self::DepartmentDeleted => 'Program studi dihapus',
            self::DepartmentAdminAssigned => 'Admin prodi ditugaskan',
            self::DepartmentAdminRemoved => 'Admin prodi dilepas',
            self::UserCreated => 'Akun dibuat',
            self::UserUpdated => 'Akun diubah',
            self::UserDeleted => 'Akun dihapus',
            self::UserRolesUpdated => 'Peran akun diubah',
            self::UserInvited => 'Tautan aktivasi dikirim',
            self::RolePermissionsUpdated => 'Izin peran diubah',
            self::BroadcastSent => 'Siaran dikirim',
            self::SystemSettingUpdated => 'Pengaturan sistem diubah',
        };
    }
}
