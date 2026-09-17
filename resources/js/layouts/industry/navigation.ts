import {
    BookOpen,
    CalendarDays,
    CalendarCheck,
    ClipboardCheck,
    FileText,
    Home,
    MessageSquare,
    Printer,
    Scale,
    Settings,
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
import type { ThesisSettings, UserRole } from '@/types';

export type IndustryNavItem = {
    label: string;
    href: string;
    icon: LucideIcon;
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
            { label: 'Daftar Proposal', href: proposals.url(), icon: FileText },
            {
                label: 'Pengajuan Ujian',
                href: proposalExamRequests.url(),
                icon: SquareCheckBig,
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
            },
            {
                label: 'Ujian Hasil Penelitian',
                href: researchExamRequests.url(),
                icon: ClipboardCheck,
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
            },
            {
                label: 'Persamaan Persepsi',
                href: perceptionAlignments.url(),
                icon: Scale,
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
            },
            {
                label: 'Pelaksanaan Ujian',
                href: exams.url(),
                icon: ClipboardCheck,
            },
        ],
    },
];

/**
 * Navigation for the roles whose dedicated screens are not built yet. They
 * still reach the shared resources, which already scope themselves by role.
 */
const SHARED_NAV: IndustryNavGroup[] = [
    PLATFORM,
    {
        label: 'Bimbingan',
        items: [
            { label: 'Proposal', href: proposals.url(), icon: FileText },
            {
                label: 'Sesi Bimbingan',
                href: guidanceSessions.url(),
                icon: MessageSquare,
            },
            {
                label: 'Logbook Penelitian',
                href: researchLogbooks.url(),
                icon: BookOpen,
            },
        ],
    },
    {
        label: 'Ujian',
        items: [
            {
                label: 'Pengajuan Ujian Proposal',
                href: proposalExamRequests.url(),
                icon: SquareCheckBig,
            },
            {
                label: 'Ujian Hasil Penelitian',
                href: researchExamRequests.url(),
                icon: CalendarCheck,
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
            { label: 'Mahasiswa', href: adminStudents.url(), icon: Users },
            { label: 'Dosen', href: adminLecturers.url(), icon: User },
            { label: 'Import Data', href: adminImport.url(), icon: Upload },
            {
                label: 'Pengaturan Tugas Akhir',
                href: adminThesisSettings.url(),
                icon: Settings,
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
            },
            {
                label: 'Pengajuan Ujian',
                href: adminExamRequests.url(),
                icon: SquareCheckBig,
            },
            {
                label: 'Pelaksanaan Ujian',
                href: adminExams.url(),
                icon: CalendarDays,
            },
            { label: 'Rubrik Ujian', href: adminRubrics.url(), icon: BookOpen },
        ],
    },
    {
        label: 'Laporan',
        items: [
            {
                label: 'Laporan & Rekap',
                href: adminReports.url(),
                icon: ClipboardCheck,
            },
        ],
    },
];

const NAV_BY_ROLE: Record<UserRole, IndustryNavGroup[]> = {
    student: STUDENT_NAV,
    lecturer: LECTURER_NAV,
    admin: ADMIN_NAV,
    'super-admin': SHARED_NAV,
};

export function navigationFor(
    role: UserRole | null,
    thesis: ThesisSettings | null = null,
): IndustryNavGroup[] {
    const groups = role === null ? [PLATFORM] : NAV_BY_ROLE[role];

    if (thesis?.requiresProposalExam !== false) {
        return groups;
    }

    // The program studi runs no ujian proposal, so the shortcut would only lead
    // to a refusal. Drop it, and any group it leaves empty.
    return groups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) => item.href !== proposalExamRequests.url(),
            ),
        }))
        .filter((group) => group.items.length > 0);
}

/** The tagline under the wordmark changes per role, as in the design. */
export function brandTagline(role: UserRole | null): string {
    return role === 'admin' ? 'Admin Program Studi' : 'Bimbingan Tugas Akhir';
}
