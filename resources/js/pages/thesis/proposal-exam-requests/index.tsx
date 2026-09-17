import { Head, setLayoutProps } from '@inertiajs/react';
import {
    EligibilityCard,
    RequestStatusCard,
    RequestTable,
} from '@/components/industry/exam-request';
import { BtnLink, PageHeader } from '@/components/industry/ui';
import { create } from '@/routes/proposal-exam-requests';
import type { Eligibility, ExamRequestSummary } from '@/types';

type Props = {
    requests: ExamRequestSummary[];
    eligibility: Eligibility | null;
    canCreate: boolean;
};

export default function ProposalExamRequestIndex({
    requests,
    eligibility,
    canCreate,
}: Props) {
    setLayoutProps({ title: 'Pengajuan Ujian Proposal' });

    const latest = requests[0];

    return (
        <>
            <Head title="Pengajuan Ujian Proposal" />

            <div className="flex flex-col gap-[var(--ind-space-4)]">
                <PageHeader
                    title="Pengajuan Ujian Proposal"
                    description="Ajukan dan pantau status verifikasi ujian proposal Anda."
                    actions={
                        canCreate ? (
                            <BtnLink variant="primary" href={create()}>
                                + Ajukan Ujian Proposal
                            </BtnLink>
                        ) : undefined
                    }
                />

                {latest ? (
                    <RequestStatusCard request={latest} />
                ) : (
                    eligibility && (
                        <EligibilityCard
                            eligibility={eligibility}
                            kicker="Kelayakan Ujian"
                        />
                    )
                )}

                <RequestTable
                    requests={requests}
                    emptyMessage="Belum ada pengajuan ujian proposal."
                />
            </div>
        </>
    );
}
