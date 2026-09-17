import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import {
    Btn,
    BtnLink,
    Card,
    CardKicker,
    Field,
    Input,
    Textarea,
} from '@/components/industry/ui';
import { store as storeEvaluation } from '@/routes/exams/evaluations';
import type { ExamRubric, ThesisExamDetail } from '@/types';

type Props = {
    exam: ThesisExamDetail;
    rubric: ExamRubric;
    results: { value: string; label: string }[];
};

export default function ExamEvaluate({ exam, rubric, results }: Props) {
    setLayoutProps({ title: 'Isi Evaluasi Ujian' });

    const { data, setData, post, processing, errors } = useForm({
        scores: Object.fromEntries(
            rubric.criteria.map((criterion) => [criterion.key, '']),
        ) as Record<string, string>,
        result: results[0]?.value ?? 'passed',
        feedback: '',
        revision_notes: '',
    });

    const mark = (key: string) => Number(data.scores[key]) || 0;

    const totalWeight = rubric.criteria.reduce(
        (sum, criterion) => sum + (criterion.weight ?? 0),
        0,
    );

    // The same arithmetic the server applies, shown live so the panel member
    // can see where a mark lands before committing to it. Mirrors
    // ExamEvaluation::scoreFor, which branches on the same two schemes: a block
    // rubric's maxima already add up to 100, a weighted one's do not.
    const score = rubric.block_additive
        ? rubric.criteria.reduce(
              (sum, criterion) => sum + mark(criterion.key),
              0,
          )
        : totalWeight === 0
          ? 0
          : (rubric.criteria.reduce(
                (sum, criterion) =>
                    sum +
                    (criterion.max_score > 0
                        ? (mark(criterion.key) / criterion.max_score) *
                          (criterion.weight ?? 0)
                        : 0),
                0,
            ) /
                totalWeight) *
            100;

    const allMarked = rubric.criteria.every(
        (criterion) => data.scores[criterion.key] !== '',
    );
    const belowThreshold =
        rubric.pass_threshold !== null && score < rubric.pass_threshold;

    const field = (criterion: (typeof rubric.criteria)[number]) => (
        <Field
            key={criterion.key}
            label={
                rubric.block_additive
                    ? `${criterion.item_number ?? ''}. ${criterion.label}`
                    : `${criterion.label} — bobot ${criterion.weight ?? 0}%`
            }
            hint={`Nilai maksimal ${criterion.max_score}`}
            error={errors[`scores.${criterion.key}`]}
        >
            <Input
                type="number"
                min={0}
                max={criterion.max_score}
                step="0.01"
                value={data.scores[criterion.key]}
                onChange={(event) =>
                    setData('scores', {
                        ...data.scores,
                        [criterion.key]: event.target.value,
                    })
                }
            />
        </Field>
    );

    return (
        <>
            <Head title={`Evaluasi — ${exam.student}`} />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    post(
                        storeEvaluation({
                            stage: exam.stage,
                            exam: exam.id,
                        }).url,
                    );
                }}
                className="flex max-w-[720px] flex-col gap-[var(--ind-space-6)]"
            >
                <div>
                    <h3 className="mb-1">Isi Evaluasi — {exam.student}</h3>
                    <p className="ind-muted m-0">
                        {exam.title}. Evaluasi tidak dapat diubah setelah
                        disimpan.
                    </p>
                </div>

                <Card>
                    <CardKicker>Rubrik Penilaian</CardKicker>
                    <div className="ind-card-title">{rubric.name}</div>
                    {rubric.description && (
                        <p className="ind-card-body m-0">
                            {rubric.description}
                        </p>
                    )}
                    <div className="ind-card-meta">
                        {rubric.block_additive
                            ? 'Setiap butir dinilai pada skalanya sendiri dan seluruhnya dijumlahkan menjadi nilai akhir dari 100.'
                            : 'Tiap kriteria dinilai pada skalanya sendiri, lalu dihitung sebagai persentase terbobot.'}{' '}
                        {rubric.pass_threshold === null
                            ? 'Program studi belum menetapkan ambang kelulusan.'
                            : `Ambang kelulusan: ${rubric.pass_threshold}.`}
                    </div>
                </Card>

                {rubric.block_additive ? (
                    <div className="flex flex-col gap-[var(--ind-space-8)]">
                        {rubric.blocks.map((block) => {
                            const earned = block.subblocks.reduce(
                                (sum, sub) =>
                                    sum +
                                    sub.items.reduce(
                                        (inner, item) => inner + mark(item.key),
                                        0,
                                    ),
                                0,
                            );

                            return (
                                <div
                                    key={block.code ?? block.label}
                                    className="ind-section"
                                >
                                    <div className="flex flex-wrap items-baseline gap-[var(--ind-space-3)]">
                                        <span className="ind-rubric-code">
                                            {block.code}
                                        </span>
                                        <h6 className="m-0 flex-1">
                                            {block.label}
                                        </h6>
                                        <span className="ind-muted text-[12.5px]">
                                            {earned} / {block.max}
                                        </span>
                                    </div>

                                    {block.subblocks.map((sub) => (
                                        <div
                                            key={sub.code ?? sub.label ?? 'x'}
                                            className="flex flex-col gap-[var(--ind-space-4)]"
                                        >
                                            {sub.label && (
                                                <div className="flex items-baseline gap-[var(--ind-space-3)]">
                                                    <span className="ind-rubric-code ind-rubric-code-sub">
                                                        {sub.code}
                                                    </span>
                                                    <span className="text-[13px] font-semibold">
                                                        {sub.label}
                                                    </span>
                                                    <span className="ind-muted text-[12px]">
                                                        maks. {sub.max}
                                                    </span>
                                                </div>
                                            )}

                                            {sub.items.map(field)}
                                        </div>
                                    ))}
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <div className="flex flex-col gap-[var(--ind-space-4)]">
                        {rubric.criteria.map(field)}
                    </div>
                )}

                <Card>
                    <CardKicker>
                        {rubric.block_additive
                            ? 'Jumlah Nilai'
                            : 'Nilai Terbobot'}
                    </CardKicker>
                    <div
                        className="ind-card-title"
                        style={{
                            color: belowThreshold
                                ? 'var(--ind-accent-900)'
                                : undefined,
                        }}
                    >
                        {allMarked
                            ? rubric.block_additive
                                ? `${score.toFixed(2)} / 100`
                                : `${score.toFixed(2)}%`
                            : '—'}
                    </div>
                    <div className="ind-card-meta">
                        {!allMarked
                            ? 'Isi seluruh kriteria untuk melihat nilai akhir.'
                            : belowThreshold
                              ? `Di bawah ambang kelulusan ${rubric.pass_threshold}. Hasil ujian akan tercatat Tidak Lulus meskipun panel memberi nilai lulus.`
                              : 'Memenuhi ambang kelulusan program studi.'}
                    </div>
                </Card>

                <Field label="Umpan Balik" optional error={errors.feedback}>
                    <Textarea
                        rows={3}
                        value={data.feedback}
                        onChange={(event) =>
                            setData('feedback', event.target.value)
                        }
                        placeholder="Kelebihan, kelemahan, dan saran perbaikan..."
                    />
                </Field>

                <Field
                    label="Daftar Revisi"
                    optional
                    error={errors.revision_notes}
                >
                    <Textarea
                        rows={2}
                        value={data.revision_notes}
                        onChange={(event) =>
                            setData('revision_notes', event.target.value)
                        }
                        placeholder="Poin revisi bila hasil Lulus Bersyarat"
                    />
                </Field>

                <Field label="Hasil Akhir" error={errors.result}>
                    <select
                        className="ind-input"
                        value={data.result}
                        onChange={(event) =>
                            setData('result', event.target.value)
                        }
                    >
                        {results.map((result) => (
                            <option key={result.value} value={result.value}>
                                {result.label}
                            </option>
                        ))}
                    </select>
                </Field>

                <div className="flex gap-[var(--ind-space-3)]">
                    <Btn type="submit" variant="primary" disabled={processing}>
                        {processing ? 'Menyimpan...' : 'Simpan Evaluasi'}
                    </Btn>
                    <BtnLink href={exam.show_url}>Batal</BtnLink>
                </div>
            </form>
        </>
    );
}
