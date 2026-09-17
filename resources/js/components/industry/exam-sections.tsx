import { router, useForm } from '@inertiajs/react';
import { Check, Download, FileCheck2, Plus, Trash2 } from 'lucide-react';
import { Fragment, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/industry/dialog';
import { SignaturePad } from '@/components/industry/signature-pad';
import {
    Btn,
    Card,
    CardKicker,
    DataTable,
    EmptyRow,
    Field,
    Input,
    Tag,
    Textarea,
} from '@/components/industry/ui';
import { toggle as toggleRevision } from '@/routes/revisions';

export type Participant = {
    id: number;
    name: string;
    role_label: string;
    attended: boolean;
    signed: boolean;
    is_me: boolean;
    can_mark: boolean;
};

export type DocumentRow = {
    value: string;
    label: string;
    ready: boolean;
    missing: string[];
    url: string;
};

/** Presensi digital plus the signature each seat leaves. */
export function AttendanceSection({
    participants,
    attendanceUrl,
    signUrl,
    locked,
}: {
    participants: Participant[];
    attendanceUrl: string;
    signUrl: string;
    locked: boolean;
}) {
    const [signing, setSigning] = useState(false);
    const mine = participants.find((person) => person.is_me);

    const markAttendance = (participant: Participant) =>
        router.post(
            attendanceUrl,
            { participant_id: participant.id, attended: !participant.attended },
            { preserveScroll: true },
        );

    return (
        <div className="ind-section">
            <h6>Presensi &amp; Tanda Tangan</h6>

            <DataTable
                head={
                    <tr>
                        <th>Peserta</th>
                        <th>Kedudukan</th>
                        <th>Hadir</th>
                        <th>Tanda Tangan</th>
                    </tr>
                }
            >
                {participants.length === 0 ? (
                    <EmptyRow colSpan={4}>
                        Panel sidang belum ditetapkan.
                    </EmptyRow>
                ) : (
                    participants.map((person) => (
                        <tr key={person.id}>
                            <td className="font-semibold">
                                {person.name}
                                {person.is_me && (
                                    <span className="ind-muted ml-2 text-[12px]">
                                        (Anda)
                                    </span>
                                )}
                            </td>
                            <td className="ind-muted">{person.role_label}</td>
                            <td>
                                {person.can_mark && !locked ? (
                                    <label className="ind-check">
                                        <input
                                            type="checkbox"
                                            checked={person.attended}
                                            onChange={() =>
                                                markAttendance(person)
                                            }
                                        />
                                        {person.attended ? 'Hadir' : 'Tandai'}
                                    </label>
                                ) : (
                                    <Tag
                                        label={
                                            person.attended
                                                ? 'Hadir'
                                                : 'Belum hadir'
                                        }
                                    />
                                )}
                            </td>
                            <td>
                                {person.signed ? (
                                    <span className="flex items-center gap-1 text-[13px]">
                                        <Check size={15} strokeWidth={1.5} />
                                        Sudah
                                    </span>
                                ) : (
                                    <span className="ind-muted text-[13px]">
                                        Belum
                                    </span>
                                )}
                            </td>
                        </tr>
                    ))
                )}
            </DataTable>

            {mine && !locked && (
                <Btn className="self-start" onClick={() => setSigning(true)}>
                    {mine.signed
                        ? 'Perbarui Tanda Tangan'
                        : 'Tanda Tangani Sekarang'}
                </Btn>
            )}

            <SignaturePad
                open={signing}
                onOpenChange={setSigning}
                action={signUrl}
            />
        </div>
    );
}

/** Lembar Revisi, written by one examiner as a numbered list. */
export function RevisionEditor({
    notes,
    action,
}: {
    notes: { body: string; due_date: string | null }[];
    action: string;
}) {
    const form = useForm({
        notes: notes.length > 0 ? notes : [{ body: '', due_date: null }],
    });

    const patch = (index: number, body: string) =>
        form.setData(
            'notes',
            form.data.notes.map((note, i) =>
                i === index ? { ...note, body } : note,
            ),
        );

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.post(action, { preserveScroll: true });
            }}
            className="ind-section"
        >
            <h6>Lembar Revisi Anda</h6>
            <p className="ind-muted mt-0 mb-0 text-[12.5px]">
                Poin dinomori mengikuti urutannya. Mahasiswa melihat gabungan
                dari seluruh penguji setelah hasil dirilis.
            </p>

            {form.data.notes.map((note, index) => (
                // Rows are positional while they are being written.
                <div
                    key={index}
                    className="flex items-end gap-[var(--ind-space-2)]"
                >
                    <span className="ind-rubric-code ind-rubric-code-item">
                        {index + 1}
                    </span>
                    <div className="flex-1">
                        <Field error={form.errors[`notes.${index}.body`]}>
                            <Input
                                value={note.body}
                                placeholder="Perbaiki rumusan masalah pada Bab I."
                                onChange={(event) =>
                                    patch(index, event.target.value)
                                }
                            />
                        </Field>
                    </div>
                    <button
                        type="button"
                        className="ind-btn ind-btn-ghost ind-btn-icon"
                        aria-label="Hapus poin"
                        onClick={() =>
                            form.setData(
                                'notes',
                                form.data.notes.filter((_, i) => i !== index),
                            )
                        }
                    >
                        <Trash2 size={16} strokeWidth={1.5} />
                    </button>
                </div>
            ))}

            <div className="flex gap-[var(--ind-space-3)]">
                <Btn
                    type="button"
                    onClick={() =>
                        form.setData('notes', [
                            ...form.data.notes,
                            { body: '', due_date: null },
                        ])
                    }
                >
                    <Plus size={15} strokeWidth={1.5} />
                    Tambah Poin
                </Btn>
                <Btn type="submit" variant="primary" disabled={form.processing}>
                    {form.processing ? 'Menyimpan...' : 'Simpan Lembar Revisi'}
                </Btn>
            </div>
        </form>
    );
}

/**
 * The merged checklist the student works through after release.
 *
 * Grouped by penguji rather than flattened: each Lembar Revisi is numbered
 * from one, so a single running list would show two number ones.
 */
export function RevisionChecklist({
    revisions,
    canTick,
}: {
    revisions: {
        id: number;
        lecturer: string;
        position: number;
        body: string;
        status: string;
        status_label: string;
        due_date: string | null;
    }[];
    canTick: boolean;
}) {
    if (revisions.length === 0) {
        return null;
    }

    const lecturers = [...new Set(revisions.map((note) => note.lecturer))];
    const done = revisions.filter((note) => note.status === 'done').length;

    return (
        <div className="ind-section">
            <h6>Daftar Revisi</h6>

            <DataTable
                head={
                    <tr>
                        <th style={{ width: '3rem' }}>No</th>
                        <th>Poin Revisi</th>
                        <th style={{ width: '10rem' }}>Status</th>
                    </tr>
                }
            >
                {lecturers.map((lecturer) => (
                    <Fragment key={lecturer}>
                        <tr>
                            <td colSpan={3} className="ind-row-group">
                                {lecturer}
                            </td>
                        </tr>
                        {revisions
                            .filter((note) => note.lecturer === lecturer)
                            .map((note) => (
                                <tr key={note.id}>
                                    <td className="ind-muted">
                                        {note.position}
                                    </td>
                                    <td>
                                        {note.body}
                                        {note.due_date && (
                                            <span className="ind-muted block text-[12px]">
                                                Batas {note.due_date}
                                            </span>
                                        )}
                                    </td>
                                    <td>
                                        {canTick ? (
                                            <label className="ind-check">
                                                <input
                                                    type="checkbox"
                                                    checked={
                                                        note.status === 'done'
                                                    }
                                                    onChange={() =>
                                                        router.post(
                                                            toggleRevision(
                                                                note.id,
                                                            ).url,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                />
                                                {note.status_label}
                                            </label>
                                        ) : (
                                            <Tag label={note.status_label} />
                                        )}
                                    </td>
                                </tr>
                            ))}
                    </Fragment>
                ))}
            </DataTable>

            <p className="ind-muted m-0 text-[12.5px]">
                {done} dari {revisions.length} poin revisi telah diselesaikan.
            </p>
        </div>
    );
}

/** Berita Acara, settled by the chair. */
export function MinutesForm({
    minutes,
    results,
    missing,
    action,
}: {
    minutes: {
        decision: string;
        revision_days: number;
        notes: string | null;
    } | null;
    results: { value: string; label: string }[];
    missing: string[];
    action: string;
}) {
    const form = useForm({
        decision: minutes?.decision ?? results[0]?.value ?? 'passed',
        revision_days: String(minutes?.revision_days ?? 7),
        notes: minutes?.notes ?? '',
    });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.post(action, { preserveScroll: true });
            }}
            className="ind-section"
        >
            <h6>Berita Acara</h6>

            {missing.length > 0 ? (
                // Saying so here beats letting the chair fill the form in and
                // meet the same refusal from the server.
                <p className="ind-muted m-0 text-[12.5px]">
                    Belum dapat ditetapkan: {missing.join(' ')}
                </p>
            ) : (
                form.errors.decision && (
                    <p
                        className="m-0 text-[12.5px]"
                        style={{ color: 'var(--ind-status-error-fg)' }}
                    >
                        {form.errors.decision}
                    </p>
                )
            )}

            <div className="grid [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))] gap-[var(--ind-space-4)]">
                <Field label="Keputusan Dewan Penguji">
                    <select
                        className="ind-input"
                        value={form.data.decision}
                        onChange={(event) =>
                            form.setData('decision', event.target.value)
                        }
                    >
                        {results.map((result) => (
                            <option key={result.value} value={result.value}>
                                {result.label}
                            </option>
                        ))}
                    </select>
                </Field>

                <Field
                    label="Lama Perbaikan (hari kerja)"
                    error={form.errors.revision_days}
                >
                    <Input
                        type="number"
                        min={0}
                        max={180}
                        value={form.data.revision_days}
                        onChange={(event) =>
                            form.setData('revision_days', event.target.value)
                        }
                    />
                </Field>
            </div>

            <Field label="Catatan" optional error={form.errors.notes}>
                <Textarea
                    rows={2}
                    value={form.data.notes}
                    onChange={(event) =>
                        form.setData('notes', event.target.value)
                    }
                />
            </Field>

            <Btn
                type="submit"
                variant="primary"
                className="self-start"
                disabled={form.processing || missing.length > 0}
            >
                {form.processing ? 'Menyimpan...' : 'Tetapkan Berita Acara'}
            </Btn>
        </form>
    );
}

/** The documents, each with why it cannot be produced yet. */
export function DocumentSection({
    documents,
    allUrl,
}: {
    documents: DocumentRow[];
    allUrl: string;
}) {
    const ready = documents.filter((document) => document.ready).length;

    return (
        <div className="ind-section">
            <h6>Dokumen Resmi</h6>

            <DataTable
                head={
                    <tr>
                        <th>Dokumen</th>
                        <th>Status</th>
                        <th>Unduh</th>
                    </tr>
                }
            >
                {documents.map((document) => (
                    <tr key={document.value}>
                        <td className="font-semibold">{document.label}</td>
                        <td>
                            {document.ready ? (
                                <Tag label="Siap" />
                            ) : (
                                <span className="ind-muted text-[12.5px]">
                                    {document.missing.join(' ')}
                                </span>
                            )}
                        </td>
                        <td>
                            {document.ready && (
                                <a
                                    href={document.url}
                                    className="ind-btn ind-btn-ghost ind-btn-icon"
                                    aria-label={`Unduh ${document.label}`}
                                >
                                    <Download size={16} strokeWidth={1.5} />
                                </a>
                            )}
                        </td>
                    </tr>
                ))}
            </DataTable>

            {ready > 0 && (
                <a
                    href={allUrl}
                    className="ind-btn ind-btn-secondary self-start"
                >
                    <FileCheck2 size={15} strokeWidth={1.5} />
                    Unduh Semua ({ready} dokumen)
                </a>
            )}
        </div>
    );
}

/** Releasing, and reopening what was released. */
export function ReleaseSection({
    released,
    action,
}: {
    released: boolean;
    action: string;
}) {
    const [unlocking, setUnlocking] = useState(false);
    const form = useForm({ reason: '' });

    if (!released) {
        return (
            <Card>
                <CardKicker>Rilis Hasil</CardKicker>
                <p className="ind-card-body m-0">
                    Merilis membuat nilai dan daftar revisi terlihat oleh
                    mahasiswa, dan mengunci seluruh penilaian.
                </p>
                <Btn
                    variant="primary"
                    className="mt-[var(--ind-space-3)] self-start"
                    disabled={form.processing}
                    onClick={() =>
                        router.post(action, {}, { preserveScroll: true })
                    }
                >
                    Rilis Hasil Ujian
                </Btn>
            </Card>
        );
    }

    return (
        <Card>
            <CardKicker>Hasil Terkunci</CardKicker>
            <p className="ind-card-body m-0">
                Hasil sudah dirilis. Perubahan hanya mungkin setelah kunci
                dibuka, dan alasannya tercatat pada log audit.
            </p>
            <Btn
                variant="danger"
                className="mt-[var(--ind-space-3)] self-start"
                onClick={() => setUnlocking(true)}
            >
                Buka Kunci
            </Btn>

            <Dialog open={unlocking} onOpenChange={setUnlocking}>
                <DialogContent>
                    <DialogTitle>Buka kunci hasil ujian?</DialogTitle>
                    <DialogDescription>
                        Nilai akan dapat diubah kembali oleh penguji. Alasan
                        Anda tercatat permanen pada log audit.
                    </DialogDescription>

                    <Field label="Alasan" error={form.errors.reason}>
                        <Textarea
                            rows={3}
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            placeholder="Contoh: salah entri nilai penguji kedua, disepakati rapat dewan penguji."
                        />
                    </Field>

                    <DialogFooter>
                        <Btn onClick={() => setUnlocking(false)}>Batal</Btn>
                        <Btn
                            variant="danger"
                            disabled={form.processing}
                            onClick={() =>
                                form.delete(action, {
                                    preserveScroll: true,
                                    onSuccess: () => setUnlocking(false),
                                })
                            }
                        >
                            Buka Kunci
                        </Btn>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Card>
    );
}
