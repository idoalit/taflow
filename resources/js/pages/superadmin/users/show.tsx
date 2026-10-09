import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { DepartmentChecklist } from '@/components/industry/department-checklist';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/industry/dialog';
import {
    Avatar,
    Btn,
    Card,
    CardKicker,
    Field,
    Input,
    Tag,
} from '@/components/industry/ui';
import { destroy, index, update } from '@/routes/superadmin/users';
import { store as sendInvitation } from '@/routes/superadmin/users/invitation';
import { update as updateRoles } from '@/routes/superadmin/users/roles';
import type { AccountRow, DepartmentOption } from '@/types';

type Props = {
    account: AccountRow & {
        is_self: boolean;
        is_super_admin: boolean;
        is_admin: boolean;
        admin_department_ids: number[];
        student: { number: string; department: string } | null;
        lecturer: { number: string; departments: string[] } | null;
        created_at: string | null;
    };
    deletion: { allowed: boolean; reason: string | null };
    departments: DepartmentOption[];
};

export default function SuperAdminUserShow({
    account,
    deletion,
    departments,
}: Props) {
    setLayoutProps({ title: 'Detail Akun' });

    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const [inviting, setInviting] = useState(false);

    const profile = useForm({ name: account.name, email: account.email });

    const roles = useForm({
        is_super_admin: account.is_super_admin,
        is_admin: account.is_admin,
        department_ids: account.admin_department_ids,
    });

    const removal = useForm({ email: '' });

    return (
        <>
            <Head title={account.name} />

            <div className="ind-page max-w-[760px]">
                <div>
                    <Link href={index()} className="text-[12.5px]">
                        ← Pengguna
                    </Link>
                    <div className="mt-[6px] flex flex-wrap items-center gap-[10px]">
                        <Avatar initials={account.initials} size={34} />
                        <div>
                            <h3 className="m-0">{account.name}</h3>
                            <span className="ind-muted text-[13px]">
                                {account.email}
                            </span>
                        </div>
                        {account.roles.map((role) => (
                            <Tag key={role} label={role} tone="outline" />
                        ))}
                        {account.is_self && (
                            <Tag label="Akun Anda" tone="accent" />
                        )}
                    </div>
                </div>

                <div className="grid [grid-template-columns:repeat(auto-fit,minmax(200px,1fr))] gap-[var(--ind-space-3)]">
                    {account.student && (
                        <Card>
                            <CardKicker>Mahasiswa</CardKicker>
                            <div className="ind-card-title">
                                {account.student.number}
                            </div>
                            <div className="ind-card-meta">
                                {account.student.department}
                            </div>
                        </Card>
                    )}
                    {account.lecturer && (
                        <Card>
                            <CardKicker>Dosen</CardKicker>
                            <div className="ind-card-title">
                                {account.lecturer.number}
                            </div>
                            <div className="ind-card-meta">
                                {account.lecturer.departments.join(', ') ||
                                    'Belum terhubung ke program studi'}
                            </div>
                        </Card>
                    )}
                    <Card>
                        <CardKicker>Terdaftar</CardKicker>
                        <div className="ind-card-title">
                            {account.created_at ?? '—'}
                        </div>
                    </Card>
                </div>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        profile.put(update(account.id).url, {
                            preserveScroll: true,
                        });
                    }}
                    className="ind-page"
                >
                    <h6 className="m-0">Data Akun</h6>

                    <div className="grid [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))] gap-[var(--ind-space-4)]">
                        <Field label="Nama" error={profile.errors.name}>
                            <Input
                                value={profile.data.name}
                                onChange={(event) =>
                                    profile.setData('name', event.target.value)
                                }
                            />
                        </Field>

                        <Field label="Surel" error={profile.errors.email}>
                            <Input
                                type="email"
                                value={profile.data.email}
                                onChange={(event) =>
                                    profile.setData('email', event.target.value)
                                }
                            />
                        </Field>
                    </div>

                    <Btn
                        type="submit"
                        variant="primary"
                        disabled={profile.processing || !profile.isDirty}
                        className="self-start"
                    >
                        {profile.processing
                            ? 'Menyimpan...'
                            : 'Simpan Data Akun'}
                    </Btn>
                </form>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        roles.put(updateRoles(account.id).url, {
                            preserveScroll: true,
                        });
                    }}
                    className="ind-section flex flex-col gap-[var(--ind-space-4)]"
                >
                    <div>
                        <h6 className="mb-1">Peran Pengelola</h6>
                        <p className="ind-muted m-0 text-[12.5px]">
                            {account.student
                                ? 'Akun mahasiswa tidak dapat diberi peran pengelola.'
                                : 'Peran mahasiswa dan dosen mengikuti data yang diimpor program studi, jadi hanya dua peran ini yang diatur di sini.'}
                        </p>
                    </div>

                    {!account.student && (
                        <>
                            <div className="flex flex-col gap-[var(--ind-space-2)]">
                                <label className="ind-check">
                                    <input
                                        type="checkbox"
                                        checked={roles.data.is_super_admin}
                                        disabled={account.is_self}
                                        onChange={(event) =>
                                            roles.setData(
                                                'is_super_admin',
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    Superadmin
                                </label>
                                {account.is_self && (
                                    <p className="ind-muted m-0 text-[12.5px]">
                                        Anda tidak dapat mencabut peran
                                        superadmin dari akun sendiri.
                                    </p>
                                )}
                                {roles.errors.is_super_admin && (
                                    <p
                                        className="m-0 text-[12.5px]"
                                        style={{
                                            color: 'var(--ind-status-error-fg)',
                                        }}
                                    >
                                        {roles.errors.is_super_admin}
                                    </p>
                                )}

                                <label className="ind-check">
                                    <input
                                        type="checkbox"
                                        checked={roles.data.is_admin}
                                        onChange={(event) =>
                                            roles.setData(
                                                'is_admin',
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    Admin Prodi
                                </label>
                                {roles.errors.is_admin && (
                                    <p
                                        className="m-0 text-[12.5px]"
                                        style={{
                                            color: 'var(--ind-status-error-fg)',
                                        }}
                                    >
                                        {roles.errors.is_admin}
                                    </p>
                                )}
                            </div>

                            {roles.data.is_admin && (
                                <DepartmentChecklist
                                    departments={departments}
                                    value={roles.data.department_ids}
                                    onChange={(ids) =>
                                        roles.setData('department_ids', ids)
                                    }
                                    error={roles.errors.department_ids}
                                />
                            )}

                            <Btn
                                type="submit"
                                variant="primary"
                                disabled={roles.processing || !roles.isDirty}
                                className="self-start"
                            >
                                {roles.processing
                                    ? 'Menyimpan...'
                                    : 'Simpan Peran'}
                            </Btn>
                        </>
                    )}
                </form>

                <div className="ind-section">
                    <h6>Tautan Aktivasi</h6>
                    <p className="ind-muted m-0 mb-[var(--ind-space-3)] text-[12.5px]">
                        Kirim ulang tautan untuk menyetel kata sandi, bagi akun
                        yang belum pernah masuk atau lupa kata sandinya.
                    </p>
                    <Btn
                        className="self-start"
                        disabled={inviting}
                        onClick={() =>
                            router.post(
                                sendInvitation(account.id).url,
                                {},
                                {
                                    preserveScroll: true,
                                    onStart: () => setInviting(true),
                                    onFinish: () => setInviting(false),
                                },
                            )
                        }
                    >
                        {inviting ? 'Mengirim...' : 'Kirim Tautan Aktivasi'}
                    </Btn>
                </div>

                <div className="ind-section">
                    <h6>Hapus Akun</h6>
                    <p className="ind-muted m-0 mb-[var(--ind-space-3)] text-[12.5px]">
                        {deletion.allowed
                            ? 'Menghapus akun ikut menghapus seluruh data miliknya: proposal, sesi bimbingan, logbook, dan ujian.'
                            : (deletion.reason ??
                              'Akun ini tidak dapat dihapus.')}
                    </p>
                    <Btn
                        variant="danger"
                        className="self-start"
                        disabled={!deletion.allowed}
                        onClick={() => setConfirmingDelete(true)}
                    >
                        Hapus Akun
                    </Btn>
                </div>

                <Dialog
                    open={confirmingDelete}
                    onOpenChange={(open) => {
                        setConfirmingDelete(open);

                        if (!open) {
                            removal.reset();
                            removal.clearErrors();
                        }
                    }}
                >
                    <DialogContent>
                        <DialogTitle>Hapus akun ini?</DialogTitle>
                        <DialogDescription>
                            Akun {account.name} beserta seluruh datanya akan
                            dihapus permanen dan tidak dapat dikembalikan. Ketik{' '}
                            <strong>{account.email}</strong> untuk
                            mengonfirmasi.
                        </DialogDescription>

                        <Field label="Surel akun" error={removal.errors.email}>
                            <Input
                                value={removal.data.email}
                                onChange={(event) =>
                                    removal.setData('email', event.target.value)
                                }
                                autoComplete="off"
                            />
                        </Field>

                        <DialogFooter>
                            <Btn onClick={() => setConfirmingDelete(false)}>
                                Batal
                            </Btn>
                            <Btn
                                variant="danger"
                                disabled={
                                    removal.processing ||
                                    removal.data.email !== account.email
                                }
                                onClick={() =>
                                    removal.delete(destroy(account.id).url)
                                }
                            >
                                {removal.processing
                                    ? 'Menghapus...'
                                    : 'Hapus Akun'}
                            </Btn>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
