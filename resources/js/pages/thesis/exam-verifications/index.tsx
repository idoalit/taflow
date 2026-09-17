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
import { index as verifications } from '@/routes/exam-verifications';
import type { ExamStage } from '@/types';

type VerificationRow = {
    id: number;
    student: string;
    student_number: string;
    title: string;
    supervisor_status_label: string;
    submitted_at: string;
    show_url: string;
};

export default function ExamVerificationIndex({
    requests,
    stage,
    stages,
}: {
    requests: VerificationRow[];
    stage: ExamStage;
    stages: { value: ExamStage; label: string; count: number }[];
}) {
    setLayoutProps({ title: 'Verifikasi Pengajuan Ujian' });

    const [search, setSearch] = useState('');
    const term = search.trim().toLowerCase();
    const visible = requests.filter(
        (row) => term === '' || row.student.toLowerCase().includes(term),
    );

    return (
        <>
            <Head title="Verifikasi Pengajuan Ujian" />

            <div className="flex flex-col gap-[var(--ind-space-4)]">
                <PageHeader
                    title="Verifikasi Pengajuan Ujian"
                    description="Sebagai pembimbing, persetujuan Anda diperlukan sebelum Admin Prodi menjadwalkan ujian."
                />

                <Tabs
                    label="Tahap ujian"
                    value={stage}
                    options={stages}
                    onChange={(next) =>
                        router.get(
                            verifications.url({ query: { stage: next } }),
                            {},
                            { preserveState: true, preserveScroll: true },
                        )
                    }
                />

                <Input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Cari nama mahasiswa..."
                    className="max-w-[320px]"
                />

                <DataTable
                    head={
                        <tr>
                            <th>Mahasiswa</th>
                            <th>Judul</th>
                            <th>Status</th>
                            <th>Diajukan</th>
                        </tr>
                    }
                >
                    {visible.length === 0 ? (
                        <EmptyRow colSpan={4}>
                            Belum ada pengajuan ujian yang masuk.
                        </EmptyRow>
                    ) : (
                        visible.map((row) => (
                            <tr
                                key={row.id}
                                className="cursor-pointer"
                                onClick={() => router.visit(row.show_url)}
                            >
                                <td className="font-semibold">
                                    {row.student}
                                    <span className="ind-muted block text-[12px]">
                                        {row.student_number}
                                    </span>
                                </td>
                                <td>{row.title}</td>
                                <td>
                                    <Tag label={row.supervisor_status_label} />
                                </td>
                                <td className="ind-muted">
                                    {row.submitted_at}
                                </td>
                            </tr>
                        ))
                    )}
                </DataTable>
            </div>
        </>
    );
}
