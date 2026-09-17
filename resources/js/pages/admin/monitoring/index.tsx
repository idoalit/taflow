import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import {
    DataTable,
    EmptyRow,
    Input,
    PageHeader,
    Seg,
    Tag,
} from '@/components/industry/ui';
import { show } from '@/routes/admin/monitoring';
import type { AdminStudentRow } from '@/types';

type Props = {
    department: string;
    students: AdminStudentRow[];
    stages: { value: string; label: string }[];
};

export default function AdminMonitoringIndex({
    department,
    students,
    stages,
}: Props) {
    setLayoutProps({ title: 'Monitoring Bimbingan' });

    const [search, setSearch] = useState('');
    const [stage, setStage] = useState('all');

    const term = search.trim().toLowerCase();
    const visible = students.filter(
        (student) =>
            (stage === 'all' ||
                student.stage_label ===
                    stages.find((option) => option.value === stage)?.label) &&
            (term === '' ||
                student.name.toLowerCase().includes(term) ||
                student.student_number.includes(term)),
    );

    return (
        <>
            <Head title="Monitoring Bimbingan" />

            <div className="ind-page">
                <PageHeader
                    title="Monitoring Progres Bimbingan"
                    description={`Memantau kemajuan sesi bimbingan seluruh mahasiswa di ${department}. Halaman ini bersifat lihat saja.`}
                />

                <div className="flex flex-wrap items-center gap-[var(--ind-space-3)]">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Cari nama atau NIM..."
                        className="max-w-[300px]"
                    />

                    <Seg
                        name="Tahap"
                        value={stage}
                        options={[
                            { value: 'all', label: 'Semua Tahap' },
                            ...stages,
                        ]}
                        onChange={setStage}
                    />

                    <span className="ind-muted ml-auto text-[12.5px]">
                        {visible.length} dari {students.length} mahasiswa
                    </span>
                </div>

                <DataTable
                    head={
                        <tr>
                            <th>Mahasiswa</th>
                            <th>Pembimbing</th>
                            <th>Tahap</th>
                            <th>Sesi Bimbingan</th>
                            <th>Status Proposal</th>
                        </tr>
                    }
                >
                    {visible.length === 0 ? (
                        <EmptyRow colSpan={5}>
                            Tidak ada mahasiswa yang cocok dengan filter ini.
                        </EmptyRow>
                    ) : (
                        visible.map((student) => (
                            <tr
                                key={student.id}
                                className="cursor-pointer"
                                onClick={() => router.visit(show(student.id))}
                            >
                                <td className="font-semibold">
                                    {student.name}
                                    <span className="ind-muted block text-[12px]">
                                        {student.student_number}
                                    </span>
                                </td>
                                <td className="ind-muted">
                                    {student.supervisors.join(', ') ||
                                        'Belum ditentukan'}
                                </td>
                                <td>
                                    <Tag
                                        label={student.stage_label}
                                        tone="outline"
                                    />
                                </td>
                                <td className="min-w-[160px]">
                                    <div className="flex items-center gap-2">
                                        <div className="ind-bar-track w-[80px]">
                                            <div
                                                className="ind-bar-fill"
                                                style={{
                                                    width: `${student.progress_percent}%`,
                                                }}
                                            />
                                        </div>
                                        <span className="ind-muted text-[12px] whitespace-nowrap">
                                            {student.sessions_completed}/
                                            {student.sessions_required}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <Tag
                                        label={student.proposal_status_label}
                                    />
                                </td>
                            </tr>
                        ))
                    )}
                </DataTable>
            </div>
        </>
    );
}
