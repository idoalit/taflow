import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { DepartmentChecklist } from '@/components/industry/department-checklist';
import {
    Btn,
    BtnLink,
    Field,
    Input,
    PageHeader,
    Seg,
} from '@/components/industry/ui';
import { index, store } from '@/routes/superadmin/users';
import type { DepartmentOption } from '@/types';

type Role = 'admin' | 'super-admin';

export default function SuperAdminUserCreate({
    departments,
    preselectedDepartment,
}: {
    departments: DepartmentOption[];
    preselectedDepartment: number | null;
}) {
    setLayoutProps({ title: 'Tambah Akun' });

    const form = useForm<{
        name: string;
        email: string;
        role: Role;
        department_ids: number[];
    }>({
        name: '',
        email: '',
        role: 'admin',
        department_ids:
            preselectedDepartment === null ? [] : [preselectedDepartment],
    });

    return (
        <>
            <Head title="Tambah Akun" />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(store().url);
                }}
                className="ind-page max-w-[640px]"
            >
                <div>
                    <Link href={index()} className="text-[12.5px]">
                        ← Pengguna
                    </Link>
                </div>

                <PageHeader
                    title="Tambah Akun"
                    description="Akun baru tidak diberi kata sandi. Pemiliknya menerima tautan aktivasi lewat surel dan menyetel kata sandinya sendiri."
                />

                <Field label="Peran">
                    <Seg<Role>
                        name="Peran"
                        value={form.data.role}
                        options={[
                            { value: 'admin', label: 'Admin Prodi' },
                            { value: 'super-admin', label: 'Superadmin' },
                        ]}
                        onChange={(role) => form.setData('role', role)}
                    />
                </Field>

                <div className="grid [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))] gap-[var(--ind-space-4)]">
                    <Field label="Nama" error={form.errors.name}>
                        <Input
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            autoComplete="off"
                        />
                    </Field>

                    <Field label="Surel" error={form.errors.email}>
                        <Input
                            type="email"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                            autoComplete="off"
                        />
                    </Field>
                </div>

                {form.data.role === 'admin' ? (
                    <DepartmentChecklist
                        departments={departments}
                        value={form.data.department_ids}
                        onChange={(ids) => form.setData('department_ids', ids)}
                        error={form.errors.department_ids}
                    />
                ) : (
                    <p className="ind-muted m-0 text-[12.5px]">
                        Superadmin mengelola seluruh program studi, pengguna,
                        dan pengaturan sistem. Berikan hanya kepada orang yang
                        memang bertanggung jawab atas sistem ini.
                    </p>
                )}

                <div className="flex flex-wrap gap-[var(--ind-space-3)]">
                    <Btn
                        type="submit"
                        variant="primary"
                        disabled={form.processing}
                    >
                        {form.processing
                            ? 'Membuat...'
                            : 'Buat Akun & Kirim Tautan'}
                    </Btn>
                    <BtnLink href={index()}>Batal</BtnLink>
                </div>
            </form>
        </>
    );
}
