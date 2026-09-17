import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { ArrowDown, MessageSquare } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { BlueprintCorners } from '@/components/industry/blueprint';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/industry/dialog';
import {
    DocumentChip,
    DocumentLink,
} from '@/components/industry/document-link';
import {
    Avatar,
    Btn,
    Card,
    CardKicker,
    Field,
    Tag,
    Textarea,
} from '@/components/industry/ui';
import { cancel, complete, index } from '@/routes/guidance-sessions';
import { store as storeMessage } from '@/routes/guidance-sessions/messages';
import type { GuidanceSessionDetail } from '@/types';

type Props = {
    session: GuidanceSessionDetail;
    can: { participate: boolean; cancel: boolean; complete: boolean };
};

export default function GuidanceSessionShow({ session, can }: Props) {
    setLayoutProps({ title: 'Detail Sesi Bimbingan' });

    const [cancelling, setCancelling] = useState(false);
    const [completing, setCompleting] = useState(false);
    const completeForm = useForm({ completion_notes: '', feedback: '' });
    const transcriptRef = useRef<HTMLDivElement>(null);
    const [atNewest, setAtNewest] = useState(true);

    const scrollToNewest = () => {
        const transcript = transcriptRef.current;

        if (transcript !== null) {
            transcript.scrollTo({
                top: transcript.scrollHeight,
                behavior: 'smooth',
            });
        }
    };

    // Reading back through a long thread should not be yanked to the bottom, so
    // the control only appears once the reader has scrolled away from it.
    const handleTranscriptScroll = () => {
        const transcript = transcriptRef.current;

        if (transcript !== null) {
            const distance =
                transcript.scrollHeight -
                transcript.scrollTop -
                transcript.clientHeight;

            setAtNewest(distance < 24);
        }
    };

    // Open on the newest message, and follow along as replies are posted.
    useEffect(() => {
        const transcript = transcriptRef.current;

        if (transcript !== null) {
            transcript.scrollTop = transcript.scrollHeight;
            setAtNewest(true);
        }
    }, [session.messages.length]);

    const messageForm = useForm({
        message: '',
        attachment: null as File | null,
    });
    const cancelForm = useForm({ cancellation_reason: '' });

    const isAsync = session.guidance_type === 'online_async';

    return (
        <>
            <Head title={session.title} />

            <div className="flex flex-col gap-[var(--ind-space-4)]">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0 flex-1">
                        <Link href={index()} className="text-[12.5px]">
                            ← Sesi Bimbingan
                        </Link>
                        <h3 style={{ margin: '6px 0 8px' }}>{session.title}</h3>
                        <Tag label={session.status_label} />
                    </div>
                    <div className="flex flex-wrap gap-[var(--ind-space-2)]">
                        {can.complete && (
                            <Btn
                                variant="primary"
                                onClick={() => setCompleting(true)}
                            >
                                Tandai Selesai
                            </Btn>
                        )}
                        {can.cancel && (
                            <Btn onClick={() => setCancelling(true)}>
                                Batalkan Bimbingan
                            </Btn>
                        )}
                    </div>
                </div>

                <div className="grid [grid-template-columns:minmax(0,1fr)_280px] items-start gap-[var(--ind-space-6)] max-lg:[grid-template-columns:minmax(0,1fr)]">
                    <div className="flex flex-col gap-[var(--ind-space-4)]">
                        {isAsync ? (
                            <div className="ind-card ind-bp flex max-h-[calc(100dvh-210px)] min-h-[320px] flex-col overflow-hidden p-0">
                                <BlueprintCorners />
                                <div
                                    className="flex flex-shrink-0 items-center justify-between gap-3 p-[var(--ind-space-4)]"
                                    style={{
                                        borderBottom:
                                            '1px solid var(--ind-divider)',
                                    }}
                                >
                                    <div className="flex items-center gap-[10px]">
                                        <MessageSquare
                                            size={18}
                                            strokeWidth={1.5}
                                            style={{
                                                color: 'var(--ind-accent-700)',
                                            }}
                                        />
                                        <div>
                                            <div
                                                className="text-[15px] font-semibold"
                                                style={{
                                                    fontFamily:
                                                        'var(--ind-font-heading)',
                                                }}
                                            >
                                                Chat Bimbingan
                                            </div>
                                            <div className="ind-muted text-[12px]">
                                                {session.messages.length} pesan
                                                dalam diskusi ini
                                            </div>
                                        </div>
                                    </div>
                                    <Tag
                                        label={session.guidance_type_label}
                                        tone="accent"
                                    />
                                </div>

                                {/* The card owns the height budget: its header
                                    and composer are fixed, and the transcript
                                    takes whatever is left and scrolls. That
                                    keeps the whole panel — send button included
                                    — on screen however long the thread runs. */}
                                <div className="relative flex min-h-0 flex-1 flex-col">
                                    <div
                                        ref={transcriptRef}
                                        onScroll={handleTranscriptScroll}
                                        className="flex min-h-0 flex-1 flex-col gap-[var(--ind-space-4)] overflow-x-hidden overflow-y-auto p-[var(--ind-space-4)]"
                                    >
                                        {session.messages.length === 0 && (
                                            <p className="ind-muted m-auto text-[13px]">
                                                Belum ada pesan. Mulai diskusi
                                                dengan pembimbing Anda.
                                            </p>
                                        )}

                                        {session.messages.map((message) => (
                                            <div
                                                key={message.id}
                                                className="max-w-[75%]"
                                                style={
                                                    message.is_mine
                                                        ? {
                                                              alignSelf:
                                                                  'flex-end',
                                                              textAlign:
                                                                  'right',
                                                          }
                                                        : undefined
                                                }
                                            >
                                                <div className="ind-muted mb-[3px] text-[11.5px]">
                                                    {message.author_name} ·{' '}
                                                    {message.sent_at}
                                                </div>
                                                <div
                                                    className="px-3 py-[10px] text-[13.5px] leading-[1.5]"
                                                    style={
                                                        message.is_mine
                                                            ? {
                                                                  background:
                                                                      'var(--ind-accent-700)',
                                                                  color: 'var(--ind-bg)',
                                                                  textAlign:
                                                                      'left',
                                                              }
                                                            : {
                                                                  border: '1px solid var(--ind-divider)',
                                                              }
                                                    }
                                                >
                                                    {message.body}
                                                </div>
                                                {message.attachment && (
                                                    <DocumentChip
                                                        document={
                                                            message.attachment
                                                        }
                                                    />
                                                )}
                                            </div>
                                        ))}
                                    </div>

                                    {!atNewest && (
                                        <button
                                            type="button"
                                            onClick={scrollToNewest}
                                            className="ind-btn ind-btn-primary ind-bp absolute bottom-3 left-1/2 -translate-x-1/2 text-[12.5px]"
                                            aria-label="Lihat pesan terbaru"
                                        >
                                            <i className="ind-corner tl" />
                                            <i className="ind-corner tr" />
                                            <i className="ind-corner bl" />
                                            <i className="ind-corner br" />
                                            <ArrowDown
                                                size={14}
                                                strokeWidth={1.5}
                                            />
                                            Pesan Terbaru
                                        </button>
                                    )}
                                </div>

                                {can.participate && (
                                    <div
                                        className="flex flex-shrink-0 flex-col gap-[var(--ind-space-2)] p-[var(--ind-space-4)]"
                                        style={{
                                            borderTop:
                                                '1px solid var(--ind-divider)',
                                        }}
                                    >
                                        <Textarea
                                            rows={2}
                                            value={messageForm.data.message}
                                            onChange={(event) =>
                                                messageForm.setData(
                                                    'message',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Tulis balasan..."
                                        />
                                        {messageForm.errors.message && (
                                            <p
                                                className="m-0 text-[11.5px]"
                                                style={{
                                                    color: 'var(--ind-status-error-fg)',
                                                }}
                                            >
                                                {messageForm.errors.message}
                                            </p>
                                        )}
                                        <div className="flex justify-end gap-[var(--ind-space-2)]">
                                            <label className="ind-btn ind-btn-secondary cursor-pointer">
                                                {messageForm.data.attachment
                                                    ?.name ?? 'Lampirkan'}
                                                <input
                                                    type="file"
                                                    className="hidden"
                                                    onChange={(event) =>
                                                        messageForm.setData(
                                                            'attachment',
                                                            event.target
                                                                .files?.[0] ??
                                                                null,
                                                        )
                                                    }
                                                />
                                            </label>
                                            <Btn
                                                variant="primary"
                                                disabled={
                                                    messageForm.processing
                                                }
                                                onClick={() =>
                                                    messageForm.post(
                                                        storeMessage(session.id)
                                                            .url,
                                                        {
                                                            forceFormData: true,
                                                            preserveScroll: true,
                                                            onSuccess: () =>
                                                                messageForm.reset(),
                                                        },
                                                    )
                                                }
                                            >
                                                Kirim Pesan
                                            </Btn>
                                        </div>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <>
                                {session.document && (
                                    <Card className="flex-row items-center">
                                        <DocumentLink
                                            document={session.document}
                                            meta="Diunggah saat sesi dibuat"
                                            className="flex-1"
                                        />
                                    </Card>
                                )}

                                {session.notes && (
                                    <div className="ind-section">
                                        <h6 className="ind-muted">
                                            Catatan Awal
                                        </h6>
                                        <p className="m-0 whitespace-pre-line">
                                            {session.notes}
                                        </p>
                                    </div>
                                )}
                            </>
                        )}

                        {session.completion_notes && (
                            <Card>
                                <CardKicker>Catatan Penyelesaian</CardKicker>
                                <p className="ind-card-body m-0 whitespace-pre-line">
                                    {session.completion_notes}
                                </p>
                                <div className="ind-card-meta">
                                    Ditandai selesai oleh pembimbing
                                    {session.completed_at
                                        ? ` · ${session.completed_at}`
                                        : ''}
                                </div>
                            </Card>
                        )}

                        {session.feedback && (
                            <div className="ind-section">
                                <h6 className="ind-muted">
                                    Umpan Balik Pembimbing
                                </h6>
                                <p className="m-0 whitespace-pre-line">
                                    {session.feedback}
                                </p>
                            </div>
                        )}

                        {session.cancellation_reason && (
                            <div className="ind-section">
                                <h6 className="ind-muted">Alasan Pembatalan</h6>
                                <p className="m-0 whitespace-pre-line">
                                    {session.cancellation_reason}
                                </p>
                            </div>
                        )}
                    </div>

                    <div className="flex flex-col gap-[var(--ind-space-4)]">
                        <div>
                            <h6 className="ind-muted mb-[var(--ind-space-3)]">
                                Informasi Sesi
                            </h6>
                            <div className="flex flex-col gap-[var(--ind-space-3)]">
                                <Info
                                    label="Mahasiswa"
                                    value={session.student.name}
                                />
                                <Info
                                    label="Tipe Bimbingan"
                                    value={session.guidance_type_label}
                                />
                                <Info
                                    label="Tahap"
                                    value={session.stage_label}
                                />
                                <Info
                                    label="Target Pembimbing"
                                    value={session.target_type_label}
                                />
                                {!isAsync && session.meeting_type_label && (
                                    <>
                                        <Info
                                            label="Jenis Pertemuan"
                                            value={session.meeting_type_label}
                                        />
                                        {session.location_value && (
                                            <Info
                                                label={
                                                    session.location_label ??
                                                    'Lokasi'
                                                }
                                                value={session.location_value}
                                            />
                                        )}
                                        {session.scheduled_at && (
                                            <Info
                                                label="Jadwal"
                                                value={session.scheduled_at}
                                            />
                                        )}
                                    </>
                                )}
                            </div>
                        </div>

                        <div>
                            <h6 className="ind-muted mb-[var(--ind-space-3)]">
                                {session.supervisors.length > 1
                                    ? 'Pembimbing Sesi Ini'
                                    : 'Pembimbing'}
                            </h6>
                            {session.supervisors.length === 0 && (
                                <p className="ind-muted m-0 text-[12px]">
                                    Pembimbing belum ditetapkan admin prodi.
                                </p>
                            )}
                            <div className="flex flex-col gap-[var(--ind-space-3)]">
                                {session.supervisors.map(
                                    (supervisor, index) => (
                                        <div
                                            key={supervisor.id}
                                            className="flex items-center gap-[10px]"
                                        >
                                            <Avatar
                                                initials={supervisor.initials}
                                                tone={
                                                    index === 0
                                                        ? 'accent'
                                                        : 'accent-2'
                                                }
                                            />
                                            <div className="min-w-0">
                                                <div className="text-[13px] font-semibold">
                                                    {supervisor.name}
                                                </div>
                                                <div className="ind-muted text-[11px]">
                                                    {supervisor.position_label}
                                                </div>
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>

                        <div>
                            <h6 className="ind-muted mb-[var(--ind-space-3)]">
                                Timeline
                            </h6>
                            <div className="flex flex-col gap-2">
                                <Info
                                    label="Dibuat"
                                    value={session.created_at}
                                />
                                {session.completed_at && (
                                    <Info
                                        label="Selesai"
                                        value={session.completed_at}
                                    />
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Both actions are irreversible for the student, so each is
                confirmed in its own dialog rather than inline on the detail. */}
            <Dialog
                open={completing}
                onOpenChange={(open) => !open && setCompleting(false)}
            >
                <DialogContent>
                    <DialogTitle>Tandai Sesi Selesai</DialogTitle>
                    <DialogDescription>
                        Sesi "{session.title}" bersama {session.student.name}{' '}
                        akan ditandai selesai dan ikut dihitung sebagai syarat
                        pengajuan ujian. Tindakan ini tidak dapat dibatalkan.
                    </DialogDescription>

                    <Field
                        label="Catatan Penyelesaian"
                        error={completeForm.errors.completion_notes}
                    >
                        <Textarea
                            rows={3}
                            value={completeForm.data.completion_notes}
                            onChange={(event) =>
                                completeForm.setData(
                                    'completion_notes',
                                    event.target.value,
                                )
                            }
                            placeholder="Apa yang sudah disepakati dan apa langkah berikutnya..."
                        />
                    </Field>

                    <Field
                        label="Umpan Balik"
                        optional
                        error={completeForm.errors.feedback}
                    >
                        <Textarea
                            rows={2}
                            value={completeForm.data.feedback}
                            onChange={(event) =>
                                completeForm.setData(
                                    'feedback',
                                    event.target.value,
                                )
                            }
                            placeholder="Umpan balik singkat untuk mahasiswa"
                        />
                    </Field>

                    <DialogFooter>
                        <Btn onClick={() => setCompleting(false)}>Batal</Btn>
                        <Btn
                            variant="primary"
                            disabled={completeForm.processing}
                            onClick={() =>
                                completeForm.post(complete(session.id).url, {
                                    preserveScroll: true,
                                    onSuccess: () => setCompleting(false),
                                })
                            }
                        >
                            {completeForm.processing
                                ? 'Menyimpan...'
                                : 'Tandai Selesai'}
                        </Btn>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={cancelling}
                onOpenChange={(open) => !open && setCancelling(false)}
            >
                <DialogContent>
                    <DialogTitle>Batalkan Sesi Bimbingan</DialogTitle>
                    <DialogDescription>
                        Sesi "{session.title}" akan dibatalkan dan tidak
                        dihitung sebagai bimbingan yang selesai. Tindakan ini
                        tidak dapat dibatalkan.
                    </DialogDescription>

                    <Field
                        label="Alasan Pembatalan"
                        error={cancelForm.errors.cancellation_reason}
                    >
                        <Textarea
                            rows={3}
                            value={cancelForm.data.cancellation_reason}
                            onChange={(event) =>
                                cancelForm.setData(
                                    'cancellation_reason',
                                    event.target.value,
                                )
                            }
                            placeholder="Jelaskan alasan pembatalan..."
                        />
                    </Field>

                    <DialogFooter>
                        <Btn onClick={() => setCancelling(false)}>Kembali</Btn>
                        <Btn
                            variant="danger"
                            disabled={cancelForm.processing}
                            onClick={() =>
                                cancelForm.post(cancel(session.id).url, {
                                    onSuccess: () => setCancelling(false),
                                })
                            }
                        >
                            {cancelForm.processing
                                ? 'Membatalkan...'
                                : 'Konfirmasi Pembatalan'}
                        </Btn>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function Info({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="ind-muted text-[11px]">{label}</div>
            <div className="text-[13.5px] font-semibold">{value}</div>
        </div>
    );
}
