import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { DocumentLink } from '@/components/industry/document-link';
import {
    Avatar,
    Btn,
    BtnLink,
    Card,
    CardKicker,
    Tag,
    Textarea,
} from '@/components/industry/ui';
import { index as guidanceSessions } from '@/routes/guidance-sessions';
import { show as alignmentShow } from '@/routes/perception-alignments';
import {
    approve as approveProposal,
    index as proposals,
    reject as rejectProposal,
} from '@/routes/proposals';
import type { ProposalDetail } from '@/types';

type Tab = 'overview' | 'content' | 'docs';

const TABS: { key: Tab; label: string }[] = [
    { key: 'overview', label: 'Ringkasan' },
    { key: 'content', label: 'Isi Proposal' },
    { key: 'docs', label: 'Dokumen & Riwayat' },
];

type Props = {
    proposal: ProposalDetail;
    can: { review: boolean };
    alignmentStatus: string | null;
};

export default function ProposalShow({
    proposal,
    can,
    alignmentStatus,
}: Props) {
    setLayoutProps({ title: 'Detail Proposal' });

    const [tab, setTab] = useState<Tab>('overview');
    const [rejecting, setRejecting] = useState(false);
    const rejectForm = useForm({ rejection_reason: '' });

    const contentSections = [
        { title: 'Latar Belakang', body: proposal.background },
        { title: 'Rumusan Masalah', body: proposal.problem_formulation },
        { title: 'Tujuan Penelitian', body: proposal.objectives },
        { title: 'Ruang Lingkup', body: proposal.scope },
        { title: 'Metodologi', body: proposal.methodology },
        { title: 'Kesesuaian Roadmap', body: proposal.roadmap_alignment },
    ].filter((section) => Boolean(section.body));

    return (
        <>
            <Head title={proposal.title} />

            <div className="flex flex-col gap-[var(--ind-space-4)]">
                <div>
                    <Link href={proposals()} className="text-[12.5px]">
                        ← Daftar Proposal
                    </Link>
                    <h3 style={{ margin: '6px 0 8px' }}>{proposal.title}</h3>
                    <div className="flex flex-wrap items-center gap-[10px]">
                        <Tag label={proposal.status_label} />
                        <span className="ind-muted text-[13px]">
                            Diajukan {proposal.submitted_at_short}
                        </span>
                    </div>
                </div>

                <div
                    className="flex gap-[var(--ind-space-4)]"
                    style={{ borderBottom: '1px solid var(--ind-divider)' }}
                >
                    {TABS.map((item) => (
                        <button
                            key={item.key}
                            type="button"
                            onClick={() => setTab(item.key)}
                            className="-mb-px cursor-pointer border-0 bg-transparent py-[10px] text-[14px]"
                            style={{
                                fontFamily: 'var(--ind-font-heading)',
                                fontWeight: item.key === tab ? 600 : 400,
                                color:
                                    item.key === tab
                                        ? 'var(--ind-accent-700)'
                                        : 'color-mix(in srgb, var(--ind-text) 60%, transparent)',
                                borderBottom: `2px solid ${item.key === tab ? 'var(--ind-accent)' : 'transparent'}`,
                                marginRight: 20,
                            }}
                        >
                            {item.label}
                        </button>
                    ))}
                </div>

                <div className="grid [grid-template-columns:minmax(0,1fr)_260px] items-start gap-[var(--ind-space-6)] max-lg:[grid-template-columns:minmax(0,1fr)]">
                    <div className="flex flex-col gap-[var(--ind-space-4)]">
                        {tab === 'overview' && (
                            <>
                                <div>
                                    <h4 className="mb-[var(--ind-space-3)]">
                                        Ringkasan
                                    </h4>
                                    <div className="grid [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))] gap-[var(--ind-space-3)]">
                                        <Detail
                                            label="Program Studi"
                                            value={proposal.student.department}
                                        />
                                        <Detail
                                            label="NIM"
                                            value={
                                                proposal.student.student_number
                                            }
                                        />
                                        <Detail
                                            label="Diajukan"
                                            value={proposal.submitted_at}
                                        />
                                        <Detail
                                            label="Disetujui"
                                            value={proposal.approved_at ?? '—'}
                                        />
                                    </div>
                                </div>

                                {proposal.alignment_summary && (
                                    <Card>
                                        <CardKicker>
                                            Hasil Persamaan Persepsi
                                        </CardKicker>
                                        <p className="ind-card-body m-0">
                                            {proposal.alignment_summary}
                                        </p>
                                    </Card>
                                )}

                                {proposal.rejection_reason && (
                                    <Card>
                                        <CardKicker>Catatan Revisi</CardKicker>
                                        <p className="ind-card-body m-0">
                                            {proposal.rejection_reason}
                                        </p>
                                    </Card>
                                )}
                            </>
                        )}

                        {tab === 'content' && (
                            <div className="flex flex-col gap-[var(--ind-space-4)]">
                                {contentSections.map((section) => (
                                    <div key={section.title}>
                                        <h6>{section.title}</h6>
                                        <p className="m-0 whitespace-pre-line">
                                            {section.body}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        )}

                        {tab === 'docs' && (
                            <div className="flex flex-col gap-[var(--ind-space-4)]">
                                <h4 className="m-0">Dokumen</h4>
                                {proposal.document ? (
                                    <Card className="flex-row items-center">
                                        <DocumentLink
                                            document={proposal.document}
                                            meta={`${proposal.document.size} · diunggah ${proposal.document.uploaded_at}`}
                                            className="flex-1"
                                        />
                                    </Card>
                                ) : (
                                    <p className="ind-muted m-0 text-[13px]">
                                        Belum ada dokumen.
                                    </p>
                                )}

                                <h4 className="m-0">Riwayat Status</h4>
                                <div className="flex flex-col gap-[var(--ind-space-3)]">
                                    {proposal.history.map((entry) => (
                                        <div
                                            key={entry.label + entry.detail}
                                            className="flex gap-[10px]"
                                        >
                                            <div
                                                className="mt-[6px] h-[7px] w-[7px] flex-shrink-0"
                                                style={{
                                                    background: entry.current
                                                        ? 'var(--ind-accent)'
                                                        : 'var(--ind-neutral-400)',
                                                }}
                                            />
                                            <div>
                                                <div className="text-[13px] font-semibold">
                                                    {entry.label}
                                                </div>
                                                <div className="ind-muted text-[12px]">
                                                    {entry.detail}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="flex flex-col gap-[var(--ind-space-4)]">
                        <div>
                            <h6 className="ind-muted mb-[var(--ind-space-3)]">
                                Pembimbing
                            </h6>
                            {proposal.supervisors.length === 0 ? (
                                <p className="ind-muted m-0 text-[12px]">
                                    Pembimbing belum ditetapkan admin prodi.
                                </p>
                            ) : (
                                <div className="flex flex-col gap-[var(--ind-space-3)]">
                                    {proposal.supervisors.map(
                                        (supervisor, index) => (
                                            <div
                                                key={supervisor.id}
                                                className="flex items-center gap-[10px]"
                                            >
                                                <Avatar
                                                    initials={
                                                        supervisor.initials
                                                    }
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
                                                        {
                                                            supervisor.position_label
                                                        }
                                                    </div>
                                                </div>
                                            </div>
                                        ),
                                    )}
                                </div>
                            )}
                        </div>

                        {can.review && (
                            <Card>
                                <CardKicker>Tinjauan Anda</CardKicker>
                                <p className="ind-card-body m-0">
                                    Setujui untuk memulai Persamaan Persepsi
                                    antar pembimbing.
                                </p>
                                <Btn
                                    variant="primary"
                                    block
                                    onClick={() =>
                                        router.post(
                                            approveProposal(proposal.id).url,
                                            {},
                                            {
                                                preserveScroll: true,
                                            },
                                        )
                                    }
                                >
                                    Setujui Proposal
                                </Btn>
                                <Btn
                                    block
                                    onClick={() =>
                                        setRejecting((value) => !value)
                                    }
                                >
                                    Minta Revisi
                                </Btn>

                                {rejecting && (
                                    <div className="flex flex-col gap-[var(--ind-space-2)]">
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
                                            placeholder="Jelaskan bagian yang perlu diperbaiki..."
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
                                            variant="primary"
                                            disabled={rejectForm.processing}
                                            onClick={() =>
                                                rejectForm.post(
                                                    rejectProposal(proposal.id)
                                                        .url,
                                                    {
                                                        preserveScroll: true,
                                                        onSuccess: () =>
                                                            setRejecting(false),
                                                    },
                                                )
                                            }
                                        >
                                            Kirim Permintaan Revisi
                                        </Btn>
                                    </div>
                                )}
                            </Card>
                        )}

                        {alignmentStatus && (
                            <Card>
                                <CardKicker>Persamaan Persepsi</CardKicker>
                                <p className="ind-card-body m-0">
                                    {alignmentStatus}
                                </p>
                                <BtnLink
                                    block
                                    href={alignmentShow(proposal.id)}
                                >
                                    Buka Persamaan Persepsi
                                </BtnLink>
                            </Card>
                        )}

                        {proposal.status === 'approved' && (
                            <Card>
                                <CardKicker>Langkah Berikutnya</CardKicker>
                                <p className="ind-card-body m-0">
                                    Proposal disetujui. Lanjutkan dengan sesi
                                    bimbingan rutin sebelum mengajukan ujian
                                    proposal.
                                </p>
                                <BtnLink
                                    variant="primary"
                                    block
                                    href={guidanceSessions()}
                                >
                                    Buka Sesi Bimbingan
                                </BtnLink>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <h6 className="ind-muted mb-[3px]">{label}</h6>
            <div className="text-[14px] font-semibold">{value}</div>
        </div>
    );
}
