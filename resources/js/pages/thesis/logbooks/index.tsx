import { Head, setLayoutProps } from '@inertiajs/react';
import { DocumentChip } from '@/components/industry/document-link';
import {
    BtnLink,
    Card,
    CardKicker,
    PageHeader,
    Tag,
} from '@/components/industry/ui';
import { index as guidanceCards } from '@/routes/guidance-cards';
import { create } from '@/routes/research-logbooks';
import type { LogbookEntry } from '@/types';

type Props = {
    entries: LogbookEntry[];
    stats: { latest_progress: number; total: number; last_date: string };
    canCreate: boolean;
    lockReason: string | null;
};

export default function LogbookIndex({
    entries,
    stats,
    canCreate,
    lockReason,
}: Props) {
    setLayoutProps({ title: 'Logbook Penelitian' });

    return (
        <>
            <Head title="Logbook Penelitian" />

            <div className="flex flex-col gap-[var(--ind-space-4)]">
                <PageHeader
                    title="Logbook Penelitian"
                    description="Catatan kegiatan penelitian Anda — dapat dipantau oleh dosen pembimbing dan admin prodi."
                    actions={
                        <>
                            <BtnLink href={guidanceCards()}>
                                Cetak Kartu Bimbingan
                            </BtnLink>
                            {canCreate && (
                                <BtnLink variant="primary" href={create()}>
                                    + Tambah Catatan
                                </BtnLink>
                            )}
                        </>
                    }
                />

                {lockReason && (
                    <Card>
                        <CardKicker>Logbook belum dapat diisi</CardKicker>
                        <p className="ind-card-body m-0">{lockReason}</p>
                    </Card>
                )}

                <div className="grid [grid-template-columns:repeat(auto-fit,minmax(200px,1fr))] gap-[var(--ind-space-4)]">
                    <Stat
                        kicker="Progres Terakhir"
                        value={`${stats.latest_progress}%`}
                    />
                    <Stat kicker="Total Catatan" value={String(stats.total)} />
                    <Stat
                        kicker="Update Terakhir"
                        value={stats.last_date}
                        small
                    />
                </div>

                {entries.length === 0 ? (
                    <p className="ind-muted m-0 py-[var(--ind-space-8)] text-center text-[13px]">
                        {lockReason
                            ? 'Catatan penelitian akan muncul di sini setelah Anda memasuki tahap penelitian.'
                            : 'Belum ada catatan logbook.'}
                    </p>
                ) : (
                    <div className="mt-[var(--ind-space-2)] flex flex-col gap-[var(--ind-space-3)]">
                        {entries.map((entry) => (
                            <div
                                key={entry.id}
                                className="grid [grid-template-columns:96px_minmax(0,1fr)] gap-[var(--ind-space-4)] max-sm:[grid-template-columns:minmax(0,1fr)]"
                            >
                                <div className="text-right max-sm:text-left">
                                    <div
                                        className="text-[15px] font-semibold"
                                        style={{
                                            fontFamily:
                                                'var(--ind-font-heading)',
                                        }}
                                    >
                                        {entry.day}
                                    </div>
                                    <div className="ind-muted text-[11.5px] tracking-[0.06em] uppercase">
                                        {entry.month_year}
                                    </div>
                                </div>

                                <Card>
                                    <div className="flex items-center justify-between gap-[10px]">
                                        <Tag
                                            label={`Progres ${entry.progress}%`}
                                            tone="accent"
                                        />
                                        <span className="ind-muted text-[11.5px]">
                                            {entry.weekday}
                                        </span>
                                    </div>
                                    <p className="ind-card-body mt-1 whitespace-pre-line">
                                        {entry.description}
                                    </p>
                                    {entry.attachment && (
                                        <DocumentChip
                                            document={entry.attachment}
                                        />
                                    )}
                                </Card>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function Stat({
    kicker,
    value,
    small = false,
}: {
    kicker: string;
    value: string;
    small?: boolean;
}) {
    return (
        <Card>
            <CardKicker>{kicker}</CardKicker>
            <div
                className="font-semibold"
                style={{
                    fontFamily: 'var(--ind-font-heading)',
                    fontSize: small ? 20 : 28,
                }}
            >
                {value}
            </div>
        </Card>
    );
}
