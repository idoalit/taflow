import type { Greeting } from '@/types';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { Card, CardKicker, PageHeader } from '@/components/industry/ui';
import { index as exams } from '@/routes/admin/exams';
import { index as monitoring } from '@/routes/admin/monitoring';
import { index as reports } from '@/routes/admin/reports';
import { index as students } from '@/routes/admin/students';

type Props = {
    greeting: Greeting;
    department: string;
    kpis: { label: string; value: string }[];
    alerts: { text: string; href: string }[];
};

const QUICK_ACTIONS = [
    { label: 'Kelola Mahasiswa', href: students.url() },
    { label: 'Monitoring Bimbingan', href: monitoring.url() },
    { label: 'Pelaksanaan Ujian', href: exams.url() },
    { label: 'Laporan & Rekap', href: reports.url() },
];

export default function AdminDashboard({
    greeting,
    department,
    kpis,
    alerts,
}: Props) {
    setLayoutProps({ title: 'Dashboard' });

    return (
        <>
            <Head title="Dashboard" />

            <div className="ind-page">
                <PageHeader
                    title={`${greeting.salutation}, ${greeting.name}`}
                    description={department}
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
                                    key={alert.text}
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
