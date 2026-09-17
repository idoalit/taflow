import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import {
    Btn,
    Card,
    CardKicker,
    Field,
    Input,
    Tag,
    Textarea,
} from '@/components/industry/ui';
import { store } from '@/routes/exams/supervision';
import type { ExamStage } from '@/types';

type Indicator = {
    id: number;
    code: string;
    label: string;
    max_score: number;
};

type Props = {
    exam: {
        id: number;
        stage: ExamStage;
        title: string;
        student: string;
        student_number: string;
        show_url: string;
    };
    indicators: Indicator[];
    total_max: number;
};

/** Mirrors SupervisionIndicator::descriptorFor. */
function descriptor(score: number, max: number): string {
    if (max <= 0) {
        return '—';
    }

    const ratio = score / max;

    return ratio >= 0.7 ? 'Baik' : ratio >= 0.35 ? 'Cukup' : 'Kurang';
}

export default function SupervisionScoreForm({
    exam,
    indicators,
    total_max,
}: Props) {
    setLayoutProps({ title: 'Penilaian Proses Bimbingan' });

    const { data, setData, post, processing, errors } = useForm({
        scores: Object.fromEntries(
            indicators.map((indicator) => [indicator.code, '']),
        ) as Record<string, string>,
        notes: '',
    });

    const total = indicators.reduce(
        (sum, indicator) => sum + (Number(data.scores[indicator.code]) || 0),
        0,
    );
    const allMarked = indicators.every(
        (indicator) => data.scores[indicator.code] !== '',
    );

    return (
        <>
            <Head title={`Penilaian Bimbingan — ${exam.student}`} />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    post(store({ stage: exam.stage, exam: exam.id }).url);
                }}
                className="ind-page max-w-[760px]"
            >
                <div>
                    <Link href={exam.show_url} className="text-[12.5px]">
                        ← Detail Ujian
                    </Link>
                    <h3 style={{ margin: '10px 0 4px' }}>
                        Penilaian Proses Bimbingan
                    </h3>
                    <p className="ind-muted m-0">
                        {exam.student} · {exam.student_number}. Menilai proses
                        bimbingannya, bukan ujiannya.
                    </p>
                </div>

                <div className="ind-section">
                    {indicators.map((indicator) => {
                        const value = data.scores[indicator.code];
                        const marked = value !== '';

                        return (
                            <div
                                key={indicator.code}
                                className="flex flex-wrap items-end gap-[var(--ind-space-3)]"
                            >
                                <span className="ind-rubric-code ind-rubric-code-item">
                                    {indicator.code}
                                </span>

                                <div className="min-w-[260px] flex-1">
                                    <Field
                                        label={indicator.label}
                                        error={
                                            errors[`scores.${indicator.code}`]
                                        }
                                    >
                                        <Input
                                            type="number"
                                            min={0}
                                            max={indicator.max_score}
                                            step="0.01"
                                            className="max-w-[140px]"
                                            value={value}
                                            onChange={(event) =>
                                                setData('scores', {
                                                    ...data.scores,
                                                    [indicator.code]:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    </Field>
                                </div>

                                <div className="pb-[10px]">
                                    {marked ? (
                                        <Tag
                                            label={descriptor(
                                                Number(value) || 0,
                                                indicator.max_score,
                                            )}
                                        />
                                    ) : (
                                        <span className="ind-muted text-[12px]">
                                            0–{indicator.max_score}
                                        </span>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>

                <Card>
                    <CardKicker>Total Nilai</CardKicker>
                    <div className="ind-stat">
                        {allMarked ? total.toFixed(2) : '—'}
                        <span className="ind-muted ml-2 text-[13px] font-normal">
                            dari {total_max}
                        </span>
                    </div>
                    <div className="ind-card-meta">
                        Keterangan mengikuti nilai tiap indikator: Baik, Cukup,
                        atau Kurang.
                    </div>
                </Card>

                <Field label="Catatan" optional error={errors.notes}>
                    <Textarea
                        rows={3}
                        value={data.notes}
                        onChange={(event) =>
                            setData('notes', event.target.value)
                        }
                        placeholder="Catatan tambahan tentang proses bimbingan..."
                    />
                </Field>

                <div className="flex gap-[var(--ind-space-3)]">
                    <Btn type="submit" variant="primary" disabled={processing}>
                        {processing ? 'Menyimpan...' : 'Simpan Penilaian'}
                    </Btn>
                    <Link
                        href={exam.show_url}
                        className="ind-btn ind-btn-secondary"
                    >
                        Batal
                    </Link>
                </div>
            </form>
        </>
    );
}
