import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useRef, useState } from 'react';
import {
    Btn,
    DataTable,
    EmptyRow,
    Field,
    Input,
    PageHeader,
    Pagination,
} from '@/components/industry/ui';
import { index } from '@/routes/superadmin/activity-logs';
import type { Paginated, SelectOption } from '@/types';

type Log = {
    id: number;
    action_label: string;
    actor: string | null;
    actor_email: string | null;
    subject: string;
    reason: string | null;
    changes: { key: string; value: string }[];
    ip_address: string | null;
    time: string | null;
};

type Filters = { action: string; actor: string; from: string; to: string };

const SEARCH_DELAY = 300;

export default function SuperAdminActivityLogIndex({
    logs,
    filters,
    actions,
}: {
    logs: Paginated<Log>;
    filters: Filters;
    actions: SelectOption[];
}) {
    setLayoutProps({ title: 'Log Aktivitas' });

    const [actor, setActor] = useState(filters.actor);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const filtered =
        filters.action !== 'all' ||
        filters.actor !== '' ||
        filters.from !== '' ||
        filters.to !== '';

    const apply = (next: Partial<Filters>) => {
        const merged = { ...filters, actor, ...next };

        router.get(
            index.url(),
            {
                action: merged.action === 'all' ? undefined : merged.action,
                actor: merged.actor || undefined,
                from: merged.from || undefined,
                to: merged.to || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title="Log Aktivitas" />

            <div className="ind-page">
                <PageHeader
                    title="Log Aktivitas"
                    description="Jejak keputusan dan perubahan penting: siapa, kapan, dan mengapa. Catatan ini tidak dapat diubah."
                />

                <div className="flex flex-wrap items-end gap-[var(--ind-space-3)]">
                    <Field label="Aksi">
                        <select
                            className="ind-input min-w-[200px]"
                            value={filters.action}
                            onChange={(event) =>
                                apply({ action: event.target.value })
                            }
                        >
                            <option value="all">Semua Aksi</option>
                            {actions.map((action) => (
                                <option key={action.value} value={action.value}>
                                    {action.label}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Pelaku">
                        <Input
                            value={actor}
                            onChange={(event) => {
                                const value = event.target.value;

                                setActor(value);

                                if (timer.current !== null) {
                                    clearTimeout(timer.current);
                                }

                                timer.current = setTimeout(
                                    () => apply({ actor: value }),
                                    SEARCH_DELAY,
                                );
                            }}
                            placeholder="Nama atau surel..."
                        />
                    </Field>

                    <Field label="Dari Tanggal">
                        <Input
                            type="date"
                            value={filters.from}
                            onChange={(event) =>
                                apply({ from: event.target.value })
                            }
                        />
                    </Field>

                    <Field label="Sampai Tanggal">
                        <Input
                            type="date"
                            value={filters.to}
                            onChange={(event) =>
                                apply({ to: event.target.value })
                            }
                        />
                    </Field>

                    {filtered && (
                        <Btn
                            variant="ghost"
                            onClick={() => {
                                setActor('');
                                router.get(index.url());
                            }}
                        >
                            Hapus Filter
                        </Btn>
                    )}
                </div>

                <DataTable
                    head={
                        <tr>
                            <th>Waktu</th>
                            <th>Pelaku</th>
                            <th>Aksi</th>
                            <th>Rincian</th>
                        </tr>
                    }
                >
                    {logs.data.length === 0 ? (
                        <EmptyRow colSpan={4}>
                            {filtered
                                ? 'Tidak ada catatan yang cocok dengan filter ini.'
                                : 'Belum ada aktivitas yang tercatat.'}
                        </EmptyRow>
                    ) : (
                        logs.data.map((log) => (
                            <tr key={log.id} className="align-top">
                                <td className="ind-muted whitespace-nowrap">
                                    {log.time}
                                </td>
                                <td>
                                    {log.actor ?? (
                                        <span className="ind-muted">
                                            Akun sudah dihapus
                                        </span>
                                    )}
                                    {log.actor_email && (
                                        <span className="ind-muted block text-[12px]">
                                            {log.actor_email}
                                        </span>
                                    )}
                                    {log.ip_address && (
                                        <span className="ind-muted block text-[12px]">
                                            {log.ip_address}
                                        </span>
                                    )}
                                </td>
                                <td>
                                    <span className="font-semibold">
                                        {log.action_label}
                                    </span>
                                    <span className="ind-muted block text-[12px]">
                                        {log.subject}
                                    </span>
                                </td>
                                <td className="max-w-[340px] text-[12.5px]">
                                    {log.reason && (
                                        <p className="m-0 mb-1">
                                            <span className="ind-muted">
                                                Alasan:{' '}
                                            </span>
                                            {log.reason}
                                        </p>
                                    )}
                                    {log.changes.map((change) => (
                                        <p
                                            key={change.key}
                                            className="m-0 break-words"
                                        >
                                            <span className="ind-muted">
                                                {change.key}:{' '}
                                            </span>
                                            {change.value}
                                        </p>
                                    ))}
                                    {!log.reason &&
                                        log.changes.length === 0 && (
                                            <span className="ind-muted">—</span>
                                        )}
                                </td>
                            </tr>
                        ))
                    )}
                </DataTable>

                <Pagination page={logs} unit="catatan" />
            </div>
        </>
    );
}
