import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { EnterDepartment } from '@/components/industry/enter-department';
import {
    Card,
    CardKicker,
    DataTable,
    EmptyRow,
    PageHeader,
    Tag,
} from '@/components/industry/ui';
import { index as activityLogs } from '@/routes/superadmin/activity-logs';
import { create as createBroadcast } from '@/routes/superadmin/broadcasts';
import {
    create as createDepartment,
    show as showDepartment,
} from '@/routes/superadmin/departments';
import { index as users } from '@/routes/superadmin/users';
import type { Greeting } from '@/types';

type Props = {
    greeting: Greeting;
    kpis: { label: string; value: string }[];
    alerts: { text: string; href: string }[];
    departments: {
        id: number;
        name: string;
        code: string;
        is_active: boolean;
        students: number;
        lecturers: number;
        admins: number;
        workspace: string;
    }[];
};

const QUICK_ACTIONS = [
    { label: 'Tambah Program Studi', href: createDepartment.url() },
    { label: 'Kelola Pengguna', href: users.url() },
    { label: 'Kirim Siaran', href: createBroadcast.url() },
    { label: 'Log Aktivitas', href: activityLogs.url() },
];

export default function SuperAdminDashboard({
    greeting,
    kpis,
    alerts,
    departments,
}: Props) {
    setLayoutProps({ title: 'Dashboard' });

    return (
        <>
            <Head title="Dashboard" />

            <div className="ind-page">
                <PageHeader
                    title={`${greeting.salutation}, ${greeting.name}`}
                    description="Seluruh program studi"
                />

                <div className="grid [grid-template-columns:repeat(auto-fit,minmax(170px,1fr))] gap-[var(--ind-space-3)]">
                    {kpis.map((kpi) => (
                        <Card key={kpi.label}>
                            <CardKicker>{kpi.label}</CardKicker>
                            <div className="ind-stat">{kpi.value}</div>
                        </Card>
                    ))}
                </div>

                {alerts.length > 0 && (
                    <div className="ind-section">
                        <h6>Perlu Perhatian</h6>
                        <div className="flex flex-col gap-[var(--ind-space-3)]">
                            {alerts.map((alert) => (
                                <Link
                                    key={alert.href}
                                    href={alert.href}
                                    className="ind-card ind-bp flex-row items-center gap-3 no-underline"
                                >
                                    <TriangleAlert
                                        size={18}
                                        strokeWidth={1.5}
                                        style={{
                                            color: 'var(--ind-accent-800)',
                                        }}
                                    />
                                    <span className="text-[13.5px] font-semibold">
                                        {alert.text}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}

                <div className="ind-section">
                    <h6>Program Studi</h6>
                    <DataTable
                        head={
                            <tr>
                                <th>Program Studi</th>
                                <th>Mahasiswa</th>
                                <th>Dosen</th>
                                <th>Admin</th>
                                <th>Status</th>
                                <th />
                            </tr>
                        }
                    >
                        {departments.length === 0 ? (
                            <EmptyRow colSpan={6}>
                                Belum ada program studi. Tambahkan yang pertama
                                untuk memulai.
                            </EmptyRow>
                        ) : (
                            departments.map((department) => (
                                <tr
                                    key={department.id}
                                    className="cursor-pointer"
                                    onClick={() =>
                                        router.visit(
                                            showDepartment(department.id),
                                        )
                                    }
                                >
                                    <td className="font-semibold">
                                        {department.name}
                                        <span className="ind-muted block text-[12px]">
                                            {department.code}
                                        </span>
                                    </td>
                                    <td className="ind-muted">
                                        {department.students}
                                    </td>
                                    <td className="ind-muted">
                                        {department.lecturers}
                                    </td>
                                    <td className="ind-muted">
                                        {department.admins}
                                    </td>
                                    <td>
                                        <Tag
                                            label={
                                                department.is_active
                                                    ? 'Aktif'
                                                    : 'Tidak Aktif'
                                            }
                                        />
                                    </td>
                                    <td className="text-right">
                                        <EnterDepartment
                                            workspace={department.workspace}
                                            name={department.name}
                                            compact
                                        />
                                    </td>
                                </tr>
                            ))
                        )}
                    </DataTable>
                </div>

                <div className="ind-section">
                    <h6>Aksi Cepat</h6>
                    <div className="grid [grid-template-columns:repeat(auto-fit,minmax(200px,1fr))] gap-[var(--ind-space-3)]">
                        {QUICK_ACTIONS.map((action) => (
                            <Link
                                key={action.label}
                                href={action.href}
                                className="ind-card ind-bp text-[13.5px] font-semibold no-underline"
                            >
                                {action.label}
                            </Link>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}
