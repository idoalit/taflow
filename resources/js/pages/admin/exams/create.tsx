import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { SearchSelect } from '@/components/industry/search-select';
import {
    Btn,
    Field,
    Input,
    SectionHeading,
    Textarea,
} from '@/components/industry/ui';
import { index as adminExams, store } from '@/routes/admin/exams';
import type { ExamStage } from '@/types';

type Props = {
    stage: ExamStage;
    stage_label: string;
    request: {
        id: number;
        student: string;
        student_number: string;
        supervisors: string[];
        title: string;
    };
    lecturers: { id: number; name: string }[];
    rubrics: { id: number; name: string }[];
    defaultRubricId: number | null;
    roles: { value: string; label: string }[];
    minExaminers: number;
    allowsSupervisorAsExaminer: boolean;
};

export default function AdminExamCreate({
    stage,
    stage_label,
    request,
    lecturers,
    rubrics,
    defaultRubricId,
    roles,
    minExaminers,
    allowsSupervisorAsExaminer,
}: Props) {
    setLayoutProps({ title: `Buat Jadwal ${stage_label}` });

    const form = useForm({
        title: `${stage_label} — ${request.student}`,
        scheduled_at: '',
        duration_minutes: '90',
        venue: '',
        preparation_notes: '',
        rubric_id: defaultRubricId,
        panel: [
            { lecturer_id: null as number | null, role: 'ketua_penguji' },
            { lecturer_id: null as number | null, role: 'anggota_penguji' },
        ],
    });

    const examinerCount = form.data.panel.filter((seat) =>
        ['ketua_penguji', 'anggota_penguji'].includes(seat.role),
    ).length;
    const chairCount = form.data.panel.filter(
        (seat) => seat.role === 'ketua_penguji',
    ).length;

    const setRow = (
        index: number,
        patch: Partial<(typeof form.data.panel)[number]>,
    ) =>
        form.setData(
            'panel',
            form.data.panel.map((row, i) =>
                i === index ? { ...row, ...patch } : row,
            ),
        );

    return (
        <>
            <Head title={`Buat Jadwal ${stage_label}`} />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(store({ stage, examRequest: request.id }).url);
                }}
                className="ind-page max-w-[720px]"
            >
                <div>
                    <Link
                        href={adminExams({ query: { stage } })}
                        className="text-[12.5px]"
                    >
                        ← Pelaksanaan Ujian
                    </Link>
                    <h3 style={{ margin: '10px 0 4px' }}>
                        Buat Jadwal {stage_label}
                    </h3>
                    <p className="ind-muted m-0">
                        Isi informasi ujian, rubrik penilaian, dan panel
                        penguji.
                    </p>
                </div>

                <div className="grid [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))] gap-[var(--ind-space-4)]">
                    <Field label="Mahasiswa">
                        <Input
                            value={`${request.student} · ${request.student_number}`}
                            readOnly
                        />
                    </Field>
                    <Field label="Dosen Pembimbing">
                        <Input
                            value={
                                request.supervisors.join(', ') ||
                                'Belum ditentukan'
                            }
                            readOnly
                        />
                    </Field>
                </div>

                <Field label="Judul Ujian" error={form.errors.title}>
                    <Input
                        value={form.data.title}
                        onChange={(event) =>
                            form.setData('title', event.target.value)
                        }
                    />
                </Field>

                <div className="grid [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))] gap-[var(--ind-space-4)]">
                    <Field
                        label="Tanggal & Waktu"
                        error={form.errors.scheduled_at}
                    >
                        <Input
                            type="datetime-local"
                            value={form.data.scheduled_at}
                            onChange={(event) =>
                                form.setData('scheduled_at', event.target.value)
                            }
                        />
                    </Field>

                    <Field
                        label="Durasi (menit)"
                        error={form.errors.duration_minutes}
                    >
                        <Input
                            type="number"
                            min={15}
                            max={300}
                            value={form.data.duration_minutes}
                            onChange={(event) =>
                                form.setData(
                                    'duration_minutes',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>

                    <Field label="Tempat" optional error={form.errors.venue}>
                        <Input
                            value={form.data.venue}
                            onChange={(event) =>
                                form.setData('venue', event.target.value)
                            }
                            placeholder="Ruang Seminar A, Gedung Teknik Lt. 2"
                        />
                    </Field>
                </div>

                <Field
                    label="Rubrik Ujian"
                    hint={
                        rubrics.length === 0
                            ? 'Belum ada rubrik terbit. Ujian dapat dijadwalkan, tetapi penilaian belum dapat diisi.'
                            : 'Rubrik dikunci pada jadwal ini, sehingga perubahan rubrik prodi kemudian hari tidak mengubah ujian ini.'
                    }
                    error={form.errors.rubric_id}
                >
                    <SearchSelect
                        value={form.data.rubric_id}
                        options={rubrics.map((rubric) => ({
                            value: rubric.id,
                            label: rubric.name,
                        }))}
                        onChange={(value) => form.setData('rubric_id', value)}
                        placeholder="Pilih rubrik"
                        emptyLabel="Belum ada rubrik yang diterbitkan."
                    />
                </Field>

                <div>
                    <SectionHeading>Panel Penguji</SectionHeading>
                    <p className="ind-muted mt-0 mb-[var(--ind-space-3)] text-[12px]">
                        Tepat satu Ketua Penguji, dan sedikitnya {minExaminers}{' '}
                        penguji. Saat ini {chairCount} ketua dan {examinerCount}{' '}
                        penguji dipilih.
                        {allowsSupervisorAsExaminer
                            ? ' Pembimbing boleh merangkap sebagai penguji.'
                            : ' Program studi ini melarang pembimbing menguji mahasiswa bimbingannya sendiri.'}
                    </p>
                    {form.errors.panel && (
                        <p
                            className="mt-0 mb-[var(--ind-space-2)] text-[12.5px]"
                            style={{ color: 'var(--ind-status-error-fg)' }}
                        >
                            {form.errors.panel}
                        </p>
                    )}

                    <div className="flex flex-col gap-[var(--ind-space-3)]">
                        {form.data.panel.map((member, index) => (
                            // Rows are positional while the panel is being built.
                            <div
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
                                            value={member.lecturer_id}
                                            options={lecturers.map(
                                                (lecturer) => ({
                                                    value: lecturer.id,
                                                    label: lecturer.name,
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
                                </div>

                                <div className="min-w-[160px] flex-1">
                                    <Field
                                        label={
                                            index === 0 ? 'Peran' : undefined
                                        }
                                    >
                                        <select
                                            className="ind-input"
                                            value={member.role}
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
                                    aria-label="Hapus penguji"
                                    onClick={() =>
                                        form.setData(
                                            'panel',
                                            form.data.panel.filter(
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

                    <Btn
                        type="button"
                        className="mt-[var(--ind-space-3)]"
                        onClick={() =>
                            form.setData('panel', [
                                ...form.data.panel,
                                { lecturer_id: null, role: 'anggota_penguji' },
                            ])
                        }
                    >
                        Tambah Penguji
                    </Btn>
                </div>

                <Field
                    label="Catatan Persiapan"
                    optional
                    error={form.errors.preparation_notes}
                >
                    <Textarea
                        rows={2}
                        value={form.data.preparation_notes}
                        onChange={(event) =>
                            form.setData(
                                'preparation_notes',
                                event.target.value,
                            )
                        }
                        placeholder="Yang perlu disiapkan mahasiswa sebelum ujian..."
                    />
                </Field>

                <div className="flex gap-[var(--ind-space-3)]">
                    <Btn
                        type="submit"
                        variant="primary"
                        disabled={form.processing}
                    >
                        {form.processing ? 'Menyimpan...' : 'Simpan Jadwal'}
                    </Btn>
                    <Link
                        href={adminExams({ query: { stage } })}
                        className="ind-btn ind-btn-secondary"
                    >
                        Batal
                    </Link>
                </div>
            </form>
        </>
    );
}
