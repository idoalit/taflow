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
import { index as examRequests } from '@/routes/admin/exam-requests';
import type { AdminExamRequestRow, ExamStage } from '@/types';

type Props = {
    department: string;
    stage: ExamStage;
    stages: { value: ExamStage; label: string; count: number }[];
    requests: AdminExamRequestRow[];
};

export default function AdminExamRequestIndex({
    department,
    stage,
    stages,
    requests,
}: Props) {
    setLayoutProps({ title: 'Pengajuan Ujian' });

    const [search, setSearch] = useState('');
    const term = search.trim().toLowerCase();
    const visible = requests.filter(
        (row) =>
            term === '' ||
            row.student.toLowerCase().includes(term) ||
            row.student_number.includes(term),
    );

    return (
        <>
            <Head title="Pengajuan Ujian" />

            <div className="ind-page">
                <PageHeader
                    title="Pengajuan Ujian"
                    description={`Gerbang verifikasi program studi ${department}. Persetujuan di sini membuka penjadwalan.`}
                />

                <Tabs
                    label="Tahap ujian"
                    value={stage}
                    options={stages}
                    onChange={(next) =>
                        router.get(
                            examRequests.url({ query: { stage: next } }),
                            {},
                            { preserveState: true, preserveScroll: true },
                        )
                    }
                />

                <Input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Cari nama atau NIM..."
                    className="max-w-[300px]"
                />

                <DataTable
                    head={
                        <tr>
                            <th>Mahasiswa</th>
                            <th>Judul</th>
                            <th>Pembimbing</th>
                            <th>Admin Prodi</th>
                            <th>Diajukan</th>
                        </tr>
                    }
                >
                    {visible.length === 0 ? (
                        <EmptyRow colSpan={5}>
                            Belum ada pengajuan ujian pada tahap ini.
                        </EmptyRow>
                    ) : (
                        visible.map((row) => (
                            <tr
                                key={row.id}
                                className="cursor-pointer"
                                onClick={() => router.visit(row.admin_show_url)}
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
                                <td>
                                    <Tag label={row.admin_status_label} />
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
