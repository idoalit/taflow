import { Head, setLayoutProps, useForm } from '@inertiajs/react';
import { FileDrop } from '@/components/industry/file-drop';
import {
    Btn,
    BtnLink,
    Card,
    CardKicker,
    Input,
    Textarea,
} from '@/components/industry/ui';
import { dashboard } from '@/routes';
import { store } from '@/routes/proposals';

type ProposalSection = {
    key:
        | 'background'
        | 'problem_formulation'
        | 'objectives'
        | 'scope'
        | 'methodology'
        | 'roadmap_alignment';
    number: string;
    title: string;
    description: string;
    placeholder: string;
    rows: number;
    optional?: boolean;
};

const SECTIONS: ProposalSection[] = [
    {
        key: 'background',
        number: '01',
        title: 'Latar Belakang',
        description: 'Konteks dan urgensi penelitian.',
        placeholder:
            'Jelaskan konteks, kesenjangan, dan mengapa topik ini penting...',
        rows: 7,
    },
    {
        key: 'problem_formulation',
        number: '02',
        title: 'Rumusan Masalah',
        description: 'Pertanyaan penelitian utama.',
        placeholder: 'Rumuskan pertanyaan penelitian yang akan dijawab...',
        rows: 6,
    },
    {
        key: 'objectives',
        number: '03',
        title: 'Tujuan Penelitian',
        description: 'Apa yang ingin dicapai.',
        placeholder: 'Sebutkan tujuan penelitian secara spesifik...',
        rows: 5,
    },
    {
        key: 'scope',
        number: '04',
        title: 'Ruang Lingkup',
        description: 'Batasan penelitian.',
        placeholder: 'Batasan objek, waktu, atau data penelitian...',
        rows: 4,
        optional: true,
    },
    {
        key: 'methodology',
        number: '05',
        title: 'Metodologi',
        description: 'Pendekatan dan metode.',
        placeholder: 'Pendekatan, metode, dan alat yang digunakan...',
        rows: 5,
        optional: true,
    },
    {
        key: 'roadmap_alignment',
        number: '06',
        title: 'Kesesuaian Roadmap',
        description: 'Keterkaitan dengan roadmap riset prodi.',
        placeholder: 'Kaitkan topik dengan roadmap riset program studi...',
        rows: 4,
        optional: true,
    },
];

type ProposalForm = Record<ProposalSection['key'], string> & {
    title: string;
    document: File | null;
};

const CHECKLIST: { label: string; done: (form: ProposalForm) => boolean }[] = [
    { label: 'Judul Proposal', done: (form) => form.title.trim() !== '' },
    { label: 'Latar Belakang', done: (form) => form.background.length >= 50 },
    {
        label: 'Rumusan Masalah',
        done: (form) => form.problem_formulation.length >= 20,
    },
    {
        label: 'Tujuan Penelitian',
        done: (form) => form.objectives.length >= 20,
    },
    { label: 'Dokumen (PDF/DOCX)', done: (form) => form.document !== null },
];

export default function ProposalCreate() {
    setLayoutProps({ title: 'Ajukan Proposal' });

    const { data, setData, post, processing, errors } = useForm({
        title: '',
        background: '',
        problem_formulation: '',
        objectives: '',
        scope: '',
        methodology: '',
        roadmap_alignment: '',
        document: null as File | null,
    });

    return (
        <>
            <Head title="Ajukan Proposal" />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    post(store().url, { forceFormData: true });
                }}
                className="flex flex-col gap-[var(--ind-space-6)]"
            >
                <div>
                    <h3 className="mb-1">Ajukan Proposal</h3>
                    <p className="ind-muted m-0">
                        Lengkapi formulir di bawah untuk mengajukan proposal
                        tugas akhir Anda. Tulis tiap bagian selengkap mungkin —
                        beri ruang, jangan dipadatkan.
                    </p>
                </div>

                <div className="grid [grid-template-columns:minmax(0,1fr)_240px] items-start gap-[var(--ind-space-8)] max-lg:[grid-template-columns:minmax(0,1fr)]">
                    <div className="flex max-w-[640px] flex-col gap-[var(--ind-space-6)]">
                        <div>
                            <label className="ind-label" htmlFor="title">
                                Judul Proposal
                            </label>
                            <Input
                                id="title"
                                value={data.title}
                                onChange={(event) =>
                                    setData('title', event.target.value)
                                }
                                placeholder="Contoh: Analisis Sentimen Ulasan E-Commerce dengan BERT"
                            />
                            {errors.title && (
                                <p
                                    className="mt-[5px] mb-0 text-[11.5px]"
                                    style={{
                                        color: 'var(--ind-status-error-fg)',
                                    }}
                                >
                                    {errors.title}
                                </p>
                            )}
                        </div>

                        {SECTIONS.map((section) => (
                            <div key={section.key}>
                                <h6 className="mb-[2px]">
                                    {section.number} · {section.title}
                                    {section.optional && (
                                        <span
                                            className="ind-muted normal-case"
                                            style={{ letterSpacing: 0 }}
                                        >
                                            {' '}
                                            (opsional)
                                        </span>
                                    )}
                                </h6>
                                <p className="ind-muted m-0 mb-[var(--ind-space-2)] text-[12px]">
                                    {section.description}
                                </p>
                                <Textarea
                                    rows={section.rows}
                                    value={data[section.key]}
                                    onChange={(event) =>
                                        setData(section.key, event.target.value)
                                    }
                                    placeholder={section.placeholder}
                                />
                                {errors[section.key] && (
                                    <p
                                        className="mt-[5px] mb-0 text-[11.5px]"
                                        style={{
                                            color: 'var(--ind-status-error-fg)',
                                        }}
                                    >
                                        {errors[section.key]}
                                    </p>
                                )}
                            </div>
                        ))}

                        <div>
                            <h6 className="mb-[var(--ind-space-2)]">
                                07 · Dokumen Proposal
                            </h6>
                            <FileDrop
                                value={data.document}
                                onChange={(file) => setData('document', file)}
                                error={errors.document}
                            />
                        </div>

                        <div className="flex gap-[var(--ind-space-3)]">
                            <Btn
                                type="submit"
                                variant="primary"
                                disabled={processing}
                            >
                                {processing ? 'Mengirim...' : 'Ajukan Proposal'}
                            </Btn>
                            <BtnLink href={dashboard()}>Kembali</BtnLink>
                        </div>
                    </div>

                    <div className="sticky top-[calc(56px+var(--ind-space-6))] flex flex-col gap-[var(--ind-space-4)]">
                        <Card>
                            <CardKicker>Panduan</CardKicker>
                            <p className="ind-card-body m-0">
                                Satu proposal aktif per mahasiswa. Setelah
                                diajukan, pembimbing akan memberi feedback lewat
                                Persamaan Persepsi sebelum bisa lanjut ke sesi
                                bimbingan.
                            </p>
                        </Card>

                        <div className="ind-section">
                            <h6 className="ind-muted">Checklist</h6>
                            <div className="flex flex-col gap-[7px] text-[12.5px]">
                                {CHECKLIST.map((item) => {
                                    const done = item.done(data);

                                    return (
                                        <div
                                            key={item.label}
                                            className={
                                                done ? undefined : 'ind-muted'
                                            }
                                            style={
                                                done
                                                    ? {
                                                          color: 'var(--ind-accent-700)',
                                                      }
                                                    : undefined
                                            }
                                        >
                                            {done ? '☑' : '☐'} {item.label}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </>
    );
}
