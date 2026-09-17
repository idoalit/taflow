import { Head, setLayoutProps } from '@inertiajs/react';
import {
    EligibilityCard,
    RequestStatusCard,
    RequestTable,
} from '@/components/industry/exam-request';
import { BtnLink, PageHeader } from '@/components/industry/ui';
import { create } from '@/routes/research-exam-requests';
import type { Eligibility, ExamRequestSummary } from '@/types';

type Props = {
    requests: ExamRequestSummary[];
    eligibility: Eligibility | null;
    canCreate: boolean;
};

export default function ResearchExamRequestIndex({
    requests,
    eligibility,
    canCreate,
}: Props) {
    setLayoutProps({ title: 'Pengajuan Ujian Hasil Penelitian' });

    const latest = requests[0];

    return (
        <>
            <Head title="Pengajuan Ujian Hasil Penelitian" />

            <div className="flex flex-col gap-[var(--ind-space-4)]">
                <PageHeader
                    title="Pengajuan Ujian Hasil Penelitian"
                    description="Ajukan sidang akhir setelah penelitian dan penulisan laporan selesai."
                    actions={
                        canCreate ? (
                            <BtnLink variant="primary" href={create()}>
                                + Ajukan Ujian Hasil Penelitian
                            </BtnLink>
                        ) : undefined
                    }
                />

                {latest && <RequestStatusCard request={latest} />}

                {eligibility && (
                    <EligibilityCard
                        eligibility={eligibility}
                        kicker="Kesiapan Sidang"
                    />
                )}

                <RequestTable
                    requests={requests}
                    emptyMessage="Belum ada pengajuan ujian hasil penelitian."
                />
            </div>
        </>
    );
}
