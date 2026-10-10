import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { useState } from 'react';
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
    DataTable,
    EmptyRow,
    Field,
    Input,
    Tag,
} from '@/components/industry/ui';
import { index, update } from '@/routes/admin/lecturers';
import { destroy as release } from '@/routes/admin/lecturers/membership';
import type { AdminLecturerRow } from '@/types';

type Props = {
    /** The prodi the workspace is in. */
    department: string;
    lecturer: AdminLecturerRow & {
        initials: string;
        departments: { name: string; is_homebase: boolean }[];
    };
    students: {
        id: number;
        name: string;
        student_number: string;
        role_label: string;
    }[];
    statuses: { value: string; label: string }[];
    release: { allowed: boolean; reason: string | null };
};

export default function AdminLecturerShow({
    department,
    lecturer,
    students,
    statuses,
    release: releasable,
}: Props) {
    setLayoutProps({ title: 'Detail Dosen' });

    const [confirmingRelease, setConfirmingRelease] = useState(false);
    const [releasing, setReleasing] = useState(false);

    const form = useForm({
        max_supervisions: String(lecturer.max_supervisions),
        academic_position: lecturer.academic_position ?? '',
        status: lecturer.status,
    });

    return (
        <>
            <Head title={lecturer.name} />

            <div className="ind-page max-w-[760px]">
                <div>
                    <Link href={index()} className="text-[12.5px]">
                        ← Dosen
                    </Link>
                    <div className="mt-[6px] flex items-center gap-[10px]">
                        <Avatar initials={lecturer.initials} size={34} />
                        <div>
                            <h3 className="m-0">{lecturer.name}</h3>
                            <span className="ind-muted text-[13px]">
                                {lecturer.lecturer_number} · {lecturer.email}
                            </span>
                        </div>
                        <Tag label={lecturer.status_label} />
                    </div>
                </div>

                <Card>
                    <CardKicker>Program Studi</CardKicker>
                    <div className="ind-card-body flex flex-wrap gap-x-[var(--ind-space-6)] gap-y-[var(--ind-space-2)]">
                        {lecturer.departments.map((member) => (
                            <span
                                key={member.name}
                                className="flex items-center gap-2"
                            >
                                {member.name}
                                {member.is_homebase && (
                                    <Tag label="Homebase" tone="accent" />
                                )}
                            </span>
                        ))}
                    </div>
                </Card>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(update(lecturer.id).url, {
                            preserveScroll: true,
                        });
                    }}
                    className="ind-page"
                >
                    <h6 className="m-0">Pengaturan Dosen</h6>

                    <div className="grid [grid-template-columns:repeat(auto-fit,minmax(200px,1fr))] gap-[var(--ind-space-4)]">
                        <Field
                            label="Kuota Bimbingan"
                            error={form.errors.max_supervisions}
                        >
                            <Input
                                type="number"
                                min={0}
                                max={50}
                                value={form.data.max_supervisions}
                                onChange={(event) =>
                                    form.setData(
                                        'max_supervisions',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>

                        <Field
                            label="Jabatan Akademik"
                            optional
                            error={form.errors.academic_position}
                        >
                            <Input
                                value={form.data.academic_position}
                                onChange={(event) =>
                                    form.setData(
                                        'academic_position',
                                        event.target.value,
                                    )
                                }
                                placeholder="Lektor Kepala"
                            />
                        </Field>

                        <Field label="Status" error={form.errors.status}>
                            <select
                                className="ind-input"
                                value={form.data.status}
                                onChange={(event) =>
                                    form.setData('status', event.target.value)
                                }
                            >
                                {statuses.map((status) => (
                                    <option
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </div>

                    <Btn
                        type="submit"
                        variant="primary"
                        disabled={form.processing}
                        className="self-start"
                    >
                        {form.processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                    </Btn>
                </form>

                <div className="ind-section">
                    <h6>Mahasiswa yang Ditangani</h6>
                    <DataTable
                        head={
                            <tr>
                                <th>Mahasiswa</th>
                                <th>NIM</th>
                                <th>Peran</th>
                            </tr>
                        }
                    >
                        {students.length === 0 ? (
                            <EmptyRow colSpan={3}>
                                Dosen ini belum menangani mahasiswa di program
                                studi ini.
                            </EmptyRow>
                        ) : (
                            students.map((student) => (
                                <tr key={`${student.id}-${student.role_label}`}>
                                    <td className="font-semibold">
                                        {student.name}
                                    </td>
                                    <td className="ind-muted">
                                        {student.student_number}
                                    </td>
                                    <td>
                                        <Tag
                                            label={student.role_label}
                                            tone="outline"
                                        />
                                    </td>
                                </tr>
                            ))
                        )}
                    </DataTable>
                </div>

                <div className="ind-section">
                    <h6>Lepas dari Program Studi</h6>
                    <p className="ind-muted m-0 mb-[var(--ind-space-3)] text-[12.5px]">
                        {releasable.allowed
                            ? `Dosen ini tidak lagi dapat ditugaskan di ${department} dan kehilangan workspace program studi ini. Akun dan homebase-nya tidak berubah.`
                            : (releasable.reason ??
                              'Dosen ini tidak dapat dilepas dari program studi ini.')}
                    </p>
                    <Btn
                        variant="danger"
                        className="self-start"
                        disabled={!releasable.allowed}
                        onClick={() => setConfirmingRelease(true)}
                    >
                        Lepas dari {department}
                    </Btn>
                </div>

                <Dialog
                    open={confirmingRelease}
                    onOpenChange={setConfirmingRelease}
                >
                    <DialogContent>
                        <DialogTitle>Lepas dosen ini?</DialogTitle>
                        <DialogDescription>
                            {lecturer.name} akan dikeluarkan dari daftar dosen{' '}
                            {department} dan diberi tahu. Ia dapat ditambahkan
                            kembali kapan saja lewat NIP-nya.
                        </DialogDescription>

                        <DialogFooter>
                            <Btn onClick={() => setConfirmingRelease(false)}>
                                Batal
                            </Btn>
                            <Btn
                                variant="danger"
                                disabled={releasing}
                                onClick={() =>
                                    router.delete(release(lecturer.id).url, {
                                        onStart: () => setReleasing(true),
                                        onFinish: () => setReleasing(false),
                                    })
                                }
                            >
                                {releasing ? 'Melepas...' : 'Lepas Dosen'}
                            </Btn>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
