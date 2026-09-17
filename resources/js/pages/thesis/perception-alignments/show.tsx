import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    Avatar,
    Btn,
    Card,
    CardKicker,
    Tag,
    Textarea,
} from '@/components/industry/ui';
import { feedback, index, reject } from '@/routes/perception-alignments';
import type { ProposalDetail } from '@/types';

type Alignment = {
    id: number;
    supervisor_order: number;
    lecturer_name: string;
    lecturer_initials: string;
    status: 'pending' | 'approved' | 'rejected';
    feedback: string | null;
    responded_at: string | null;
    is_mine: boolean;
    is_my_turn: boolean;
};

type Props = {
    proposal: ProposalDetail;
    alignments: Alignment[];
    statusLabel: string;
};

const SLOT_LABEL: Record<number, string> = {
    1: 'Umpan Balik Pembimbing Utama',
    2: 'Umpan Balik Pembimbing Kedua',
};

export default function PerceptionAlignmentShow({
    proposal,
    alignments,
    statusLabel,
}: Props) {
    setLayoutProps({ title: 'Detail Persamaan Persepsi' });

    const [rejecting, setRejecting] = useState<number | null>(null);
    const feedbackForm = useForm({
        feedback: '',
        preparation_requirements: '',
    });
    const rejectForm = useForm({ rejection_reason: '' });

    return (
        <>
            <Head title={proposal.title} />

            <div className="flex flex-col gap-[var(--ind-space-4)]">
                <div>
                    <Link href={index()} className="text-[12.5px]">
                        ← Persamaan Persepsi
                    </Link>
                    <h3 style={{ margin: '6px 0 8px' }}>{proposal.title}</h3>
                    <div className="flex flex-wrap items-center gap-[10px]">
                        <span className="ind-muted text-[13px]">
                            {proposal.student.name} ·{' '}
                            {proposal.student.student_number}
                        </span>
                        <Tag label={statusLabel} />
                    </div>
                </div>

                {alignments.map((alignment) => (
                    <Card key={alignment.id}>
                        <CardKicker>
                            {SLOT_LABEL[alignment.supervisor_order] ??
                                `Umpan Balik Pembimbing ${alignment.supervisor_order}`}
                        </CardKicker>

                        <div className="flex items-center gap-[10px]">
                            <Avatar
                                initials={alignment.lecturer_initials}
                                size={26}
                                tone={
                                    alignment.supervisor_order === 1
                                        ? 'accent'
                                        : 'accent-2'
                                }
                            />
                            <span className="text-[13px] font-semibold">
                                {alignment.lecturer_name}
                            </span>
                            {alignment.responded_at && (
                                <span className="ind-muted text-[12px]">
                                    · {alignment.responded_at}
                                </span>
                            )}
                        </div>

                        {alignment.feedback && (
                            <p className="ind-card-body m-0 whitespace-pre-line">
                                {alignment.feedback}
                            </p>
                        )}

                        {alignment.status === 'pending' &&
                            !alignment.is_my_turn && (
                                <p className="ind-muted m-0 text-[13px]">
                                    {alignment.supervisor_order === 1
                                        ? 'Menunggu diisi.'
                                        : 'Menunggu Pembimbing Utama mengisi terlebih dahulu.'}
                                </p>
                            )}

                        {alignment.is_mine && alignment.is_my_turn && (
                            <div className="flex flex-col gap-[var(--ind-space-2)]">
                                <Textarea
                                    rows={3}
                                    value={feedbackForm.data.feedback}
                                    onChange={(event) =>
                                        feedbackForm.setData(
                                            'feedback',
                                            event.target.value,
                                        )
                                    }
                                    placeholder={
                                        alignment.supervisor_order === 1
                                            ? 'Kekuatan proposal, saran perbaikan, dan arahan bimbingan ke depan...'
                                            : 'Perspektif tambahan atau penguatan arahan...'
                                    }
                                />
                                {feedbackForm.errors.feedback && (
                                    <p
                                        className="m-0 text-[11.5px]"
                                        style={{
                                            color: 'var(--ind-status-error-fg)',
                                        }}
                                    >
                                        {feedbackForm.errors.feedback}
                                    </p>
                                )}

                                <div className="flex flex-wrap gap-[var(--ind-space-2)]">
                                    <Btn
                                        variant="primary"
                                        disabled={feedbackForm.processing}
                                        onClick={() =>
                                            feedbackForm.post(
                                                feedback(alignment.id).url,
                                                {
                                                    preserveScroll: true,
                                                },
                                            )
                                        }
                                    >
                                        Simpan Umpan Balik
                                    </Btn>
                                    <Btn
                                        onClick={() =>
                                            setRejecting(
                                                rejecting === alignment.id
                                                    ? null
                                                    : alignment.id,
                                            )
                                        }
                                    >
                                        Tolak Proposal
                                    </Btn>
                                </div>

                                {rejecting === alignment.id && (
                                    <div className="flex flex-col gap-[var(--ind-space-2)]">
                                        <CardKicker>
                                            Alasan Penolakan
                                        </CardKicker>
                                        <Textarea
                                            rows={3}
                                            value={
                                                rejectForm.data.rejection_reason
                                            }
                                            onChange={(event) =>
                                                rejectForm.setData(
                                                    'rejection_reason',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Jelaskan alasan penolakan secara jelas dan lengkap"
                                        />
                                        {rejectForm.errors.rejection_reason && (
                                            <p
                                                className="m-0 text-[11.5px]"
                                                style={{
                                                    color: 'var(--ind-status-error-fg)',
                                                }}
                                            >
                                                {
                                                    rejectForm.errors
                                                        .rejection_reason
                                                }
                                            </p>
                                        )}
                                        <Btn
                                            variant="danger"
                                            disabled={rejectForm.processing}
                                            onClick={() =>
                                                rejectForm.post(
                                                    reject(alignment.id).url,
                                                )
                                            }
                                        >
                                            Konfirmasi Tolak
                                        </Btn>
                                    </div>
                                )}
                            </div>
                        )}
                    </Card>
                ))}
            </div>
        </>
    );
}
