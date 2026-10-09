import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/industry/dialog';
import { EnterDepartment } from '@/components/industry/enter-department';
import { SearchSelect } from '@/components/industry/search-select';
import {
    Avatar,
    Btn,
    BtnLink,
    Card,
    CardKicker,
    DataTable,
    EmptyRow,
    Field,
    Tag,
} from '@/components/industry/ui';
import { destroy, edit, index } from '@/routes/superadmin/departments';
import {
    destroy as removeAdmin,
    store as assignAdmin,
} from '@/routes/superadmin/departments/admins';
import {
    create as createUser,
    show as showUser,
} from '@/routes/superadmin/users';
import type { DepartmentRow } from '@/types';

type Props = {
    department: DepartmentRow & { is_empty: boolean };
    admins: { id: number; name: string; email: string; initials: string }[];
    candidates: { id: number; name: string; email: string }[];
};

export default function SuperAdminDepartmentShow({
    department,
    admins,
    candidates,
}: Props) {
    setLayoutProps({ title: 'Detail Program Studi' });

    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const assignment = useForm<{ user_id: number | null }>({ user_id: null });
    const deletion = useForm({});

    return (
        <>
            <Head title={department.name} />

            <div className="ind-page max-w-[820px]">
                <div>
                    <Link href={index()} className="text-[12.5px]">
                        ← Program Studi
                    </Link>
                    <div className="mt-[6px] flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div className="flex items-center gap-[10px]">
                                <h3 className="m-0">{department.name}</h3>
                                <Tag label={department.status_label} />
                            </div>
                            <span className="ind-muted text-[13px]">
                                {[
                                    department.code,
                                    department.faculty,
                                    department.city,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </span>
                        </div>
                        <div className="flex flex-wrap gap-[var(--ind-space-3)]">
                            <BtnLink href={edit(department.id)}>Ubah</BtnLink>
                            <EnterDepartment
                                workspace={department.workspace}
                                name={department.name}
                                variant="primary"
                            />
                        </div>
                    </div>
                </div>

                {department.description && (
                    <p className="ind-muted m-0">{department.description}</p>
                )}

                <div className="grid [grid-template-columns:repeat(auto-fit,minmax(170px,1fr))] gap-[var(--ind-space-3)]">
                    <Card>
                        <CardKicker>Mahasiswa</CardKicker>
                        <div className="ind-stat">{department.students}</div>
                    </Card>
                    <Card>
                        <CardKicker>Dosen</CardKicker>
                        <div className="ind-stat">{department.lecturers}</div>
                    </Card>
                    <Card>
                        <CardKicker>Admin Prodi</CardKicker>
                        <div className="ind-stat">{department.admins}</div>
                    </Card>
                </div>

                <div className="ind-section">
                    <h6>Admin Prodi</h6>
                    <DataTable
                        head={
                            <tr>
                                <th>Admin</th>
                                <th>Surel</th>
                                <th />
                            </tr>
                        }
                    >
                        {admins.length === 0 ? (
                            <EmptyRow colSpan={3}>
                                Program studi ini belum memiliki admin, sehingga
                                belum ada yang dapat mengelolanya.
                            </EmptyRow>
                        ) : (
                            admins.map((admin) => (
                                <tr key={admin.id}>
                                    <td>
                                        <Link
                                            href={showUser(admin.id)}
                                            className="flex items-center gap-[10px] font-semibold no-underline"
                                        >
                                            <Avatar
                                                initials={admin.initials}
                                                size={26}
                                            />
                                            {admin.name}
                                        </Link>
                                    </td>
                                    <td className="ind-muted">{admin.email}</td>
                                    <td className="text-right">
                                        <Btn
                                            variant="ghost"
                                            aria-label={`Lepas ${admin.name} dari Admin Prodi`}
                                            onClick={() =>
                                                router.delete(
                                                    removeAdmin([
                                                        department.id,
                                                        admin.id,
                                                    ]).url,
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Lepas
                                        </Btn>
                                    </td>
                                </tr>
                            ))
                        )}
                    </DataTable>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            assignment.post(assignAdmin(department.id).url, {
                                preserveScroll: true,
                                onSuccess: () => assignment.reset(),
                            });
                        }}
                        className="mt-[var(--ind-space-4)] flex flex-wrap items-end gap-[var(--ind-space-3)]"
                    >
                        <div className="min-w-[260px] flex-1">
                            <Field
                                label="Tugaskan akun yang sudah ada"
                                error={assignment.errors.user_id}
                            >
                                <SearchSelect
                                    value={assignment.data.user_id}
                                    options={candidates.map((candidate) => ({
                                        value: candidate.id,
                                        label: candidate.name,
                                        hint: candidate.email,
                                    }))}
                                    onChange={(value) =>
                                        assignment.setData('user_id', value)
                                    }
                                    placeholder="Pilih akun"
                                    searchPlaceholder="Cari nama atau surel..."
                                />
                            </Field>
                        </div>
                        <Btn
                            type="submit"
                            disabled={
                                assignment.processing ||
                                assignment.data.user_id === null
                            }
                        >
                            Tugaskan
                        </Btn>
                        <BtnLink
                            href={createUser({
                                query: { department: department.id },
                            })}
                        >
                            Buat Akun Admin Baru
                        </BtnLink>
                    </form>
                </div>

                <div className="ind-section">
                    <h6>Hapus Program Studi</h6>
                    <p className="ind-muted m-0 mb-[var(--ind-space-3)] text-[12.5px]">
                        {department.is_empty
                            ? 'Program studi ini belum memiliki mahasiswa maupun dosen, sehingga masih dapat dihapus.'
                            : 'Program studi yang sudah memiliki mahasiswa atau dosen tidak dapat dihapus. Nonaktifkan lewat tombol Ubah untuk mengarsipkannya.'}
                    </p>
                    <Btn
                        variant="danger"
                        className="self-start"
                        disabled={!department.is_empty}
                        onClick={() => setConfirmingDelete(true)}
                    >
                        Hapus Program Studi
                    </Btn>
                </div>

                <Dialog
                    open={confirmingDelete}
                    onOpenChange={setConfirmingDelete}
                >
                    <DialogContent>
                        <DialogTitle>Hapus program studi?</DialogTitle>
                        <DialogDescription>
                            &ldquo;{department.name}&rdquo; beserta aturan dan
                            rubriknya akan dihapus permanen. Tindakan ini tidak
                            dapat dibatalkan.
                        </DialogDescription>

                        <DialogFooter>
                            <Btn onClick={() => setConfirmingDelete(false)}>
                                Batal
                            </Btn>
                            <Btn
                                variant="danger"
                                disabled={deletion.processing}
                                onClick={() =>
                                    deletion.delete(destroy(department.id).url)
                                }
                            >
                                {deletion.processing
                                    ? 'Menghapus...'
                                    : 'Hapus Program Studi'}
                            </Btn>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
