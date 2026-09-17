import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import {
    BookOpen,
    CalendarDays,
    CheckCircle2,
    FilePlus2,
    FileText,
    MessageSquare,
    SquareCheckBig,
} from 'lucide-react';
import { BtnLink, Card, CardKicker, Tag } from '@/components/industry/ui';
import { index as guidanceSessions } from '@/routes/guidance-sessions';
import { index as proposalExamRequests } from '@/routes/proposal-exam-requests';
import { index as researchLogbooks } from '@/routes/research-logbooks';
import {
    create as createProposal,
    index as proposals,
    show as showProposal,
} from '@/routes/proposals';
import type {
    Greeting,
    GuidanceSessionSummary,
    ProposalSummary,
} from '@/types';

type Props = {
    greeting: Greeting;
    proposal: ProposalSummary | null;
    canCreateProposal: boolean;
    primarySupervisor: string | null;
    upcomingSession: GuidanceSessionSummary | null;
    activity: { text: string; time: string }[];
};

/**
 * The proposal shortcut changes with the student's state: offering "Ajukan
 * Proposal Baru" to someone who already has one active only leads to a refusal,
 * so it points at the existing proposal instead.
 */
function quickActions(
    canCreateProposal: boolean,
    proposal: ProposalSummary | null,
    requiresProposalExam: boolean,
) {
    return [
        canCreateProposal || proposal === null
            ? {
                  label: 'Ajukan Proposal Baru',
                  href: createProposal.url(),
                  Icon: FilePlus2,
              }
            : {
                  label: 'Lihat Proposal Saya',
                  href: showProposal.url(proposal.id),
                  Icon: FileText,
              },
        {
            label: 'Sesi Bimbingan',
            href: guidanceSessions.url(),
            Icon: MessageSquare,
        },
        // A program studi that runs no ujian proposal would only be offered a
        // refusal here, so the shortcut points at the research logbook instead.
        requiresProposalExam
            ? {
                  label: 'Ajukan Ujian Proposal',
                  href: proposalExamRequests.url(),
                  Icon: SquareCheckBig,
              }
            : {
                  label: 'Logbook Penelitian',
                  href: researchLogbooks.url(),
                  Icon: BookOpen,
              },
    ];
}

export default function StudentDashboard({
    greeting,
    proposal,
    canCreateProposal,
    primarySupervisor,
    upcomingSession,
    activity,
}: Props) {
    setLayoutProps({ title: 'Dashboard Mahasiswa' });

    const thesis = usePage().props.thesis;

    return (
        <>
            <Head title="Dashboard" />

            <div className="flex flex-col gap-[var(--ind-space-6)]">
                <div>
                    <h2 className="mb-[6px]">
                        {greeting.salutation}, {greeting.name}
                    </h2>
                    <p className="ind-muted m-0 text-[15px]">
                        Progres skripsi dan agenda bimbingan Anda dirangkum di
                        sini.
                    </p>
                </div>

                <div
                    className="flex flex-wrap items-end justify-between gap-5 pb-[var(--ind-space-4)]"
                    style={{ borderBottom: '1px solid var(--ind-divider)' }}
                >
                    <div className="flex flex-col gap-2">
                        <span className="ind-kicker">
                            Status proposal aktif
                        </span>
                        {proposal ? (
                            <>
                                <h3 className="m-0">{proposal.title}</h3>
                                <div className="flex flex-wrap items-center gap-[10px]">
                                    <Tag label={proposal.status_label} />
                                    {primarySupervisor && (
                                        <span className="ind-muted text-[13px]">
                                            Pembimbing: {primarySupervisor}
                                        </span>
                                    )}
                                </div>
                            </>
                        ) : (
                            <>
                                <h3 className="m-0">
                                    Belum ada proposal aktif
                                </h3>
                                <p className="ind-muted m-0 text-[13px]">
                                    Ajukan proposal tugas akhir untuk memulai
                                    proses bimbingan.
                                </p>
                            </>
                        )}
                    </div>

                    {proposal ? (
                        <BtnLink
                            variant="primary"
                            href={showProposal(proposal.id)}
                        >
                            Lihat Detail Proposal
                        </BtnLink>
                    ) : (
                        <BtnLink variant="primary" href={createProposal()}>
                            Ajukan Proposal
                        </BtnLink>
                    )}
                </div>

                {upcomingSession && (
                    <div className="flex items-center gap-[14px]">
                        <CalendarDays
                            size={18}
                            strokeWidth={1.5}
                            style={{ color: 'var(--ind-accent-700)' }}
                        />
                        <div className="min-w-0 flex-1">
                            <div className="text-[14px] font-semibold">
                                Agenda terdekat: {upcomingSession.title}
                            </div>
                            <div className="ind-muted mt-[2px] text-[13px]">
                                {upcomingSession.meta} ·{' '}
                                {upcomingSession.guidance_type_label}
                            </div>
                        </div>
                        <BtnLink href={guidanceSessions()}>Detail</BtnLink>
                    </div>
                )}

                <div className="grid [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))] gap-[var(--ind-space-4)]">
                    {quickActions(
                        canCreateProposal,
                        proposal,
                        thesis?.requiresProposalExam ?? true,
                    ).map(({ label, href, Icon }) => (
                        <Link
                            key={label}
                            href={href}
                            className="ind-card ind-bp flex-row items-center"
                        >
                            <i className="ind-corner tl" />
                            <i className="ind-corner tr" />
                            <i className="ind-corner bl" />
                            <i className="ind-corner br" />
                            <Icon
                                size={18}
                                strokeWidth={1.5}
                                style={{ color: 'var(--ind-accent-700)' }}
                            />
                            <span
                                className="text-[14px] font-semibold"
                                style={{
                                    fontFamily: 'var(--ind-font-heading)',
                                }}
                            >
                                {label}
                            </span>
                        </Link>
                    ))}
                </div>

                <div className="grid [grid-template-columns:minmax(0,1.15fr)_minmax(0,0.85fr)] gap-[var(--ind-space-6)] max-md:[grid-template-columns:minmax(0,1fr)]">
                    <div>
                        <div className="mb-[var(--ind-space-3)] flex items-baseline justify-between">
                            <h4 className="m-0">Proposal Saya</h4>
                            <Link href={proposals()} className="text-[13px]">
                                Lihat semua →
                            </Link>
                        </div>

                        {proposal ? (
                            <Link
                                href={showProposal(proposal.id)}
                                className="ind-card ind-bp"
                            >
                                <i className="ind-corner tl" />
                                <i className="ind-corner tr" />
                                <i className="ind-corner bl" />
                                <i className="ind-corner br" />
                                <CardKicker>
                                    Diajukan {proposal.submitted_at_short}
                                </CardKicker>
                                <div className="ind-card-title">
                                    {proposal.title}
                                </div>
                                <div className="ind-card-meta">
                                    <Tag label={proposal.status_label} />
                                </div>
                            </Link>
                        ) : (
                            <Card>
                                <CardKicker>Belum ada</CardKicker>
                                <p className="ind-card-body m-0">
                                    Anda belum mengajukan proposal tugas akhir.
                                </p>
                            </Card>
                        )}
                    </div>

                    <div>
                        <h4 className="mb-[var(--ind-space-3)]">
                            Aktivitas Terbaru
                        </h4>
                        {activity.length === 0 ? (
                            <p className="ind-muted m-0 text-[13px]">
                                Belum ada aktivitas.
                            </p>
                        ) : (
                            <div className="flex flex-col gap-[var(--ind-space-3)]">
                                {activity.map((entry) => (
                                    <div key={`${entry.text}-${entry.time}`}>
                                        <div className="flex items-start gap-2 text-[13px] font-semibold">
                                            <CheckCircle2
                                                size={14}
                                                strokeWidth={1.5}
                                                className="mt-[3px] flex-shrink-0"
                                                style={{
                                                    color: 'var(--ind-accent-700)',
                                                }}
                                            />
                                            <span>{entry.text}</span>
                                        </div>
                                        <div className="ind-muted mt-[2px] pl-[22px] text-[12px]">
                                            {entry.time}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
