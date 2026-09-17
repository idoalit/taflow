import type { ExamRequestDocument, ExamStage } from '@/types';
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
import { index, verify } from '@/routes/exam-verifications';

type Props = {
    request: {
        id: number;
        stage: ExamStage;
        stage_label: string;
        student: string;
        student_number: string;
        department: string;
        title: string;
        supervisor_status_label: string;
        supervisor_notes: string | null;
        student_notes: string | null;
        submitted_at: string;
        completed_sessions: number;
        required_sessions: number;
        documents: ExamRequestDocument[];
    };
    canVerify: boolean;
};

export default function ExamVerificationShow({ request, canVerify }: Props) {
    setLayoutProps({ title: 'Detail Pengajuan Ujian' });

    const form = useForm({ decision: 'approved', supervisor_notes: '' });

    const submit = (decision: 'approved' | 'rejected') => {
        form.transform((data) => ({ ...data, decision }));
        form.post(
            verify({ stage: request.stage, examRequest: request.id }).url,
        );
    };

    return (
        <>
            <Head title={request.title} />

            <div className="flex max-w-[720px] flex-col gap-[var(--ind-space-4)]">
                <div>
                    <Link href={index()} className="text-[12.5px]">
                        ← Verifikasi Pengajuan Ujian
                    </Link>
                    <h3 style={{ margin: '6px 0 8px' }}>{request.title}</h3>
                    <div className="flex flex-wrap items-center gap-[10px]">
                        <Tag label={request.stage_label} tone="accent" />
                        <span className="ind-muted text-[13px]">
                            {request.student} · {request.student_number} · Sesi
                            selesai: {request.completed_sessions}/
                            {request.required_sessions}
                        </span>
                        <Tag label={request.supervisor_status_label} />
                    </div>
                </div>

                <Card>
                    <CardKicker>Catatan dari Mahasiswa</CardKicker>
                    <p className="ind-card-body m-0 whitespace-pre-line">
                        {request.student_notes ?? 'Tidak ada catatan.'}
                    </p>
                </Card>

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

                {request.supervisor_notes && (
                    <div className="ind-section">
                        <h6 className="ind-muted">Catatan Verifikasi</h6>
                        <p className="m-0 whitespace-pre-line">
                            {request.supervisor_notes}
                        </p>
                    </div>
                )}

                {canVerify && (
                    <>
                        <Field
                            label="Catatan Verifikasi"
                            optional
                            error={form.errors.supervisor_notes}
                        >
                            <Textarea
                                rows={2}
                                value={form.data.supervisor_notes}
                                onChange={(event) =>
                                    form.setData(
                                        'supervisor_notes',
                                        event.target.value,
                                    )
                                }
                                placeholder="Arahan untuk panel penguji atau catatan kesiapan mahasiswa"
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
                                disabled={form.processing}
                                onClick={() => submit('rejected')}
                            >
                                Tolak Pengajuan
                            </Btn>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}
