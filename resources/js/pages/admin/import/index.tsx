import { Head, setLayoutProps, useForm, usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import { FileDrop } from '@/components/industry/file-drop';
import {
    Btn,
    Card,
    CardKicker,
    PageHeader,
    Tabs,
} from '@/components/industry/ui';
import { store, template } from '@/routes/admin/import';

type Target = { value: string; label: string; columns: string[] };

export default function AdminImportIndex({
    department,
    targets,
}: {
    department: string;
    targets: Target[];
}) {
    setLayoutProps({ title: 'Import Data' });

    const [target, setTarget] = useState(targets[0]?.value ?? 'students');
    const active = targets.find((option) => option.value === target);
    const result = usePage().props.flash.importResult;

    const form = useForm<{ target: string; file: File | null }>({
        target,
        file: null,
    });

    return (
        <>
            <Head title="Import Data" />

            <div className="ind-page max-w-[720px]">
                <PageHeader
                    title="Import Data Massal"
                    description={`Tambahkan banyak dosen atau mahasiswa ke ${department} sekaligus dari berkas Excel/CSV.`}
                />

                <Tabs
                    label="Jenis data"
                    value={target}
                    options={targets.map((option) => ({
                        value: option.value,
                        label: option.label,
                    }))}
                    onChange={(next) => {
                        setTarget(next);
                        form.setData('target', next);
                    }}
                />

                <Card>
                    <CardKicker>Langkah 1 — Unduh Template</CardKicker>
                    <p className="ind-card-body m-0">
                        Template berisi kolom yang wajib diisi:{' '}
                        {active?.columns.join(', ')}.
                    </p>
                    <a
                        href={template(target).url}
                        className="ind-btn ind-btn-secondary mt-[var(--ind-space-2)] self-start"
                    >
                        <Download size={15} strokeWidth={1.5} />
                        Unduh Template {active?.label}.xlsx
                    </a>
                </Card>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(store().url, { forceFormData: true });
                    }}
                >
                    <Card>
                        <CardKicker>Langkah 2 — Unggah Berkas</CardKicker>

                        <FileDrop
                            value={form.data.file}
                            onChange={(file) => form.setData('file', file)}
                            accept=".xlsx,.xls,.csv"
                            hint="Berkas .xlsx, .xls, atau .csv, maks. 5MB"
                            error={form.errors.file}
                        />

                        <Btn
                            type="submit"
                            variant="primary"
                            className="mt-[var(--ind-space-3)] self-start"
                            disabled={
                                form.processing || form.data.file === null
                            }
                        >
                            {form.processing ? 'Memproses...' : 'Proses Impor'}
                        </Btn>
                    </Card>
                </form>

                {result && (
                    <Card>
                        <CardKicker>Hasil Impor</CardKicker>

                        <div className="mt-[var(--ind-space-2)] flex gap-[var(--ind-space-6)]">
                            <div>
                                <div
                                    className="ind-stat ind-stat-sm"
                                    style={{ color: 'var(--ind-accent-700)' }}
                                >
                                    {result.imported}
                                </div>
                                <div className="ind-muted text-[12px]">
                                    Berhasil
                                </div>
                            </div>
                            {result.attached > 0 && (
                                <div>
                                    <div className="ind-stat ind-stat-sm">
                                        {result.attached}
                                    </div>
                                    <div className="ind-muted text-[12px]">
                                        Sudah ada, ditautkan ke prodi
                                    </div>
                                </div>
                            )}
                            <div>
                                <div className="ind-stat ind-stat-sm">
                                    {result.failed}
                                </div>
                                <div className="ind-muted text-[12px]">
                                    Gagal
                                </div>
                            </div>
                        </div>

                        {result.errors.length > 0 && (
                            <ul className="mt-[var(--ind-space-3)] mb-0 flex list-none flex-col gap-1 p-0 text-[13px]">
                                {result.errors.map((error) => (
                                    <li key={error}>{error}</li>
                                ))}
                            </ul>
                        )}
                    </Card>
                )}
            </div>
        </>
    );
}
