import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { Eye } from 'lucide-react';
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
import { store as startImpersonating } from '@/routes/superadmin/users/impersonation';
import { store as sendInvitation } from '@/routes/superadmin/users/invitation';
import { update as updateLecturerDepartments } from '@/routes/superadmin/users/lecturer-departments';
import { update as updateRoles } from '@/routes/superadmin/users/roles';
import type { AccountRow, DepartmentOption } from '@/types';

type Props = {
    account: AccountRow & {
        is_self: boolean;
        is_super_admin: boolean;
        is_admin: boolean;
        admin_department_ids: number[];
        student: { number: string; department: string } | null;
        lecturer: {
            number: string;
            departments: string[];
            department_ids: number[];
            homebase_id: number | null;
        } | null;
        created_at: string | null;
    };
    deletion: { allowed: boolean; reason: string | null };
    impersonable: { allowed: boolean; reason: string | null };
    departments: DepartmentOption[];
};

/**
 * Every prodi a dosen works in, and which of them is the homebase. One table
 * rather than a checklist and a separate select, so a homebase can never be
 * picked from a prodi that is not ticked.
 */
function LecturerDepartments({
    departments,
    value,
    homebase,
    onChange,
    error,
}: {
    departments: DepartmentOption[];
    value: number[];
    homebase: number | null;
    onChange: (value: number[], homebase: number | null) => void;
    error?: string;
}) {
    const toggle = (id: number, checked: boolean) => {
        const next = checked
            ? [...value, id]
            : value.filter((kept) => kept !== id);

        // Unticking the homebase leaves the choice open rather than moving it
        // somewhere nobody chose; the first prodi ticked becomes it.
        const nextHomebase =
            homebase !== null && next.includes(homebase)
                ? homebase
                : next.length === 1
                  ? next[0]
                  : null;

        onChange(next, nextHomebase);
    };

    return (
        <fieldset className="m-0 border-0 p-0">
            <legend className="sr-only">Program studi dosen</legend>

            <table className="ind-table">
                <thead>
                    <tr>
                        <th>Program Studi</th>
                        <th className="w-[120px]">Homebase</th>
                    </tr>
                </thead>
                <tbody>
                    {departments.map((department) => {
                        const checked = value.includes(department.id);

                        return (
                            <tr key={department.id}>
                                <td>
                                    <label className="ind-check">
                                        <input
                                            type="checkbox"
                                            checked={checked}
                                            onChange={(event) =>
                                                toggle(
                                                    department.id,
                                                    event.target.checked,
                                                )
                                            }
                                        />
                                        {department.name}
                                        {!department.is_active && (
                                            <span className="ind-muted">
                                                {' '}
                                                (tidak aktif)
                                            </span>
                                        )}
                                    </label>
                                </td>
                                <td>
                                    <input
                                        type="radio"
                                        name="homebase"
                                        aria-label={`Jadikan ${department.name} homebase`}
                                        className="h-4 w-4 cursor-pointer accent-[var(--ind-accent-700)] disabled:cursor-not-allowed"
                                        checked={homebase === department.id}
                                        disabled={!checked}
                                        onChange={() =>
                                            onChange(value, department.id)
                                        }
                                    />
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>

            {error && (
                <p
                    className="mt-[5px] mb-0 text-[11.5px]"
                    style={{ color: 'var(--ind-status-error-fg)' }}
                >
                    {error}
                </p>
            )}
        </fieldset>
    );
}

export default function SuperAdminUserShow({
    account,
    deletion,
    impersonable,
    departments,
}: Props) {
    setLayoutProps({ title: 'Detail Akun' });

    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const [inviting, setInviting] = useState(false);
    const [impersonating, setImpersonating] = useState(false);

    const profile = useForm({ name: account.name, email: account.email });

    const roles = useForm({
        is_super_admin: account.is_super_admin,
        is_admin: account.is_admin,
        department_ids: account.admin_department_ids,
    });

    const removal = useForm({ email: '' });

    const membership = useForm<{
        department_ids: number[];
        homebase_id: number | null;
    }>({
        department_ids: account.lecturer?.department_ids ?? [],
        homebase_id: account.lecturer?.homebase_id ?? null,
    });

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
                                : 'Peran mahasiswa dan dosen mengikuti data yang dikelola program studi, jadi hanya dua peran ini yang diatur di sini.'}
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

                {account.lecturer && (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            membership.put(
                                updateLecturerDepartments(account.id).url,
                                { preserveScroll: true },
                            );
                        }}
                        className="ind-page"
                    >
                        <div>
                            <h6 className="m-0">Program Studi Dosen</h6>
                            <p className="ind-muted m-0 mt-1 text-[12.5px]">
                                Program studi tempat dosen ini dapat membimbing
                                dan menguji, dan satu di antaranya sebagai
                                homebase. Admin Prodi dapat menambahkan dosen ke
                                prodinya sendiri; memindahkan homebase hanya
                                dari sini.
                            </p>
                        </div>

                        <LecturerDepartments
                            departments={departments}
                            value={membership.data.department_ids}
                            homebase={membership.data.homebase_id}
                            onChange={(ids, homebase) =>
                                membership.setData({
                                    department_ids: ids,
                                    homebase_id: homebase,
                                })
                            }
                            error={
                                membership.errors.department_ids ??
                                membership.errors.homebase_id
                            }
                        />

                        <Btn
                            type="submit"
                            variant="primary"
                            disabled={
                                membership.processing || !membership.isDirty
                            }
                            className="self-start"
                        >
                            {membership.processing
                                ? 'Menyimpan...'
                                : 'Simpan Program Studi'}
                        </Btn>
                    </form>
                )}

                <div className="ind-section">
                    <h6>Lihat sebagai Akun Ini</h6>
                    <p className="ind-muted m-0 mb-[var(--ind-space-3)] text-[12.5px]">
                        {impersonable.allowed
                            ? 'Buka aplikasi persis seperti yang dilihat pemilik akun ini, untuk menelusuri apa yang tampil atau tidak tampil baginya. Selama itu tidak ada yang dapat diubah, dan awal serta akhirnya tercatat di Log Aktivitas.'
                            : (impersonable.reason ??
                              'Akun ini tidak dapat dilihat dengan cara ini.')}
                    </p>
                    <Btn
                        className="self-start"
                        disabled={!impersonable.allowed || impersonating}
                        onClick={() =>
                            router.post(
                                startImpersonating(account.id).url,
                                {},
                                {
                                    onStart: () => setImpersonating(true),
                                    onFinish: () => setImpersonating(false),
                                },
                            )
                        }
                    >
                        <Eye size={15} strokeWidth={1.5} />
                        {impersonating
                            ? 'Membuka...'
                            : `Lihat sebagai ${account.name}`}
                    </Btn>
                </div>

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
