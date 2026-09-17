import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { TriangleAlert, Trash2 } from 'lucide-react';
import {
    Avatar,
    Btn,
    Card,
    CardKicker,
    Field,
    Tag,
} from '@/components/industry/ui';
import { SearchSelect } from '@/components/industry/search-select';
import { index } from '@/routes/admin/students';
import { update as updateSupervisors } from '@/routes/admin/students/supervisors';
import type { AdminStudentDetail } from '@/types';

type Props = {
    student: AdminStudentDetail;
    lecturers: { id: number; name: string; load: number; max: number }[];
    maxSupervisors: number;
    roles: { value: string; label: string }[];
};

export default function AdminStudentShow({
    student,
    lecturers,
    maxSupervisors,
    roles,
}: Props) {
    setLayoutProps({ title: 'Detail Mahasiswa' });

    const form = useForm({
        assignments: student.assignments.map((assignment) => ({
            lecturer_id: assignment.lecturer_id as number | null,
            role: assignment.role,
        })),
    });

    const supervisorCount = form.data.assignments.filter(
        (assignment) => assignment.role === 'supervisor',
    ).length;

    /** The design warns when a chosen dosen has no room left to take anyone. */
    const atCapacity = (lecturerId: number | null) => {
        const lecturer = lecturers.find((option) => option.id === lecturerId);

        return lecturer !== undefined && lecturer.load >= lecturer.max;
    };

    const setRow = (
        index: number,
        patch: Partial<(typeof form.data.assignments)[number]>,
    ) =>
        form.setData(
            'assignments',
            form.data.assignments.map((row, i) =>
                i === index ? { ...row, ...patch } : row,
            ),
        );

    return (
        <>
            <Head title={student.name} />

            <div className="ind-page max-w-[760px]">
                <div>
                    <Link href={index()} className="text-[12.5px]">
                        ← Mahasiswa
                    </Link>
                    <div className="mt-[6px] flex items-center gap-[10px]">
                        <Avatar initials={student.initials} size={34} />
                        <div>
                            <h3 className="m-0">{student.name}</h3>
                            <span className="ind-muted text-[13px]">
                                {student.student_number} · {student.department}{' '}
                                · Angkatan {student.enrollment_year}
                            </span>
                        </div>
                        <Tag label={student.status_label} />
                    </div>
                </div>

                <div className="grid [grid-template-columns:repeat(auto-fit,minmax(200px,1fr))] gap-[var(--ind-space-3)]">
                    <Card>
                        <CardKicker>Tahap</CardKicker>
                        <div className="ind-card-title">
                            {student.stage_label}
                        </div>
                    </Card>
                    <Card>
                        <CardKicker>Sesi Bimbingan</CardKicker>
                        <div className="ind-card-title">
                            {student.sessions_completed}/
                            {student.sessions_required}
                        </div>
                    </Card>
                    <Card>
                        <CardKicker>Status Proposal</CardKicker>
                        <div className="ind-card-title">
                            {student.proposal_status_label}
                        </div>
                        {student.proposal_title && (
                            <div className="ind-card-meta">
                                {student.proposal_title}
                            </div>
                        )}
                    </Card>
                </div>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(updateSupervisors(student.id).url, {
                            preserveScroll: true,
                        });
                    }}
                    className="ind-page"
                >
                    <div>
                        <h6 className="mb-1">Pembimbing &amp; Penguji</h6>
                        <p className="ind-muted m-0 text-[12.5px]">
                            Program studi ini membatasi {maxSupervisors} dosen
                            pembimbing per mahasiswa. Saat ini {supervisorCount}{' '}
                            pembimbing dipilih.
                        </p>
                    </div>

                    {form.errors.assignments && (
                        <p
                            className="m-0 text-[12.5px]"
                            style={{ color: 'var(--ind-status-error-fg)' }}
                        >
                            {form.errors.assignments}
                        </p>
                    )}

                    <div className="flex flex-col gap-[var(--ind-space-3)]">
                        {form.data.assignments.map((assignment, index) => (
                            <div
                                // Rows are positional; a lecturer may legitimately
                                // appear twice while the form is being edited.
                                key={index}
                                className="flex flex-wrap items-end gap-[var(--ind-space-3)]"
                            >
                                <div className="min-w-[220px] flex-[1.4]">
                                    <Field
                                        label={
                                            index === 0 ? 'Dosen' : undefined
                                        }
                                    >
                                        <SearchSelect
                                            value={assignment.lecturer_id}
                                            options={lecturers.map(
                                                (lecturer) => ({
                                                    value: lecturer.id,
                                                    label: lecturer.name,
                                                    hint: `${lecturer.load}/${lecturer.max} bimbingan`,
                                                }),
                                            )}
                                            onChange={(value) =>
                                                setRow(index, {
                                                    lecturer_id: value,
                                                })
                                            }
                                            placeholder="Pilih dosen"
                                        />
                                    </Field>

                                    {atCapacity(assignment.lecturer_id) && (
                                        <p
                                            className="mt-[5px] mb-0 flex items-center gap-1 text-[12px]"
                                            style={{
                                                color: 'var(--ind-accent-800)',
                                            }}
                                        >
                                            <TriangleAlert
                                                size={13}
                                                strokeWidth={1.5}
                                            />
                                            Sudah mencapai kuota maksimal
                                            bimbingan.
                                        </p>
                                    )}
                                </div>

                                <div className="min-w-[160px] flex-1">
                                    <Field
                                        label={
                                            index === 0 ? 'Peran' : undefined
                                        }
                                    >
                                        <select
                                            className="ind-input"
                                            value={assignment.role}
                                            onChange={(event) =>
                                                setRow(index, {
                                                    role: event.target.value,
                                                })
                                            }
                                        >
                                            {roles.map((role) => (
                                                <option
                                                    key={role.value}
                                                    value={role.value}
                                                >
                                                    {role.label}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>
                                </div>

                                <button
                                    type="button"
                                    className="ind-btn ind-btn-ghost ind-btn-icon"
                                    aria-label="Hapus dosen dari daftar"
                                    onClick={() =>
                                        form.setData(
                                            'assignments',
                                            form.data.assignments.filter(
                                                (_, i) => i !== index,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 size={16} strokeWidth={1.5} />
                                </button>
                            </div>
                        ))}
                    </div>

                    <div className="flex flex-wrap gap-[var(--ind-space-3)]">
                        <Btn
                            type="button"
                            onClick={() =>
                                form.setData('assignments', [
                                    ...form.data.assignments,
                                    { lecturer_id: null, role: 'supervisor' },
                                ])
                            }
                        >
                            Tambah Dosen
                        </Btn>
                        <Btn
                            type="submit"
                            variant="primary"
                            disabled={form.processing}
                        >
                            {form.processing ? 'Menyimpan...' : 'Simpan'}
                        </Btn>
                    </div>
                </form>
            </div>
        </>
    );
}
