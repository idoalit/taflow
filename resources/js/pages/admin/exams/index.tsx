import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import {
    Card,
    CardKicker,
    DataTable,
    EmptyRow,
    PageHeader,
    Tabs,
    Tag,
} from '@/components/industry/ui';
import { index as adminExams } from '@/routes/admin/exams';
import type { ExamStage, ThesisExamSummary } from '@/types';

type Waiting = {
    id: number;
    student: string;
    student_number: string;
    title: string;
    approved_at: string;
    schedule_url: string;
};

type Props = {
    department: string;
    stage: ExamStage;
    stages: { value: ExamStage; label: string; count: number }[];
    exams: ThesisExamSummary[];
    waiting: Waiting[];
};

export default function AdminExamIndex({
    department,
    stage,
    stages,
    exams,
    waiting,
}: Props) {
    setLayoutProps({ title: 'Pelaksanaan Ujian' });

    return (
        <>
            <Head title="Pelaksanaan Ujian" />

            <div className="ind-page">
                <PageHeader
                    title="Pelaksanaan Ujian"
                    description={`Jadwal ujian di ${department} dan pengajuan yang menunggu slot.`}
                />

                <Tabs
                    label="Tahap ujian"
                    value={stage}
                    options={stages}
                    onChange={(next) =>
                        router.get(
                            adminExams.url({ query: { stage: next } }),
                            {},
                            { preserveState: true, preserveScroll: true },
                        )
                    }
                />

                <div className="ind-section">
                    <h6>Siap Dijadwalkan ({waiting.length})</h6>
                    {waiting.length === 0 ? (
                        <p className="ind-muted m-0 text-[13px]">
                            Tidak ada pengajuan yang menunggu penjadwalan.
                        </p>
                    ) : (
                        <div className="grid [grid-template-columns:repeat(auto-fit,minmax(260px,1fr))] gap-[var(--ind-space-3)]">
                            {waiting.map((row) => (
                                <Card key={row.id}>
                                    <CardKicker>
                                        Disetujui {row.approved_at}
                                    </CardKicker>
                                    <div className="ind-card-title">
                                        {row.student}
                                    </div>
                                    <div className="ind-card-meta">
                                        {row.student_number} · {row.title}
                                    </div>
                                    <Link
                                        href={row.schedule_url}
                                        className="mt-[var(--ind-space-2)] self-start text-[13px] font-semibold"
                                    >
                                        Buat Jadwal →
                                    </Link>
                                </Card>
                            ))}
                        </div>
                    )}
                </div>

                <div className="ind-section">
                    <h6>Jadwal Ujian</h6>
                    <DataTable
                        head={
                            <tr>
                                <th>Mahasiswa</th>
                                <th>Judul</th>
                                <th>Jadwal</th>
                                <th>Tempat</th>
                                <th>Status</th>
                            </tr>
                        }
                    >
                        {exams.length === 0 ? (
                            <EmptyRow colSpan={5}>
                                Belum ada ujian yang dijadwalkan pada tahap ini.
                            </EmptyRow>
                        ) : (
                            exams.map((exam) => (
                                <tr
                                    key={`${exam.stage}-${exam.id}`}
                                    className="cursor-pointer"
                                    onClick={() => router.visit(exam.show_url)}
                                >
                                    <td className="font-semibold">
                                        {exam.student}
                                        <span className="ind-muted block text-[12px]">
                                            {exam.student_number}
                                        </span>
                                    </td>
                                    <td>{exam.title}</td>
                                    <td className="ind-muted">
                                        {exam.scheduled_at}
                                    </td>
                                    <td className="ind-muted">
                                        {exam.venue ?? '—'}
                                    </td>
                                    <td>
                                        <Tag label={exam.status_label} />
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
