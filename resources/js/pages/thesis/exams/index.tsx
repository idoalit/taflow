import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import {
    DataTable,
    EmptyRow,
    Input,
    PageHeader,
    Tabs,
    Tag,
} from '@/components/industry/ui';
import { index as exams } from '@/routes/exams';
import type { ExamStage, ThesisExamSummary } from '@/types';

export default function ExamIndex({
    exams: rows,
    stage,
    stages,
}: {
    exams: ThesisExamSummary[];
    stage: ExamStage;
    stages: { value: ExamStage; label: string; count: number }[];
}) {
    setLayoutProps({ title: 'Pelaksanaan Ujian' });

    const [search, setSearch] = useState('');
    const term = search.trim().toLowerCase();
    const visible = rows.filter(
        (exam) =>
            term === '' ||
            exam.title.toLowerCase().includes(term) ||
            exam.student.toLowerCase().includes(term),
    );

    const active = stages.find((option) => option.value === stage);

    return (
        <>
            <Head title="Pelaksanaan Ujian" />

            <div className="flex flex-col gap-[var(--ind-space-4)]">
                <PageHeader
                    title="Pelaksanaan Ujian"
                    description="Ujian yang melibatkan Anda sebagai pembimbing atau anggota panel penguji."
                />

                <Tabs
                    label="Tahap ujian"
                    value={stage}
                    options={stages}
                    onChange={(next) =>
                        router.get(
                            exams.url({ query: { stage: next } }),
                            {},
                            { preserveState: true, preserveScroll: true },
                        )
                    }
                />

                <Input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Cari judul atau nama mahasiswa..."
                    className="max-w-[320px]"
                />

                <DataTable
                    head={
                        <tr>
                            <th>Mahasiswa</th>
                            <th>Judul</th>
                            <th>Tanggal</th>
                            <th>Status Ujian</th>
                            <th>Evaluasi Anda</th>
                        </tr>
                    }
                >
                    {visible.length === 0 ? (
                        <EmptyRow colSpan={5}>
                            Belum ada {active?.label.toLowerCase() ?? 'ujian'}{' '}
                            yang dijadwalkan untuk mahasiswa bimbingan Anda.
                        </EmptyRow>
                    ) : (
                        visible.map((exam) => (
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
                                    {exam.scheduled_at_short}
                                </td>
                                <td>
                                    <Tag label={exam.status_label} />
                                </td>
                                <td className="ind-muted">
                                    {exam.my_evaluation}
                                </td>
                            </tr>
                        ))
                    )}
                </DataTable>
            </div>
        </>
    );
}
