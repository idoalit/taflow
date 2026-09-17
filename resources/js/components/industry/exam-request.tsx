import { Clock } from 'lucide-react';
import {
    Card,
    CardKicker,
    DataTable,
    EmptyRow,
    Tag,
} from '@/components/industry/ui';
import type { Eligibility, ExamRequestSummary } from '@/types';

/**
 * The readiness card the design puts at the top of both exam request screens:
 * a headline verdict plus the individual checks behind it.
 */
export function EligibilityCard({
    eligibility,
    kicker,
    detailed = false,
}: {
    eligibility: Eligibility;
    kicker: string;
    detailed?: boolean;
}) {
    return (
        <Card
            style={{
                borderColor: eligibility.eligible
                    ? 'var(--ind-accent)'
                    : 'var(--ind-accent-2)',
            }}
        >
            <CardKicker>{kicker}</CardKicker>
            <div className="text-[14px] font-semibold">
                {eligibility.eligible
                    ? 'Memenuhi Syarat'
                    : 'Belum Memenuhi Syarat'}
            </div>

            {detailed ? (
                <>
                    <div className="mt-[2px] flex flex-col gap-[6px]">
                        {eligibility.checks.map((check) => (
                            <div
                                key={check.label}
                                className="flex items-center justify-between gap-3 text-[13px]"
                            >
                                <span>{check.label}</span>
                                <span
                                    className="font-semibold"
                                    style={{
                                        color: check.met
                                            ? 'var(--ind-status-success-fg)'
                                            : 'var(--ind-status-warning-fg)',
                                    }}
                                >
                                    {check.detail}
                                </span>
                            </div>
                        ))}
                    </div>
                    <div className="ind-muted mt-1 text-[12.5px]">
                        {eligibility.summary}
                    </div>
                </>
            ) : (
                <div className="ind-muted text-[13px]">
                    {eligibility.summary}
                </div>
            )}
        </Card>
    );
}

/** The "what is happening with my request" banner. */
export function RequestStatusCard({
    request,
}: {
    request: ExamRequestSummary;
}) {
    return (
        <Card className="flex-row items-start gap-3">
            <Clock
                size={18}
                strokeWidth={1.5}
                style={{ color: 'var(--ind-accent-700)' }}
            />
            <div>
                <div className="text-[14px] font-semibold">
                    {request.awaiting_label}
                </div>
                <div className="ind-muted mt-[3px] text-[13px]">
                    {request.status === 'pending'
                        ? `Pengajuan Anda tanggal ${request.submitted_at} sedang diverifikasi. Anda akan mendapat notifikasi setelah diproses.`
                        : `Pengajuan tanggal ${request.submitted_at} telah diproses pada ${request.verified_at ?? '—'}.`}
                </div>
                {request.exam && (
                    <div className="mt-2 text-[13px]">
                        Jadwal ujian:{' '}
                        <strong>{request.exam.scheduled_at}</strong>
                        {request.exam.venue && ` · ${request.exam.venue}`}
                    </div>
                )}
            </div>
        </Card>
    );
}

export function RequestTable({
    requests,
    emptyMessage,
}: {
    requests: ExamRequestSummary[];
    emptyMessage: string;
}) {
    return (
        <DataTable
            head={
                <tr>
                    <th>Status</th>
                    <th>Diajukan</th>
                    <th>Diverifikasi</th>
                </tr>
            }
        >
            {requests.length === 0 ? (
                <EmptyRow colSpan={3}>{emptyMessage}</EmptyRow>
            ) : (
                requests.map((request) => (
                    <tr key={request.id}>
                        <td>
                            <Tag
                                label={
                                    request.status === 'pending'
                                        ? 'Menunggu Verifikasi'
                                        : request.status_label
                                }
                            />
                        </td>
                        <td className="ind-muted">{request.submitted_at}</td>
                        <td className="ind-muted">
                            {request.verified_at ?? '-'}
                        </td>
                    </tr>
                ))
            )}
        </DataTable>
    );
}
