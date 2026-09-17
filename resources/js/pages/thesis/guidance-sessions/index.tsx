import { Head, router, setLayoutProps, usePage } from '@inertiajs/react';
import { CalendarDays, CheckCircle2, Clock, XCircle } from 'lucide-react';
import { BlueprintCorners } from '@/components/industry/blueprint';
import {
    BtnLink,
    DataTable,
    EmptyRow,
    PageHeader,
    Seg,
    Tabs,
    Tag,
} from '@/components/industry/ui';
import { index as guidanceCards } from '@/routes/guidance-cards';
import { create, index, show } from '@/routes/guidance-sessions';
import type { GuidanceSessionSummary } from '@/types';

type Tab = { value: string; label: string; count: number };
type FilterOption = { value: string; label: string };

type Props = {
    sessions: GuidanceSessionSummary[];
    filters: { tab: string; stage: string };
    tabs: Tab[];
    filterOptions: { stages: FilterOption[] };
    canCreate: boolean;
};

const STATUS_ICON = {
    scheduled: Clock,
    ongoing: CalendarDays,
    completed: CheckCircle2,
    cancelled: XCircle,
} as const;

export default function GuidanceSessionIndex({
    sessions,
    filters,
    tabs,
    filterOptions,
    canCreate,
}: Props) {
    setLayoutProps({ title: 'Sesi Bimbingan' });

    // A supervisor works through many students' sessions, so they get a dense
    // table; a student has a handful of their own and gets cards.
    const isStudent = usePage().props.auth.user.role === 'student';

    const go = (next: Partial<Props['filters']>) =>
        router.get(
            index().url,
            { ...filters, ...next },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Sesi Bimbingan" />

            <div className="flex flex-col gap-[var(--ind-space-4)]">
                <PageHeader
                    title="Sesi Bimbingan"
                    description={
                        isStudent
                            ? 'Jadwal dan riwayat sesi bimbingan dengan pembimbing Anda.'
                            : 'Sesi bimbingan dari seluruh mahasiswa bimbingan Anda.'
                    }
                    actions={
                        <>
                            <BtnLink href={guidanceCards()}>
                                Cetak Kartu Bimbingan
                            </BtnLink>
                            {canCreate && (
                                <BtnLink variant="primary" href={create()}>
                                    + Buat Sesi Bimbingan
                                </BtnLink>
                            )}
                        </>
                    }
                />

                <Tabs
                    label="Status sesi"
                    value={filters.tab}
                    options={tabs.map((tab) => ({
                        value: tab.value,
                        label: tab.label,
                        count: tab.count,
                    }))}
                    onChange={(tab) => go({ tab })}
                />

                <Seg
                    name="Tahap bimbingan"
                    value={filters.stage}
                    options={filterOptions.stages}
                    onChange={(stage) => go({ stage })}
                />

                {isStudent ? (
                    <StudentList sessions={sessions} />
                ) : (
                    <SupervisorTable sessions={sessions} />
                )}
            </div>
        </>
    );
}

function SupervisorTable({ sessions }: { sessions: GuidanceSessionSummary[] }) {
    return (
        <DataTable
            head={
                <tr>
                    <th>Mahasiswa</th>
                    <th>Judul</th>
                    <th>Tahapan</th>
                    <th>Jenis</th>
                    <th>Status</th>
                    <th>Jadwal</th>
                </tr>
            }
        >
            {sessions.length === 0 ? (
                <EmptyRow colSpan={6}>
                    Tidak ada sesi bimbingan pada tab ini.
                </EmptyRow>
            ) : (
                sessions.map((session) => (
                    <tr
                        key={session.id}
                        className="cursor-pointer"
                        onClick={() => router.visit(show(session.id))}
                    >
                        <td className="font-semibold">
                            {session.student?.name ?? '—'}
                            <span className="ind-muted block text-[12px]">
                                {session.student?.student_number}
                            </span>
                        </td>
                        <td>{session.title}</td>
                        <td className="ind-muted">{session.stage_label}</td>
                        <td>
                            <Tag
                                label={session.guidance_type_label}
                                tone={
                                    session.guidance_type === 'online_async'
                                        ? 'accent'
                                        : 'outline'
                                }
                            />
                        </td>
                        <td>
                            <Tag label={session.status_label} />
                        </td>
                        <td className="ind-muted">{session.meta}</td>
                    </tr>
                ))
            )}
        </DataTable>
    );
}

function StudentList({ sessions }: { sessions: GuidanceSessionSummary[] }) {
    if (sessions.length === 0) {
        return (
            <p className="ind-muted m-0 py-[var(--ind-space-8)] text-center text-[13px]">
                Tidak ada sesi bimbingan pada tab ini.
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-[var(--ind-space-2)]">
            {sessions.map((session) => {
                const Icon =
                    STATUS_ICON[session.status as keyof typeof STATUS_ICON] ??
                    CalendarDays;

                return (
                    <div
                        key={session.id}
                        role="button"
                        tabIndex={0}
                        onClick={() => router.visit(show(session.id))}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                router.visit(show(session.id));
                            }
                        }}
                        className="ind-card ind-bp cursor-pointer flex-row items-center gap-[14px]"
                    >
                        <BlueprintCorners />
                        <Icon
                            size={18}
                            strokeWidth={1.5}
                            style={{ color: 'var(--ind-accent-700)' }}
                        />
                        <div className="min-w-0 flex-1">
                            <span className="ind-kicker">
                                {session.stage_label}
                            </span>
                            <div className="text-[14px] font-semibold">
                                {session.title}
                            </div>
                            <div className="ind-muted mt-[2px] text-[12px]">
                                {session.meta}
                            </div>
                        </div>
                        <Tag
                            label={session.guidance_type_label}
                            tone={
                                session.guidance_type === 'online_async'
                                    ? 'accent'
                                    : 'outline'
                            }
                        />
                        <Tag label={session.status_label} />
                    </div>
                );
            })}
        </div>
    );
}
