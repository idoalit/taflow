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
import { show } from '@/routes/admin/lecturers';
import type { AdminLecturerRow } from '@/types';

type Props = {
    department: string;
    lecturers: AdminLecturerRow[];
    statuses: { value: string; label: string }[];
};

export default function AdminLecturerIndex({
    department,
    lecturers,
    statuses,
}: Props) {
    setLayoutProps({ title: 'Dosen' });

    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('all');

    const term = search.trim().toLowerCase();
    const visible = lecturers.filter(
        (lecturer) =>
            (status === 'all' || lecturer.status === status) &&
            (term === '' ||
                lecturer.name.toLowerCase().includes(term) ||
                lecturer.lecturer_number.includes(term) ||
                lecturer.email.toLowerCase().includes(term)),
    );

    return (
        <>
            <Head title="Dosen" />

            <div className="ind-page">
                <PageHeader
                    title="Dosen"
                    description={`Dosen di ${department} beserta beban bimbingannya.`}
                />

                <div className="flex flex-wrap items-center gap-[var(--ind-space-3)]">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Cari nama, NIP, atau surel..."
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

                    <span className="ind-muted ml-auto text-[12.5px]">
                        {visible.length} dari {lecturers.length} dosen
                    </span>
                </div>

                <DataTable
                    head={
                        <tr>
                            <th>Dosen</th>
                            <th>NIP</th>
                            <th>Jabatan</th>
                            <th>Beban Bimbingan</th>
                            <th>Status</th>
                        </tr>
                    }
                >
                    {visible.length === 0 ? (
                        <EmptyRow colSpan={5}>
                            Tidak ada dosen yang cocok dengan filter ini.
                        </EmptyRow>
                    ) : (
                        visible.map((lecturer) => (
                            <tr
                                key={lecturer.id}
                                className="cursor-pointer"
                                onClick={() => router.visit(show(lecturer.id))}
                            >
                                <td className="font-semibold">
                                    {lecturer.name}
                                    <span className="ind-muted block text-[12px]">
                                        {lecturer.email}
                                    </span>
                                </td>
                                <td className="ind-muted">
                                    {lecturer.lecturer_number}
                                </td>
                                <td className="ind-muted">
                                    {lecturer.academic_position ?? '—'}
                                </td>
                                <td className="min-w-[160px]">
                                    <div className="flex items-center gap-2">
                                        <div className="ind-bar-track w-[80px]">
                                            <div
                                                className="ind-bar-fill"
                                                data-full={lecturer.at_capacity}
                                                style={{
                                                    width: `${lecturer.load_percent}%`,
                                                }}
                                            />
                                        </div>
                                        <span className="ind-muted text-[12px] whitespace-nowrap">
                                            {lecturer.load}/
                                            {lecturer.max_supervisions}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <Tag label={lecturer.status_label} />
                                </td>
                            </tr>
                        ))
                    )}
                </DataTable>
            </div>
        </>
    );
}
