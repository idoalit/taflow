import { Head, Link, setLayoutProps, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/industry/dialog';
import {
    Btn,
    BtnLink,
    Card,
    CardKicker,
    DataTable,
    Tag,
} from '@/components/industry/ui';
import { destroy, index, publish } from '@/routes/admin/rubrics';
import type { AdminRubricRow } from '@/types';

type Props = {
    rubric: AdminRubricRow & {
        criteria: {
            label: string;
            key: string;
            weight: number | null;
            max_score: number;
            block_code: string | null;
            block_label: string | null;
            subblock_code: string | null;
            subblock_label: string | null;
            item_number: number | null;
        }[];
        block_summary: {
            code: string | null;
            label: string | null;
            max: number;
        }[];
    };
};

export default function AdminRubricShow({ rubric }: Props) {
    setLayoutProps({ title: 'Detail Rubrik' });

    const form = useForm({});
    const publishError = usePage().props.errors?.criteria;
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const isBlockScheme = rubric.scoring_scheme === 'block_additive';

    return (
        <>
            <Head title={rubric.name} />

            <div className="ind-page max-w-[720px]">
                <div>
                    <Link href={index()} className="text-[12.5px]">
                        ← Daftar Rubrik
                    </Link>
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 style={{ margin: '6px 0 8px' }}>
                                {rubric.name}
                            </h3>
                            <div className="flex flex-wrap items-center gap-[10px]">
                                <Tag label={rubric.status_label} />
                                <span className="ind-muted text-[13px]">
                                    {rubric.scheme_label}
                                </span>
                                {!isBlockScheme && (
                                    <span className="ind-muted text-[13px]">
                                        Skala {rubric.scale_label}
                                    </span>
                                )}
                                {rubric.description && (
                                    <span className="ind-muted text-[13px]">
                                        {rubric.description}
                                    </span>
                                )}
                            </div>
                        </div>

                        {rubric.editable && (
                            <div className="flex gap-[var(--ind-space-3)]">
                                <BtnLink href={rubric.edit_url}>
                                    Ubah Rubrik
                                </BtnLink>
                                <Btn
                                    variant="danger"
                                    onClick={() => setConfirmingDelete(true)}
                                >
                                    Hapus Draf
                                </Btn>
                            </div>
                        )}
                    </div>
                </div>

                {!rubric.editable && (
                    <Card>
                        <CardKicker>Terkunci</CardKicker>
                        <p className="ind-card-body m-0">
                            {rubric.used
                                ? 'Rubrik ini sudah dipakai menilai ujian, sehingga kriterianya tidak dapat diubah lagi.'
                                : 'Rubrik yang sudah diterbitkan tidak dapat diubah, karena mungkin sudah diterapkan pada tahap ujian atau terkunci pada jadwal ujian.'}{' '}
                            Buat rubrik baru bila kriterianya perlu berganti.
                        </p>
                    </Card>
                )}

                {isBlockScheme ? (
                    <DataTable
                        head={
                            <tr>
                                <th>Blok</th>
                                <th>Item</th>
                                <th>Pengenal Sistem</th>
                                <th>Nilai Maks</th>
                            </tr>
                        }
                    >
                        {rubric.criteria.map((criterion, index) => {
                            const previous = rubric.criteria[index - 1];
                            const newBlock =
                                previous?.block_code !== criterion.block_code;
                            const newSub =
                                newBlock ||
                                previous?.subblock_code !==
                                    criterion.subblock_code;

                            return (
                                <tr key={criterion.key}>
                                    <td className="ind-muted align-top">
                                        {newBlock && (
                                            <span className="font-semibold">
                                                {criterion.block_code}.{' '}
                                                {criterion.block_label}
                                            </span>
                                        )}
                                        {newSub && criterion.subblock_code && (
                                            <span className="block text-[12px]">
                                                {criterion.subblock_code}.{' '}
                                                {criterion.subblock_label}
                                            </span>
                                        )}
                                    </td>
                                    <td>
                                        {criterion.item_number}.{' '}
                                        {criterion.label}
                                    </td>
                                    <td className="ind-muted">
                                        {criterion.key}
                                    </td>
                                    <td>{criterion.max_score}</td>
                                </tr>
                            );
                        })}

                        {rubric.block_summary.map((block) => (
                            <tr key={`sub-${block.code}`}>
                                <td className="font-semibold">
                                    Jumlah blok {block.code}
                                </td>
                                <td />
                                <td />
                                <td className="font-semibold">{block.max}</td>
                            </tr>
                        ))}

                        <tr>
                            <td className="font-semibold">TOTAL NILAI</td>
                            <td />
                            <td />
                            <td
                                className="font-semibold"
                                style={{
                                    color: rubric.balanced
                                        ? undefined
                                        : 'var(--ind-status-error-fg)',
                                }}
                            >
                                {rubric.criteria.reduce(
                                    (sum, c) => sum + c.max_score,
                                    0,
                                )}
                            </td>
                        </tr>
                    </DataTable>
                ) : (
                    <DataTable
                        head={
                            <tr>
                                <th>Kriteria</th>
                                <th>Pengenal Sistem</th>
                                <th>Bobot</th>
                                <th>Nilai Maks</th>
                            </tr>
                        }
                    >
                        {rubric.criteria.map((criterion) => (
                            <tr key={criterion.key}>
                                <td className="font-semibold">
                                    {criterion.label}
                                </td>
                                <td className="ind-muted">{criterion.key}</td>
                                <td>{criterion.weight}%</td>
                                <td className="ind-muted">
                                    0–{criterion.max_score}
                                </td>
                            </tr>
                        ))}
                        <tr>
                            <td className="font-semibold">Total</td>
                            <td />
                            <td
                                className="font-semibold"
                                style={{
                                    color: rubric.balanced
                                        ? undefined
                                        : 'var(--ind-status-error-fg)',
                                }}
                            >
                                {rubric.total_weight}%
                            </td>
                            <td />
                        </tr>
                    </DataTable>
                )}

                {publishError && (
                    <p
                        className="m-0 text-[12.5px]"
                        style={{ color: 'var(--ind-status-error-fg)' }}
                    >
                        {publishError}
                    </p>
                )}

                {rubric.status !== 'published' && (
                    <Btn
                        variant="primary"
                        className="self-start"
                        disabled={form.processing || !rubric.balanced}
                        onClick={() =>
                            form.post(publish(rubric.id).url, {
                                preserveScroll: true,
                            })
                        }
                    >
                        {rubric.balanced
                            ? 'Terbitkan Rubrik'
                            : 'Bobot harus genap 100% sebelum diterbitkan'}
                    </Btn>
                )}

                <Dialog
                    open={confirmingDelete}
                    onOpenChange={setConfirmingDelete}
                >
                    <DialogContent>
                        <DialogTitle>Hapus draf rubrik?</DialogTitle>
                        <DialogDescription>
                            Rubrik &ldquo;{rubric.name}&rdquo; beserta seluruh
                            kriterianya akan dihapus permanen. Tindakan ini
                            tidak dapat dibatalkan.
                        </DialogDescription>

                        <DialogFooter>
                            <Btn onClick={() => setConfirmingDelete(false)}>
                                Batal
                            </Btn>
                            <Btn
                                variant="danger"
                                disabled={form.processing}
                                onClick={() =>
                                    form.delete(destroy(rubric.id).url)
                                }
                            >
                                {form.processing
                                    ? 'Menghapus...'
                                    : 'Hapus Rubrik'}
                            </Btn>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
