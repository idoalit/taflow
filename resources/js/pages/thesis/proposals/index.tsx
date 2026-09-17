import { Head, router, setLayoutProps } from '@inertiajs/react';
import {
    BtnLink,
    DataTable,
    EmptyRow,
    PageHeader,
    Tag,
} from '@/components/industry/ui';
import { create, show } from '@/routes/proposals';
import type { ProposalSummary } from '@/types';

type Props = {
    proposals: ProposalSummary[];
    canCreate: boolean;
};

export default function ProposalIndex({ proposals, canCreate }: Props) {
    setLayoutProps({ title: 'Daftar Proposal' });

    return (
        <>
            <Head title="Daftar Proposal" />

            <div className="flex flex-col gap-[var(--ind-space-4)]">
                <PageHeader
                    title="Daftar Proposal"
                    description="Riwayat pengajuan proposal tugas akhir Anda."
                    actions={
                        canCreate ? (
                            <BtnLink variant="primary" href={create()}>
                                + Ajukan Proposal
                            </BtnLink>
                        ) : undefined
                    }
                />

                <DataTable
                    head={
                        <tr>
                            <th>Judul Proposal</th>
                            <th>Status</th>
                            <th>Diajukan</th>
                        </tr>
                    }
                >
                    {proposals.length === 0 ? (
                        <EmptyRow colSpan={3}>
                            Belum ada proposal yang diajukan.
                        </EmptyRow>
                    ) : (
                        proposals.map((proposal) => (
                            <tr
                                key={proposal.id}
                                className="cursor-pointer"
                                onClick={() => router.visit(show(proposal.id))}
                            >
                                <td className="font-semibold">
                                    {proposal.title}
                                </td>
                                <td>
                                    <Tag label={proposal.status_label} />
                                </td>
                                <td className="ind-muted">
                                    {proposal.submitted_at_short}
                                </td>
                            </tr>
                        ))
                    )}
                </DataTable>

                {!canCreate && proposals.length > 0 && (
                    <p className="ind-muted m-0 text-center text-[12px]">
                        Mahasiswa hanya dapat memiliki satu proposal aktif —
                        tombol "Ajukan Baru" otomatis tersembunyi selama
                        proposal ini berjalan.
                    </p>
                )}
            </div>
        </>
    );
}
