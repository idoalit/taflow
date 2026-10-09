import {
    BookOpen,
    Building2,
    CalendarDays,
    ClipboardCheck,
    FileText,
    Home,
    Megaphone,
    MessageSquare,
    Printer,
    Scale,
    ScrollText,
    Settings,
    ShieldCheck,
    SquareCheckBig,
    Upload,
    User,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { dashboard } from '@/routes';
import { index as adminExamRequests } from '@/routes/admin/exam-requests';
import { index as adminExams } from '@/routes/admin/exams';
import { index as adminImport } from '@/routes/admin/import';
import { index as adminLecturers } from '@/routes/admin/lecturers';
import { index as adminMonitoring } from '@/routes/admin/monitoring';
import { index as adminReports } from '@/routes/admin/reports';
import { index as adminRubrics } from '@/routes/admin/rubrics';
import { index as adminStudents } from '@/routes/admin/students';
import { edit as adminThesisSettings } from '@/routes/admin/thesis-settings';
import { index as guidanceCards } from '@/routes/guidance-cards';
import { index as examVerifications } from '@/routes/exam-verifications';
import { index as guidanceSessions } from '@/routes/guidance-sessions';
import { index as perceptionAlignments } from '@/routes/perception-alignments';
import { index as exams } from '@/routes/exams';
import { index as proposalExamRequests } from '@/routes/proposal-exam-requests';
import { index as proposals } from '@/routes/proposals';
import { index as researchExamRequests } from '@/routes/research-exam-requests';
import { index as researchLogbooks } from '@/routes/research-logbooks';
import { index as superadminActivityLogs } from '@/routes/superadmin/activity-logs';
import { index as superadminBroadcasts } from '@/routes/superadmin/broadcasts';
import { index as superadminDepartments } from '@/routes/superadmin/departments';
import { index as superadminRoles } from '@/routes/superadmin/roles';
import { edit as superadminSettings } from '@/routes/superadmin/settings';
import { index as superadminUsers } from '@/routes/superadmin/users';
import type { ThesisSettings, UserRole } from '@/types';

export type IndustryNavItem = {
    label: string;
    href: string;
    icon: LucideIcon;
    /**
     * The permission the destination itself checks. An item whose permission
     * the role editor has taken away is dropped rather than left to refuse.
     */
    permission?: string;
};

export type IndustryNavGroup = {
    label: string;
    items: IndustryNavItem[];
};

const PLATFORM: IndustryNavGroup = {
    label: 'Platform',
    items: [{ label: 'Dashboard', href: dashboard.url(), icon: Home }],
};

const STUDENT_NAV: IndustryNavGroup[] = [
    PLATFORM,
    {
        label: 'Proposal',
        items: [
            {
                label: 'Daftar Proposal',
                href: proposals.url(),
                icon: FileText,
                permission: 'proposal.view',
            },
            {
                label: 'Pengajuan Ujian',
                href: proposalExamRequests.url(),
                icon: SquareCheckBig,
                permission: 'exam.view',
            },
        ],
    },
    {
        label: 'Pembimbingan',
        items: [
            {
                label: 'Sesi Bimbingan',
                href: guidanceSessions.url(),
                icon: MessageSquare,
                permission: 'guidance.view',
            },
            {
                label: 'Cetak Kartu Bimbingan',
                href: guidanceCards.url(),
                icon: Printer,
            },
        ],
    },
    {
        label: 'Penelitian',
        items: [
            {
                label: 'Logbook Penelitian',
                href: researchLogbooks.url(),
                icon: BookOpen,
                permission: 'logbook.view',
            },
            {
                label: 'Ujian Hasil Penelitian',
                href: researchExamRequests.url(),
                icon: ClipboardCheck,
                permission: 'exam.view',
            },
        ],
    },
];

const LECTURER_NAV: IndustryNavGroup[] = [
    PLATFORM,
    {
        label: 'Proposal',
        items: [
            {
                label: 'Tinjauan Proposal',
                href: proposals.url(),
                icon: FileText,
                permission: 'proposal.view',
            },
            {
                label: 'Persamaan Persepsi',
                href: perceptionAlignments.url(),
                icon: Scale,
                permission: 'proposal.review',
            },
        ],
    },
    {
        label: 'Pembimbingan',
        items: [
            {
                label: 'Sesi Bimbingan',
                href: guidanceSessions.url(),
                icon: MessageSquare,
                permission: 'guidance.view',
            },
            {
                label: 'Cetak Kartu Bimbingan',
                href: guidanceCards.url(),
                icon: Printer,
            },
        ],
    },
    {
        label: 'Ujian',
        items: [
            {
                label: 'Verifikasi Pengajuan Ujian',
                href: examVerifications.url(),
                icon: SquareCheckBig,
                permission: 'exam.view',
            },
            {
                label: 'Pelaksanaan Ujian',
                href: exams.url(),
                icon: ClipboardCheck,
                permission: 'exam.view',
            },
        ],
    },
];

/**
 * Admin Prodi. Every destination is scoped to the program studi named by the
 * active workspace, so an administrator of two prodi uses the same menu twice.
 */
const ADMIN_NAV: IndustryNavGroup[] = [
    PLATFORM,
    {
        label: 'Data',
        items: [
            {
                label: 'Mahasiswa',
                href: adminStudents.url(),
                icon: Users,
                permission: 'student.view',
            },
            {
                label: 'Dosen',
                href: adminLecturers.url(),
                icon: User,
                permission: 'lecturer.view',
            },
            {
                label: 'Import Data',
                href: adminImport.url(),
                icon: Upload,
                permission: 'data.import',
            },
            {
                label: 'Pengaturan Tugas Akhir',
                href: adminThesisSettings.url(),
                icon: Settings,
                permission: 'thesis-setting.manage',
            },
        ],
    },
    {
        label: 'Bimbingan & Ujian',
        items: [
            {
                label: 'Monitoring Bimbingan',
                href: adminMonitoring.url(),
                icon: FileText,
                permission: 'guidance.monitor',
            },
            {
                label: 'Pengajuan Ujian',
                href: adminExamRequests.url(),
                icon: SquareCheckBig,
                permission: 'exam.view',
            },
            {
                label: 'Pelaksanaan Ujian',
                href: adminExams.url(),
                icon: CalendarDays,
                permission: 'exam.schedule',
            },
            {
                label: 'Rubrik Ujian',
                href: adminRubrics.url(),
                icon: BookOpen,
                permission: 'rubric.manage',
            },
        ],
    },
    {
        label: 'Laporan',
        items: [
            {
                label: 'Laporan & Rekap',
                href: adminReports.url(),
                icon: ClipboardCheck,
                permission: 'report.view',
            },
        ],
    },
];

/**
 * Superadmin. Nothing here belongs to one program studi: these are the screens
 * that create the prodi, the people who run them, and the rules they all share.
 */
const SUPERADMIN_NAV: IndustryNavGroup[] = [
    PLATFORM,
    {
        label: 'Kelola',
        items: [
            {
                label: 'Program Studi',
                href: superadminDepartments.url(),
                icon: Building2,
                permission: 'department.manage',
            },
            {
                label: 'Pengguna',
                href: superadminUsers.url(),
                icon: Users,
                permission: 'user.view',
            },
            {
                label: 'Peran & Izin',
                href: superadminRoles.url(),
                icon: ShieldCheck,
                permission: 'role.manage',
            },
        ],
    },
    {
        label: 'Sistem',
        items: [
            {
                label: 'Siaran',
                href: superadminBroadcasts.url(),
                icon: Megaphone,
                permission: 'broadcast.manage',
            },
            {
                label: 'Log Aktivitas',
                href: superadminActivityLogs.url(),
                icon: ScrollText,
                permission: 'activity-log.view',
            },
            {
                label: 'Pengaturan Sistem',
                href: superadminSettings.url(),
                icon: Settings,
                permission: 'system-setting.manage',
            },
        ],
    },
];

const NAV_BY_ROLE: Record<UserRole, IndustryNavGroup[]> = {
    student: STUDENT_NAV,
    lecturer: LECTURER_NAV,
    admin: ADMIN_NAV,
    'super-admin': SUPERADMIN_NAV,
};

export function navigationFor(
    role: UserRole | null,
    thesis: ThesisSettings | null = null,
    permissions: string[] = [],
): IndustryNavGroup[] {
    const groups = role === null ? [PLATFORM] : NAV_BY_ROLE[role];

    // Two things take an item out of the menu: a permission the role no longer
    // holds, and — where the program studi runs no ujian proposal — the
    // shortcut that would only lead to a refusal. A group left empty goes too.
    return groups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) =>
                    (item.permission === undefined ||
                        permissions.includes(item.permission)) &&
                    (thesis?.requiresProposalExam !== false ||
                        item.href !== proposalExamRequests.url()),
            ),
        }))
        .filter((group) => group.items.length > 0);
}

const TAGLINES: Partial<Record<UserRole, string>> = {
    admin: 'Admin Program Studi',
    'super-admin': 'Superadmin',
};

/** The tagline under the wordmark changes per role, as in the design. */
export function brandTagline(role: UserRole | null): string {
    return (role && TAGLINES[role]) ?? 'Bimbingan Tugas Akhir';
}
