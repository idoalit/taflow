import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
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
import type { AdminLecturerRow } from '@/types';

type Props = {
    lecturer: AdminLecturerRow & { initials: string; departments: string[] };
    students: {
        id: number;
        name: string;
        student_number: string;
        role_label: string;
    }[];
    statuses: { value: string; label: string }[];
};

export default function AdminLecturerShow({
    lecturer,
    students,
    statuses,
}: Props) {
    setLayoutProps({ title: 'Detail Dosen' });

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
                    <div className="ind-card-body">
                        {lecturer.departments.join(', ')}
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
            </div>
        </>
    );
}
