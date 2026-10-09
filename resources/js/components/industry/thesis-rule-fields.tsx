import { Field, Input } from '@/components/industry/ui';
import type { SelectOption, ThesisRuleValues } from '@/types';

/** The rules as a form holds them: numbers are text until they are saved. */
export type ThesisRuleForm = {
    requires_proposal_exam: boolean;
    min_proposal_guidance_sessions: string;
    min_research_guidance_sessions: string;
    min_logbook_progress: string;
    max_supervisors_per_student: string;
    guidance_slot_minutes: string;
    perception_alignment_mode: string;
};

export function thesisRuleForm(values: ThesisRuleValues): ThesisRuleForm {
    return {
        requires_proposal_exam: values.requires_proposal_exam,
        min_proposal_guidance_sessions: String(
            values.min_proposal_guidance_sessions,
        ),
        min_research_guidance_sessions: String(
            values.min_research_guidance_sessions,
        ),
        min_logbook_progress: String(values.min_logbook_progress),
        max_supervisors_per_student: String(values.max_supervisors_per_student),
        guidance_slot_minutes: String(values.guidance_slot_minutes),
        perception_alignment_mode: values.perception_alignment_mode,
    };
}

/**
 * The tugas akhir rules of a program studi. The Admin Prodi fills these in for
 * its own prodi and the superadmin fills in the same ones as the default, so
 * both screens are this one set of fields.
 */
export function ThesisRuleFields({
    data,
    errors,
    setData,
    alignmentModes,
}: {
    data: ThesisRuleForm;
    errors: Partial<Record<keyof ThesisRuleForm, string>>;
    setData: <K extends keyof ThesisRuleForm>(
        key: K,
        value: ThesisRuleForm[K],
    ) => void;
    alignmentModes: SelectOption[];
}) {
    return (
        <>
            <div className="flex flex-col gap-[var(--ind-space-2)]">
                <label className="ind-check">
                    <input
                        type="checkbox"
                        checked={data.requires_proposal_exam}
                        onChange={(event) =>
                            setData(
                                'requires_proposal_exam',
                                event.target.checked,
                            )
                        }
                    />
                    Aktifkan Ujian Proposal
                </label>

                <p className="ind-muted m-0 text-[12.5px]">
                    {data.requires_proposal_exam
                        ? 'Mahasiswa harus lulus ujian proposal sebelum masuk tahap penelitian.'
                        : 'Tanpa ujian proposal, tahap penelitian terbuka setelah kuota bimbingan proposal terpenuhi.'}
                </p>
            </div>

            <div className="grid [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))] gap-[var(--ind-space-4)]">
                <Field
                    label="Jumlah Minimum Bimbingan Proposal"
                    error={errors.min_proposal_guidance_sessions}
                >
                    <Input
                        type="number"
                        min={0}
                        value={data.min_proposal_guidance_sessions}
                        onChange={(event) =>
                            setData(
                                'min_proposal_guidance_sessions',
                                event.target.value,
                            )
                        }
                    />
                </Field>

                <Field
                    label="Jumlah Minimum Bimbingan Hasil"
                    error={errors.min_research_guidance_sessions}
                >
                    <Input
                        type="number"
                        min={0}
                        value={data.min_research_guidance_sessions}
                        onChange={(event) =>
                            setData(
                                'min_research_guidance_sessions',
                                event.target.value,
                            )
                        }
                    />
                </Field>

                <Field
                    label="Jumlah Pembimbing"
                    hint="Batas dosen pembimbing per mahasiswa."
                    error={errors.max_supervisors_per_student}
                >
                    <Input
                        type="number"
                        min={1}
                        max={5}
                        value={data.max_supervisors_per_student}
                        onChange={(event) =>
                            setData(
                                'max_supervisors_per_student',
                                event.target.value,
                            )
                        }
                    />
                </Field>

                <Field
                    label="Progres Logbook Minimum (%)"
                    error={errors.min_logbook_progress}
                >
                    <Input
                        type="number"
                        min={0}
                        max={100}
                        value={data.min_logbook_progress}
                        onChange={(event) =>
                            setData('min_logbook_progress', event.target.value)
                        }
                    />
                </Field>

                <Field
                    label="Durasi Slot Bimbingan (menit)"
                    hint="Jarak minimum antar sesi tatap muka seorang dosen."
                    error={errors.guidance_slot_minutes}
                >
                    <Input
                        type="number"
                        min={15}
                        max={240}
                        value={data.guidance_slot_minutes}
                        onChange={(event) =>
                            setData('guidance_slot_minutes', event.target.value)
                        }
                    />
                </Field>
            </div>

            <Field
                label="Pihak Kedua pada Persamaan Persepsi"
                error={errors.perception_alignment_mode}
            >
                <select
                    className="ind-input"
                    value={data.perception_alignment_mode}
                    onChange={(event) =>
                        setData('perception_alignment_mode', event.target.value)
                    }
                >
                    {alignmentModes.map((mode) => (
                        <option key={mode.value} value={mode.value}>
                            {mode.label}
                        </option>
                    ))}
                </select>
            </Field>
        </>
    );
}
