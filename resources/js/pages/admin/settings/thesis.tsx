import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import { Btn, Field, Input, PageHeader } from '@/components/industry/ui';
import { update } from '@/routes/admin/thesis-settings';

type Props = {
    department: string;
    setting: {
        requires_proposal_exam: boolean;
        min_proposal_guidance_sessions: number;
        min_research_guidance_sessions: number;
        min_logbook_progress: number;
        max_supervisors_per_student: number;
        guidance_slot_minutes: number;
        perception_alignment_mode: string;
    };
    alignmentModes: { value: string; label: string }[];
};

export default function AdminThesisSettings({
    department,
    setting,
    alignmentModes,
}: Props) {
    setLayoutProps({ title: 'Pengaturan Tugas Akhir' });

    const form = useForm({
        requires_proposal_exam: setting.requires_proposal_exam,
        min_proposal_guidance_sessions: String(
            setting.min_proposal_guidance_sessions,
        ),
        min_research_guidance_sessions: String(
            setting.min_research_guidance_sessions,
        ),
        min_logbook_progress: String(setting.min_logbook_progress),
        max_supervisors_per_student: String(
            setting.max_supervisors_per_student,
        ),
        guidance_slot_minutes: String(setting.guidance_slot_minutes),
        perception_alignment_mode: setting.perception_alignment_mode,
    });

    return (
        <>
            <Head title="Pengaturan Tugas Akhir" />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(update().url, { preserveScroll: true });
                }}
                className="ind-page max-w-[640px]"
            >
                <PageHeader
                    title="Pengaturan Tugas Akhir"
                    description={`Konfigurasi ini hanya berlaku untuk ${department}.`}
                />

                <div className="flex flex-col gap-[var(--ind-space-2)]">
                    <label className="ind-check">
                        <input
                            type="checkbox"
                            checked={form.data.requires_proposal_exam}
                            onChange={(event) =>
                                form.setData(
                                    'requires_proposal_exam',
                                    event.target.checked,
                                )
                            }
                        />
                        Aktifkan Ujian Proposal
                    </label>

                    <p className="ind-muted m-0 text-[12.5px]">
                        {form.data.requires_proposal_exam
                            ? 'Mahasiswa harus lulus ujian proposal sebelum masuk tahap penelitian.'
                            : 'Tanpa ujian proposal, tahap penelitian terbuka setelah kuota bimbingan proposal terpenuhi.'}
                    </p>
                </div>

                <div className="grid [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))] gap-[var(--ind-space-4)]">
                    <Field
                        label="Jumlah Minimum Bimbingan Proposal"
                        error={form.errors.min_proposal_guidance_sessions}
                    >
                        <Input
                            type="number"
                            min={0}
                            value={form.data.min_proposal_guidance_sessions}
                            onChange={(event) =>
                                form.setData(
                                    'min_proposal_guidance_sessions',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>

                    <Field
                        label="Jumlah Minimum Bimbingan Hasil"
                        error={form.errors.min_research_guidance_sessions}
                    >
                        <Input
                            type="number"
                            min={0}
                            value={form.data.min_research_guidance_sessions}
                            onChange={(event) =>
                                form.setData(
                                    'min_research_guidance_sessions',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>

                    <Field
                        label="Jumlah Pembimbing"
                        hint="Batas dosen pembimbing per mahasiswa."
                        error={form.errors.max_supervisors_per_student}
                    >
                        <Input
                            type="number"
                            min={1}
                            max={5}
                            value={form.data.max_supervisors_per_student}
                            onChange={(event) =>
                                form.setData(
                                    'max_supervisors_per_student',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>

                    <Field
                        label="Progres Logbook Minimum (%)"
                        error={form.errors.min_logbook_progress}
                    >
                        <Input
                            type="number"
                            min={0}
                            max={100}
                            value={form.data.min_logbook_progress}
                            onChange={(event) =>
                                form.setData(
                                    'min_logbook_progress',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>

                    <Field
                        label="Durasi Slot Bimbingan (menit)"
                        hint="Jarak minimum antar sesi tatap muka seorang dosen."
                        error={form.errors.guidance_slot_minutes}
                    >
                        <Input
                            type="number"
                            min={15}
                            max={240}
                            value={form.data.guidance_slot_minutes}
                            onChange={(event) =>
                                form.setData(
                                    'guidance_slot_minutes',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                </div>

                <Field
                    label="Pihak Kedua pada Persamaan Persepsi"
                    error={form.errors.perception_alignment_mode}
                >
                    <select
                        className="ind-input"
                        value={form.data.perception_alignment_mode}
                        onChange={(event) =>
                            form.setData(
                                'perception_alignment_mode',
                                event.target.value,
                            )
                        }
                    >
                        {alignmentModes.map((mode) => (
                            <option key={mode.value} value={mode.value}>
                                {mode.label}
                            </option>
                        ))}
                    </select>
                </Field>

                <Btn
                    type="submit"
                    variant="primary"
                    disabled={form.processing}
                    className="self-start"
                >
                    {form.processing ? 'Menyimpan...' : 'Simpan Pengaturan'}
                </Btn>
            </form>
        </>
    );
}
