import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import { EnterDepartment } from '@/components/industry/enter-department';
import {
    BtnLink,
    DataTable,
    EmptyRow,
    Input,
    PageHeader,
    Seg,
    Tag,
} from '@/components/industry/ui';
import { create, show } from '@/routes/superadmin/departments';
import type { DepartmentRow } from '@/types';

type Status = 'all' | 'active' | 'inactive';

export default function SuperAdminDepartmentIndex({
    departments,
}: {
    departments: DepartmentRow[];
}) {
    setLayoutProps({ title: 'Program Studi' });

    const [search, setSearch] = useState('');
    const [status, setStatus] = useState<Status>('all');

    const term = search.trim().toLowerCase();
    const visible = departments.filter(
        (department) =>
            (status === 'all' ||
                department.is_active === (status === 'active')) &&
            (term === '' ||
                department.name.toLowerCase().includes(term) ||
                department.code.toLowerCase().includes(term) ||
                (department.faculty?.toLowerCase().includes(term) ?? false)),
    );

    return (
        <>
            <Head title="Program Studi" />

            <div className="ind-page">
                <PageHeader
                    title="Program Studi"
                    description="Setiap program studi punya admin, aturan tugas akhir, dan rubriknya sendiri."
                    actions={
                        <BtnLink href={create()} variant="primary">
                            Tambah Program Studi
                        </BtnLink>
                    }
                />

                <div className="flex flex-wrap items-center gap-[var(--ind-space-3)]">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Cari nama, kode, atau fakultas..."
                        className="max-w-[300px]"
                        aria-label="Cari program studi"
                    />

                    <Seg
                        name="Status"
                        value={status}
                        options={[
                            { value: 'all', label: 'Semua' },
                            { value: 'active', label: 'Aktif' },
                            { value: 'inactive', label: 'Tidak Aktif' },
                        ]}
                        onChange={setStatus}
                    />

                    <span className="ind-muted ml-auto text-[12.5px]">
                        {visible.length} dari {departments.length} program studi
                    </span>
                </div>

                <DataTable
                    head={
                        <tr>
                            <th>Program Studi</th>
                            <th>Fakultas</th>
                            <th>Mahasiswa</th>
                            <th>Dosen</th>
                            <th>Admin</th>
                            <th>Status</th>
                            <th />
                        </tr>
                    }
                >
                    {visible.length === 0 ? (
                        <EmptyRow colSpan={7}>
                            {departments.length === 0
                                ? 'Belum ada program studi.'
                                : 'Tidak ada program studi yang cocok dengan filter ini.'}
                        </EmptyRow>
                    ) : (
                        visible.map((department) => (
                            <tr
                                key={department.id}
                                className="cursor-pointer"
                                onClick={() =>
                                    router.visit(show(department.id))
                                }
                            >
                                <td className="font-semibold">
                                    {department.name}
                                    <span className="ind-muted block text-[12px]">
                                        {department.code}
                                    </span>
                                </td>
                                <td className="ind-muted">
                                    {department.faculty ?? '—'}
                                </td>
                                <td className="ind-muted">
                                    {department.students}
                                </td>
                                <td className="ind-muted">
                                    {department.lecturers}
                                </td>
                                <td>
                                    {department.admins === 0 ? (
                                        <Tag label="Belum ada" tone="accent" />
                                    ) : (
                                        <span className="ind-muted">
                                            {department.admins}
                                        </span>
                                    )}
                                </td>
                                <td>
                                    <Tag label={department.status_label} />
                                </td>
                                <td className="text-right">
                                    <EnterDepartment
                                        workspace={department.workspace}
                                        name={department.name}
                                        compact
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
