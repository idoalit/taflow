import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    Avatar,
    Card,
    CardKicker,
    DataTable,
    EmptyRow,
    Tag,
} from '@/components/industry/ui';
import { index } from '@/routes/admin/monitoring';
import type { AdminStudentDetail, GuidanceSessionSummary } from '@/types';

type Props = {
    student: AdminStudentDetail;
    sessions: GuidanceSessionSummary[];
    logbookCount: number;
};

export default function AdminMonitoringShow({
    student,
    sessions,
    logbookCount,
}: Props) {
    setLayoutProps({ title: 'Detail Monitoring' });

    return (
        <>
            <Head title={`Monitoring — ${student.name}`} />

            <div className="ind-page max-w-[860px]">
                <div>
                    <Link href={index()} className="text-[12.5px]">
                        ← Monitoring Bimbingan
                    </Link>
                    <div className="mt-[6px] flex items-center gap-[10px]">
                        <Avatar initials={student.initials} size={34} />
                        <div>
                            <h3 className="m-0">{student.name}</h3>
                            <span className="ind-muted text-[13px]">
                                {student.student_number} · {student.department}
                            </span>
                        </div>
                        <Tag label={student.stage_label} tone="outline" />
                    </div>
                </div>

                <div className="grid [grid-template-columns:repeat(auto-fit,minmax(190px,1fr))] gap-[var(--ind-space-3)]">
                    <Card>
                        <CardKicker>Sesi Bimbingan</CardKicker>
                        <div className="ind-stat ind-stat-sm">
                            {student.sessions_completed}/
                            {student.sessions_required}
                        </div>
                    </Card>
                    <Card>
                        <CardKicker>Catatan Logbook</CardKicker>
                        <div className="ind-stat ind-stat-sm">
                            {logbookCount}
                        </div>
                    </Card>
                    <Card>
                        <CardKicker>Status Proposal</CardKicker>
                        <div className="ind-card-title">
                            {student.proposal_status_label}
                        </div>
                    </Card>
                </div>

                <Card>
                    <CardKicker>Dosen</CardKicker>
                    <div className="flex flex-col gap-[var(--ind-space-2)]">
                        {student.assignments.length === 0 ? (
                            <span className="ind-muted text-[13px]">
                                Belum ada pembimbing maupun penguji.
                            </span>
                        ) : (
                            student.assignments.map((assignment) => (
                                <div
                                    key={`${assignment.lecturer_id}-${assignment.role}`}
                                    className="flex items-center gap-[10px]"
                                >
                                    <span className="text-[13px] font-semibold">
                                        {assignment.name}
                                    </span>
                                    <Tag
                                        label={assignment.role_label}
                                        tone="neutral"
                                    />
                                </div>
                            ))
                        )}
                    </div>
                </Card>

                <div className="ind-section">
                    <h6>Riwayat Sesi</h6>
                    <DataTable
                        head={
                            <tr>
                                <th>Judul</th>
                                <th>Tahap</th>
                                <th>Jadwal</th>
                                <th>Status</th>
                            </tr>
                        }
                    >
                        {sessions.length === 0 ? (
                            <EmptyRow colSpan={4}>
                                Mahasiswa ini belum memiliki sesi bimbingan.
                            </EmptyRow>
                        ) : (
                            sessions.map((session) => (
                                <tr key={session.id}>
                                    <td className="font-semibold">
                                        {session.title}
                                    </td>
                                    <td className="ind-muted">
                                        {session.stage_label}
                                    </td>
                                    <td className="ind-muted">
                                        {session.meta}
                                    </td>
                                    <td>
                                        <Tag label={session.status_label} />
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
