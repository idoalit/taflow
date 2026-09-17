import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { DocumentLink } from '@/components/industry/document-link';
import {
    Btn,
    Card,
    CardKicker,
    Field,
    Tag,
    Textarea,
} from '@/components/industry/ui';
import { index, verify } from '@/routes/admin/exam-requests';
import type { AdminExamRequestDetail } from '@/types';

type Props = {
    request: AdminExamRequestDetail;
    canVerify: boolean;
};

export default function AdminExamRequestShow({ request, canVerify }: Props) {
    setLayoutProps({ title: 'Detail Pengajuan Ujian' });

    const form = useForm({ decision: 'approved', admin_notes: '' });

    const submit = (decision: 'approved' | 'rejected') => {
        form.transform((data) => ({ ...data, decision }));
        form.post(
            verify({ stage: request.stage, examRequest: request.id }).url,
        );
    };

    return (
        <>
            <Head title={request.title} />

            <div className="ind-page max-w-[720px]">
                <div>
                    <Link
                        href={index({ query: { stage: request.stage } })}
                        className="text-[12.5px]"
                    >
                        ← Pengajuan Ujian
                    </Link>
                    <h3 style={{ margin: '6px 0 8px' }}>{request.title}</h3>
                    <div className="flex flex-wrap items-center gap-[10px]">
                        <Tag label={request.stage_label} tone="accent" />
                        <span className="ind-muted text-[13px]">
                            {request.student} · {request.student_number} · Sesi
                            selesai: {request.completed_sessions}/
                            {request.required_sessions}
                        </span>
                    </div>
                </div>

                <div className="grid [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))] gap-[var(--ind-space-3)]">
                    <Card>
                        <CardKicker>Verifikasi Pembimbing</CardKicker>
                        <div className="ind-card-title">
                            {request.supervisor_status_label}
                        </div>
                        {request.supervisor_notes && (
                            <div className="ind-card-meta">
                                {request.supervisor_notes}
                            </div>
                        )}
                    </Card>
                    <Card>
                        <CardKicker>Verifikasi Admin Prodi</CardKicker>
                        <div className="ind-card-title">
                            {request.admin_status_label}
                        </div>
                        {request.admin_notes && (
                            <div className="ind-card-meta">
                                {request.admin_notes}
                            </div>
                        )}
                    </Card>
                </div>

                {request.student_notes && (
                    <div className="ind-section">
                        <h6 className="ind-muted">Catatan Mahasiswa</h6>
                        <p className="m-0 text-[13.5px] whitespace-pre-line">
                            {request.student_notes}
                        </p>
                    </div>
                )}

                {request.documents.length > 0 && (
                    <div className="ind-section">
                        <h6 className="ind-muted">Dokumen Pendukung</h6>
                        <div className="flex flex-col gap-[var(--ind-space-2)]">
                            {request.documents.map((document) => (
                                <Card
                                    key={document.name}
                                    className="flex-row items-center"
                                >
                                    <DocumentLink
                                        document={document}
                                        className="flex-1"
                                    />
                                </Card>
                            ))}
                        </div>
                    </div>
                )}

                {canVerify ? (
                    <form
                        onSubmit={(event) => event.preventDefault()}
                        className="flex flex-col gap-[var(--ind-space-3)]"
                    >
                        <Field
                            label="Catatan Admin"
                            optional
                            error={form.errors.admin_notes}
                        >
                            <Textarea
                                rows={3}
                                value={form.data.admin_notes}
                                onChange={(event) =>
                                    form.setData(
                                        'admin_notes',
                                        event.target.value,
                                    )
                                }
                                placeholder="Kelengkapan berkas, periode ujian, atau alasan penolakan..."
                            />
                        </Field>

                        <div className="flex flex-wrap gap-[var(--ind-space-3)]">
                            <Btn
                                variant="primary"
                                disabled={form.processing}
                                onClick={() => submit('approved')}
                            >
                                Setujui Pengajuan
                            </Btn>
                            <Btn
                                variant="danger"
                                disabled={form.processing}
                                onClick={() => submit('rejected')}
                            >
                                Tolak Pengajuan
                            </Btn>
                        </div>
                    </form>
                ) : (
                    <p className="ind-muted m-0 text-[13px]">
                        {request.supervisor_status_label === 'Menunggu'
                            ? 'Menunggu verifikasi dosen pembimbing terlebih dahulu.'
                            : 'Pengajuan ini sudah diputuskan dan tidak dapat diubah.'}
                    </p>
                )}
            </div>
        </>
    );
}
