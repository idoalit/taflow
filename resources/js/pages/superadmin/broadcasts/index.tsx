import { Head, setLayoutProps } from '@inertiajs/react';
import {
    BtnLink,
    DataTable,
    EmptyRow,
    PageHeader,
    Pagination,
    Tag,
} from '@/components/industry/ui';
import { create } from '@/routes/superadmin/broadcasts';
import type { Paginated } from '@/types';

type Broadcast = {
    id: number;
    title: string;
    message: string;
    url: string | null;
    audience: string;
    recipients: number;
    sender: string | null;
    sent_at: string | null;
};

export default function SuperAdminBroadcastIndex({
    broadcasts,
}: {
    broadcasts: Paginated<Broadcast>;
}) {
    setLayoutProps({ title: 'Siaran' });

    return (
        <>
            <Head title="Siaran" />

            <div className="ind-page">
                <PageHeader
                    title="Siaran"
                    description="Pengumuman yang dikirim ke lonceng notifikasi pengguna."
                    actions={
                        <BtnLink href={create()} variant="primary">
                            Kirim Siaran
                        </BtnLink>
                    }
                />

                <DataTable
                    head={
                        <tr>
                            <th>Siaran</th>
                            <th>Sasaran</th>
                            <th>Penerima</th>
                            <th>Dikirim</th>
                        </tr>
                    }
                >
                    {broadcasts.data.length === 0 ? (
                        <EmptyRow colSpan={4}>
                            Belum ada siaran yang dikirim.
                        </EmptyRow>
                    ) : (
                        broadcasts.data.map((broadcast) => (
                            <tr key={broadcast.id} className="align-top">
                                <td className="max-w-[420px]">
                                    <span className="font-semibold">
                                        {broadcast.title}
                                    </span>
                                    <span className="ind-muted block text-[12.5px] whitespace-pre-line">
                                        {broadcast.message}
                                    </span>
                                    {broadcast.url && (
                                        <span className="ind-muted block text-[12px] break-all">
                                            {broadcast.url}
                                        </span>
                                    )}
                                </td>
                                <td>
                                    <Tag
                                        label={broadcast.audience}
                                        tone="outline"
                                    />
                                </td>
                                <td className="ind-muted">
                                    {broadcast.recipients}
                                </td>
                                <td className="ind-muted whitespace-nowrap">
                                    {broadcast.sent_at}
                                    {broadcast.sender && (
                                        <span className="block text-[12px]">
                                            oleh {broadcast.sender}
                                        </span>
                                    )}
                                </td>
                            </tr>
                        ))
                    )}
                </DataTable>

                <Pagination page={broadcasts} unit="siaran" />
            </div>
        </>
    );
}
