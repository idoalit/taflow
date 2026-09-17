import type { Greeting } from '@/types';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ClipboardCheck, FileText, Scale, SquareCheckBig } from 'lucide-react';
import {
    Card,
    CardKicker,
    DataTable,
    EmptyRow,
    Tag,
} from '@/components/industry/ui';
import { index as examVerifications } from '@/routes/exam-verifications';
import { index as perceptionAlignments } from '@/routes/perception-alignments';
import { index as exams } from '@/routes/exams';
import { index as proposals, show as showProposal } from '@/routes/proposals';

type StudentRow = {
    id: number;
    name: string;
    student_number: string;
    title: string | null;
    proposal_id: number | null;
    my_role: string;
    status_label: string;
    sessions_completed: number;
    sessions_required: number;
};

type Props = {
    greeting: Greeting;
    kpis: { label: string; value: number }[];
    students: StudentRow[];
};

const QUICK_ACTIONS = [
    { label: 'Tinjau Proposal', href: proposals.url(), Icon: FileText },
    {
        label: 'Persamaan Persepsi',
        href: perceptionAlignments.url(),
        Icon: Scale,
    },
    {
        label: 'Verifikasi Ujian',
        href: examVerifications.url(),
        Icon: SquareCheckBig,
    },
    {
        label: 'Isi Evaluasi Ujian',
        href: exams.url(),
        Icon: ClipboardCheck,
    },
];

export default function LecturerDashboard({ greeting, kpis, students }: Props) {
    setLayoutProps({ title: 'Dashboard Dosen' });

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-[var(--ind-space-6)]">
                <div>
                    <h2 className="mb-[6px]">
                        {greeting.salutation}, {greeting.name}
                    </h2>
                    <p className="ind-muted m-0 text-[15px]">
                        Ringkasan mahasiswa bimbingan dan tindakan yang menunggu
                        Anda.
                    </p>
                </div>

                <div className="grid [grid-template-columns:repeat(auto-fit,minmax(200px,1fr))] gap-[var(--ind-space-4)]">
                    {kpis.map((kpi) => (
                        <Card key={kpi.label}>
                            <CardKicker>{kpi.label}</CardKicker>
                            <div className="ind-stat">{kpi.value}</div>
                        </Card>
                    ))}
                </div>

                <div className="grid [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))] gap-[var(--ind-space-4)]">
                    {QUICK_ACTIONS.map(({ label, href, Icon }) => (
                        <Link
                            key={label}
                            href={href}
                            className="ind-card ind-bp flex-row items-center"
                        >
                            <i className="ind-corner tl" />
                            <i className="ind-corner tr" />
                            <i className="ind-corner bl" />
                            <i className="ind-corner br" />
                            <Icon
                                size={18}
                                strokeWidth={1.5}
                                style={{ color: 'var(--ind-accent-700)' }}
                            />
                            <span
                                className="text-[14px] font-semibold"
                                style={{
                                    fontFamily: 'var(--ind-font-heading)',
                                }}
                            >
                                {label}
                            </span>
                        </Link>
                    ))}
                </div>

                <div>
                    <h4 className="mb-[var(--ind-space-3)]">
                        Mahasiswa Bimbingan
                    </h4>

                    <DataTable
                        head={
                            <tr>
                                <th>Mahasiswa</th>
                                <th>Judul Proposal</th>
                                <th>Peran Saya</th>
                                <th>Status Proposal</th>
                                <th>Sesi Selesai</th>
                            </tr>
                        }
                    >
                        {students.length === 0 ? (
                            <EmptyRow colSpan={5}>
                                Belum ada mahasiswa bimbingan.
                            </EmptyRow>
                        ) : (
                            students.map((student) => (
                                <tr key={student.id}>
                                    <td className="font-semibold">
                                        {student.name}
                                        <span className="ind-muted block text-[12px]">
                                            {student.student_number}
                                        </span>
                                    </td>
                                    <td>
                                        {student.proposal_id ? (
                                            <Link
                                                href={showProposal(
                                                    student.proposal_id,
                                                )}
                                            >
                                                {student.title}
                                            </Link>
                                        ) : (
                                            <span className="ind-muted">—</span>
                                        )}
                                    </td>
                                    <td>
                                        <Tag
                                            label={student.my_role}
                                            tone="outline"
                                        />
                                    </td>
                                    <td>
                                        <Tag label={student.status_label} />
                                    </td>
                                    <td className="ind-muted">
                                        {student.sessions_completed} /{' '}
                                        {student.sessions_required}
                                    </td>
                                </tr>
                            ))
                        )}
                    </DataTable>
                </div>
            </div>
        </>
    );
}
