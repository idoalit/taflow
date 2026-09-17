import { Head, router, setLayoutProps, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { SearchSelect } from '@/components/industry/search-select';
import {
    Btn,
    BtnLink,
    Card,
    CardKicker,
    DataTable,
    EmptyRow,
    Field,
    Input,
    PageHeader,
    Tabs,
    Tag,
} from '@/components/industry/ui';
import { assign, create } from '@/routes/admin/rubrics';
import type { AdminRubricRow, ExamStage } from '@/types';

type Assignment = {
    stage: ExamStage;
    stage_label: string;
    rubric_id: number | null;
    rubric_name: string | null;
    pass_threshold: number | null;
    effective_from: string | null;
};

type Props = {
    department: string;
    rubrics: AdminRubricRow[];
    assignments: Assignment[];
    stages: { value: ExamStage; label: string }[];
};

export default function AdminRubricIndex({
    department,
    rubrics,
    assignments,
    stages,
}: Props) {
    setLayoutProps({ title: 'Rubrik Ujian' });

    const [tab, setTab] = useState<'templates' | 'assignments'>('templates');
    const published = rubrics.filter((rubric) => rubric.status === 'published');

    return (
        <>
            <Head title="Rubrik Ujian" />

            <div className="ind-page">
                <PageHeader
                    title="Rubrik Ujian"
                    description={`Buat template rubrik untuk ${department} dan terapkan ke tiap tahap ujian.`}
                    actions={
                        <BtnLink variant="primary" href={create()}>
                            Rubrik Baru
                        </BtnLink>
                    }
                />

                <Tabs
                    label="Tampilan rubrik"
                    value={tab}
                    options={[
                        { value: 'templates', label: 'Template Rubrik' },
                        {
                            value: 'assignments',
                            label: 'Penerapan per Tahap Ujian',
                        },
                    ]}
                    onChange={setTab}
                />

                {tab === 'templates' ? (
                    <DataTable
                        head={
                            <tr>
                                <th>Nama Rubrik</th>
                                <th>Kriteria</th>
                                <th>Skema</th>
                                <th>Kelengkapan</th>
                                <th>Status</th>
                                <th>Terbit</th>
                            </tr>
                        }
                    >
                        {rubrics.length === 0 ? (
                            <EmptyRow colSpan={6}>
                                Belum ada rubrik. Buat rubrik baru untuk mulai
                                menilai ujian.
                            </EmptyRow>
                        ) : (
                            rubrics.map((rubric) => (
                                <tr
                                    key={rubric.id}
                                    className="cursor-pointer"
                                    onClick={() =>
                                        router.visit(rubric.show_url)
                                    }
                                >
                                    <td className="font-semibold">
                                        {rubric.name}
                                        {rubric.description && (
                                            <span className="ind-muted block text-[12px]">
                                                {rubric.description}
                                            </span>
                                        )}
                                    </td>
                                    <td className="ind-muted">
                                        {rubric.criteria_count}
                                    </td>
                                    <td className="ind-muted">
                                        {rubric.scoring_scheme ===
                                        'block_additive'
                                            ? 'Aditif per blok'
                                            : 'Berbobot'}
                                    </td>
                                    <td
                                        style={{
                                            color: rubric.balanced
                                                ? undefined
                                                : 'var(--ind-status-error-fg)',
                                        }}
                                    >
                                        {rubric.total_label}
                                    </td>
                                    <td>
                                        <Tag label={rubric.status_label} />
                                    </td>
                                    <td className="ind-muted">
                                        {rubric.published_at ?? '—'}
                                    </td>
                                </tr>
                            ))
                        )}
                    </DataTable>
                ) : (
                    <div className="grid [grid-template-columns:repeat(auto-fit,minmax(300px,1fr))] gap-[var(--ind-space-4)]">
                        {assignments.map((assignment) => (
                            <AssignmentCard
                                key={assignment.stage}
                                assignment={assignment}
                                published={published}
                                stages={stages}
                            />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function AssignmentCard({
    assignment,
    published,
}: {
    assignment: Assignment;
    published: AdminRubricRow[];
    stages: { value: ExamStage; label: string }[];
}) {
    const form = useForm({
        exam_stage: assignment.stage,
        rubric_id: assignment.rubric_id,
        pass_threshold: String(assignment.pass_threshold ?? ''),
    });

    return (
        <Card>
            <CardKicker>{assignment.stage_label}</CardKicker>

            <div className="ind-card-title">
                {assignment.rubric_name ?? 'Belum diterapkan'}
            </div>
            <div className="ind-card-meta">
                {assignment.rubric_name === null
                    ? 'Dosen belum dapat menilai ujian tahap ini.'
                    : `Ambang kelulusan ${assignment.pass_threshold}% · berlaku sejak ${assignment.effective_from}`}
            </div>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(assign().url, { preserveScroll: true });
                }}
                className="mt-[var(--ind-space-3)] flex flex-col gap-[var(--ind-space-3)]"
            >
                <Field label="Rubrik (Terbit)" error={form.errors.rubric_id}>
                    <SearchSelect
                        value={form.data.rubric_id}
                        options={published.map((rubric) => ({
                            value: rubric.id,
                            label: rubric.name,
                            hint: `skala ${rubric.scale_label}`,
                        }))}
                        onChange={(value) => form.setData('rubric_id', value)}
                        placeholder="Pilih rubrik terbit"
                        emptyLabel="Belum ada rubrik yang diterbitkan."
                    />
                </Field>

                <Field
                    label="Ambang Kelulusan (%)"
                    error={form.errors.pass_threshold}
                >
                    <Input
                        type="number"
                        min={0}
                        max={100}
                        value={form.data.pass_threshold}
                        placeholder="70"
                        onChange={(event) =>
                            form.setData('pass_threshold', event.target.value)
                        }
                    />
                </Field>

                <Btn
                    type="submit"
                    variant="primary"
                    disabled={form.processing || published.length === 0}
                    className="self-start"
                >
                    {form.processing ? 'Menyimpan...' : 'Terapkan Rubrik'}
                </Btn>
            </form>
        </Card>
    );
}
