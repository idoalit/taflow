import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import {
    Btn,
    Field,
    Input,
    SectionHeading,
    Textarea,
} from '@/components/industry/ui';
import {
    BLANK_BLOCK,
    RubricBlockEditor,
    type Block,
} from '@/components/industry/rubric-block-editor';
import { index, store, update } from '@/routes/admin/rubrics';

type Row = { label: string; weight: string; max_score: string };

type Draft = {
    id: number;
    name: string;
    description: string | null;
    scoring_scheme: string;
    criteria: { label: string; weight: number; max_score: number }[];
    blocks: {
        label: string;
        subblocks: {
            label: string;
            items: { label: string; max_score: number }[];
        }[];
    }[];
};

type Scheme = { value: string; label: string; description: string };

const BLANK: Row = { label: '', weight: '', max_score: '100' };

export default function AdminRubricForm({
    department,
    rubric,
    schemes,
}: {
    department: string;
    rubric: Draft | null;
    schemes: Scheme[];
}) {
    const editing = rubric !== null;

    setLayoutProps({ title: editing ? 'Ubah Rubrik' : 'Rubrik Baru' });

    const form = useForm({
        name: rubric?.name ?? '',
        description: rubric?.description ?? '',
        scoring_scheme: rubric?.scoring_scheme ?? 'weighted',
        criteria: (rubric?.criteria.map((criterion) => ({
            label: criterion.label,
            weight: String(criterion.weight),
            max_score: String(criterion.max_score),
        })) ?? [{ ...BLANK }, { ...BLANK }]) as Row[],
        blocks: (rubric?.blocks.map((block) => ({
            label: block.label,
            subblocks: block.subblocks.map((sub) => ({
                label: sub.label,
                items: sub.items.map((item) => ({
                    label: item.label,
                    max_score: String(item.max_score),
                })),
            })),
        })) ?? [JSON.parse(JSON.stringify(BLANK_BLOCK)) as Block]) as Block[],
    });

    const isBlockScheme = form.data.scoring_scheme === 'block_additive';
    const blockTotal = form.data.blocks.reduce(
        (sum, block) =>
            sum +
            block.subblocks.reduce(
                (s, sub) =>
                    s +
                    sub.items.reduce(
                        (i, item) => i + (Number(item.max_score) || 0),
                        0,
                    ),
                0,
            ),
        0,
    );

    const total = form.data.criteria.reduce(
        (sum, row) => sum + (Number(row.weight) || 0),
        0,
    );

    const setRow = (index: number, patch: Partial<Row>) =>
        form.setData(
            'criteria',
            form.data.criteria.map((row, i) =>
                i === index ? { ...row, ...patch } : row,
            ),
        );

    return (
        <>
            <Head title="Rubrik Baru" />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    if (editing) {
                        form.put(update(rubric.id).url);
                    } else {
                        form.post(store().url);
                    }
                }}
                className="ind-page max-w-[720px]"
            >
                <div>
                    <Link href={index()} className="text-[12.5px]">
                        ← Daftar Rubrik
                    </Link>
                    <h3 style={{ margin: '10px 0 4px' }}>
                        {editing ? 'Ubah Rubrik' : 'Rubrik Baru'}
                    </h3>
                    <p className="ind-muted m-0">
                        Rubrik untuk {department}. Tersimpan sebagai draft dan
                        dapat diterbitkan setelah bobotnya genap 100%.
                    </p>
                </div>

                <Field label="Nama Rubrik" error={form.errors.name}>
                    <Input
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        placeholder="Rubrik Ujian Proposal — Genap 2025/2026"
                    />
                </Field>

                <Field
                    label="Deskripsi"
                    optional
                    error={form.errors.description}
                >
                    <Textarea
                        rows={2}
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData('description', event.target.value)
                        }
                        placeholder="Keterangan peruntukan rubrik ini"
                    />
                </Field>

                <Field
                    label="Skema Penilaian"
                    hint={
                        schemes.find(
                            (scheme) =>
                                scheme.value === form.data.scoring_scheme,
                        )?.description
                    }
                    error={form.errors.scoring_scheme}
                >
                    <select
                        className="ind-input"
                        value={form.data.scoring_scheme}
                        onChange={(event) =>
                            form.setData('scoring_scheme', event.target.value)
                        }
                    >
                        {schemes.map((scheme) => (
                            <option key={scheme.value} value={scheme.value}>
                                {scheme.label}
                            </option>
                        ))}
                    </select>
                </Field>

                <div>
                    <div className="mb-[2px] flex items-baseline justify-between">
                        <SectionHeading>Kriteria Penilaian</SectionHeading>
                        <span
                            className="text-[12px]"
                            style={{
                                // An untouched form is not yet wrong; only a
                                // started-but-unbalanced one is.
                                color: (
                                    isBlockScheme
                                        ? blockTotal === 0 || blockTotal === 100
                                        : total === 0 || total === 100
                                )
                                    ? 'color-mix(in srgb, var(--ind-text) 55%, transparent)'
                                    : 'var(--ind-status-error-fg)',
                            }}
                        >
                            {isBlockScheme
                                ? `Total skor maksimal: ${blockTotal} dari 100`
                                : `Total bobot: ${total}%`}
                        </span>
                    </div>

                    <p className="ind-muted mt-0 mb-[var(--ind-space-3)] text-[12px]">
                        {isBlockScheme
                            ? 'Skor maksimal seluruh item harus berjumlah 100; nilai penguji adalah jumlah skornya. Kode blok (A, B, C) dan sub-blok (I, II, III) dibuat otomatis dari urutannya — gunakan tombol panah untuk mengurutkan.'
                            : 'Tiap kriteria punya skala nilainya sendiri — boleh 1–5, 1–10, atau 1–100. Nilai akhir dihitung sebagai persentase terbobot, jadi skala yang berbeda tetap sebanding.'}
                    </p>

                    {form.errors.criteria && (
                        <p
                            className="mt-0 mb-[var(--ind-space-2)] text-[12.5px]"
                            style={{ color: 'var(--ind-status-error-fg)' }}
                        >
                            {form.errors.criteria}
                        </p>
                    )}

                    {isBlockScheme ? (
                        <RubricBlockEditor
                            blocks={form.data.blocks}
                            onChange={(blocks) =>
                                form.setData('blocks', blocks)
                            }
                            errors={form.errors as Record<string, string>}
                        />
                    ) : (
                        <>
                            <div className="flex flex-col gap-[var(--ind-space-3)]">
                                {form.data.criteria.map((row, index) => (
                                    // Rows are positional; labels are still being typed.
                                    <div
                                        key={index}
                                        className="flex flex-wrap items-end gap-[var(--ind-space-2)]"
                                    >
                                        <div className="min-w-[220px] flex-[2]">
                                            <Field
                                                label={
                                                    index === 0
                                                        ? 'Kriteria'
                                                        : undefined
                                                }
                                                error={
                                                    form.errors[
                                                        `criteria.${index}.label`
                                                    ]
                                                }
                                            >
                                                <Input
                                                    value={row.label}
                                                    onChange={(event) =>
                                                        setRow(index, {
                                                            label: event.target
                                                                .value,
                                                        })
                                                    }
                                                    placeholder="Penguasaan Materi"
                                                />
                                            </Field>
                                        </div>

                                        <div className="min-w-[100px] flex-[0.7]">
                                            <Field
                                                label={
                                                    index === 0
                                                        ? 'Bobot %'
                                                        : undefined
                                                }
                                                error={
                                                    form.errors[
                                                        `criteria.${index}.weight`
                                                    ]
                                                }
                                            >
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    max={100}
                                                    value={row.weight}
                                                    onChange={(event) =>
                                                        setRow(index, {
                                                            weight: event.target
                                                                .value,
                                                        })
                                                    }
                                                />
                                            </Field>
                                        </div>

                                        <div className="min-w-[110px] flex-[0.7]">
                                            <Field
                                                label={
                                                    index === 0
                                                        ? 'Nilai Maks'
                                                        : undefined
                                                }
                                                error={
                                                    form.errors[
                                                        `criteria.${index}.max_score`
                                                    ]
                                                }
                                            >
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    max={100}
                                                    value={row.max_score}
                                                    onChange={(event) =>
                                                        setRow(index, {
                                                            max_score:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                />
                                            </Field>
                                        </div>

                                        <button
                                            type="button"
                                            className="ind-btn ind-btn-ghost ind-btn-icon"
                                            aria-label="Hapus kriteria"
                                            onClick={() =>
                                                form.setData(
                                                    'criteria',
                                                    form.data.criteria.filter(
                                                        (_, i) => i !== index,
                                                    ),
                                                )
                                            }
                                        >
                                            <Trash2
                                                size={16}
                                                strokeWidth={1.5}
                                            />
                                        </button>
                                    </div>
                                ))}
                            </div>

                            <Btn
                                type="button"
                                className="mt-[var(--ind-space-3)]"
                                onClick={() =>
                                    form.setData('criteria', [
                                        ...form.data.criteria,
                                        { ...BLANK },
                                    ])
                                }
                            >
                                Tambah Kriteria
                            </Btn>
                        </>
                    )}
                </div>

                <div className="flex gap-[var(--ind-space-3)]">
                    <Btn
                        type="submit"
                        variant="primary"
                        disabled={form.processing}
                    >
                        {form.processing ? 'Menyimpan...' : 'Simpan'}
                    </Btn>
                    <Link href={index()} className="ind-btn ind-btn-secondary">
                        Batal
                    </Link>
                </div>
            </form>
        </>
    );
}
