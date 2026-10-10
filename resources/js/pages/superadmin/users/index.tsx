import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useRef, useState } from 'react';
import {
    Avatar,
    BtnLink,
    DataTable,
    EmptyRow,
    Input,
    PageHeader,
    Pagination,
    Tag,
} from '@/components/industry/ui';
import { create, index, show } from '@/routes/superadmin/users';
import type {
    AccountRow,
    DepartmentOption,
    Paginated,
    SelectOption,
} from '@/types';

type Filters = { search: string; role: string; department: string };

type Props = {
    users: Paginated<AccountRow>;
    filters: Filters;
    roles: SelectOption[];
    departments: DepartmentOption[];
};

const SEARCH_DELAY = 300;

export default function SuperAdminUserIndex({
    users,
    filters,
    roles,
    departments,
}: Props) {
    setLayoutProps({ title: 'Pengguna' });

    const [search, setSearch] = useState(filters.search);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    // The list is filtered on the server, so a change of filter is a visit —
    // one that starts again from the first page.
    const apply = (next: Partial<Filters>) => {
        const merged = { ...filters, search, ...next };

        router.get(
            index.url(),
            {
                search: merged.search || undefined,
                role: merged.role === 'all' ? undefined : merged.role,
                department:
                    merged.department === 'all' ? undefined : merged.department,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Pengguna" />

            <div className="ind-page">
                <PageHeader
                    title="Pengguna"
                    description="Semua akun di seluruh program studi. Mahasiswa dan dosen ditambahkan oleh program studinya."
                    actions={
                        <BtnLink href={create()} variant="primary">
                            Tambah Akun
                        </BtnLink>
                    }
                />

                <div className="flex flex-wrap items-center gap-[var(--ind-space-3)]">
                    <Input
                        value={search}
                        onChange={(event) => {
                            const value = event.target.value;

                            setSearch(value);

                            if (timer.current !== null) {
                                clearTimeout(timer.current);
                            }

                            timer.current = setTimeout(
                                () => apply({ search: value }),
                                SEARCH_DELAY,
                            );
                        }}
                        placeholder="Cari nama atau surel..."
                        className="max-w-[280px]"
                        aria-label="Cari pengguna"
                    />

                    <select
                        className="ind-input max-w-[180px]"
                        value={filters.role}
                        onChange={(event) =>
                            apply({ role: event.target.value })
                        }
                        aria-label="Peran"
                    >
                        <option value="all">Semua Peran</option>
                        {roles.map((role) => (
                            <option key={role.value} value={role.value}>
                                {role.label}
                            </option>
                        ))}
                    </select>

                    <select
                        className="ind-input max-w-[240px]"
                        value={filters.department}
                        onChange={(event) =>
                            apply({ department: event.target.value })
                        }
                        aria-label="Program studi"
                    >
                        <option value="all">Semua Program Studi</option>
                        {departments.map((department) => (
                            <option key={department.id} value={department.id}>
                                {department.name}
                            </option>
                        ))}
                    </select>
                </div>

                <DataTable
                    head={
                        <tr>
                            <th>Pengguna</th>
                            <th>Peran</th>
                            <th>Program Studi</th>
                        </tr>
                    }
                >
                    {users.data.length === 0 ? (
                        <EmptyRow colSpan={3}>
                            Tidak ada pengguna yang cocok dengan filter ini.
                        </EmptyRow>
                    ) : (
                        users.data.map((user) => (
                            <tr
                                key={user.id}
                                className="cursor-pointer"
                                onClick={() => router.visit(show(user.id))}
                            >
                                <td>
                                    <div className="flex items-center gap-[10px]">
                                        <Avatar
                                            initials={user.initials}
                                            size={28}
                                        />
                                        <div className="min-w-0">
                                            <span className="block font-semibold">
                                                {user.name}
                                            </span>
                                            <span className="ind-muted block text-[12px]">
                                                {user.email}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div className="flex flex-wrap gap-[6px]">
                                        {user.roles.length === 0 ? (
                                            <Tag
                                                label="Tanpa Peran"
                                                tone="neutral"
                                            />
                                        ) : (
                                            user.roles.map((role) => (
                                                <Tag
                                                    key={role}
                                                    label={role}
                                                    tone="outline"
                                                />
                                            ))
                                        )}
                                    </div>
                                </td>
                                <td className="ind-muted">
                                    {user.departments.length === 0
                                        ? '—'
                                        : user.departments.join(', ')}
                                </td>
                            </tr>
                        ))
                    )}
                </DataTable>

                <Pagination page={users} unit="pengguna" />
            </div>
        </>
    );
}
