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
import { show } from '@/routes/admin/students';
import type { AdminStudentRow } from '@/types';

type Props = {
    department: string;
    students: AdminStudentRow[];
    statuses: { value: string; label: string }[];
};

export default function AdminStudentIndex({
    department,
    students,
    statuses,
}: Props) {
    setLayoutProps({ title: 'Mahasiswa' });

    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('all');
    const [supervision, setSupervision] = useState('all');

    const term = search.trim().toLowerCase();
    const visible = students.filter(
        (student) =>
            (status === 'all' || student.status === status) &&
            (supervision === 'all' ||
                (supervision === 'none' && !student.has_supervisor)) &&
            (term === '' ||
                student.name.toLowerCase().includes(term) ||
                student.student_number.includes(term) ||
                student.email.toLowerCase().includes(term)),
    );

    return (
        <>
            <Head title="Mahasiswa" />

            <div className="ind-page">
                <PageHeader
                    title="Mahasiswa"
                    description={`Kelola data mahasiswa ${department} dan penentuan pembimbing/penguji.`}
                />

                <div className="flex flex-wrap items-center gap-[var(--ind-space-3)]">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Cari nama, NIM, atau surel..."
                        className="max-w-[300px]"
                    />

                    <Seg
                        name="Status"
                        value={status}
                        options={[
                            { value: 'all', label: 'Semua Status' },
                            ...statuses,
                        ]}
                        onChange={setStatus}
                    />

                    <Seg
                        name="Pembimbing"
                        value={supervision}
                        options={[
                            { value: 'all', label: 'Semua' },
                            { value: 'none', label: 'Belum Ada Pembimbing' },
                        ]}
                        onChange={setSupervision}
                    />

                    <span className="ind-muted ml-auto text-[12.5px]">
                        {visible.length} dari {students.length} mahasiswa
                    </span>
                </div>

                <DataTable
                    head={
                        <tr>
                            <th>Mahasiswa</th>
                            <th>Angkatan</th>
                            <th>Pembimbing</th>
                            <th>Penguji</th>
                            <th>Status</th>
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
                                    {student.enrollment_year}
                                </td>
                                <td>
                                    {student.supervisors.length === 0 ? (
                                        <Tag
                                            label="Belum ditentukan"
                                            tone="accent"
                                        />
                                    ) : (
                                        student.supervisors.join(', ')
                                    )}
                                </td>
                                <td className="ind-muted">
                                    {student.examiners.join(', ') || '—'}
                                </td>
                                <td>
                                    <Tag label={student.status_label} />
                                </td>
                            </tr>
                        ))
                    )}
                </DataTable>
            </div>
        </>
    );
}
