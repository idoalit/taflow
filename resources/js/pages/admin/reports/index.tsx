import { Head, setLayoutProps } from '@inertiajs/react';
import {
    Card,
    CardKicker,
    DataTable,
    PageHeader,
} from '@/components/industry/ui';

type Props = {
    department: string;
    students: {
        total: number;
        by_status: { label: string; count: number }[];
        without_supervisor: number;
    };
    proposals: { label: string; count: number }[];
    guidance: { total: number; completed: number };
    exams: {
        stage_label: string;
        scheduled: number;
        completed: number;
        passed: number;
        failed: number;
        pending_requests: number;
    }[];
};

export default function AdminReportIndex({
    department,
    students,
    proposals,
    guidance,
    exams,
}: Props) {
    setLayoutProps({ title: 'Laporan & Rekap' });

    return (
        <>
            <Head title="Laporan & Rekap" />

            <div className="ind-page">
                <PageHeader
                    title="Laporan & Rekap"
                    description={`Rekapitulasi ${department}, dihitung langsung dari data berjalan.`}
                />

                <div className="grid [grid-template-columns:repeat(auto-fit,minmax(190px,1fr))] gap-[var(--ind-space-3)]">
                    <Card>
                        <CardKicker>Total Mahasiswa</CardKicker>
                        <div className="ind-stat">{students.total}</div>
                    </Card>
                    <Card>
                        <CardKicker>Belum Ada Pembimbing</CardKicker>
                        <div className="ind-stat">
                            {students.without_supervisor}
                        </div>
                    </Card>
                    <Card>
                        <CardKicker>Total Sesi Bimbingan</CardKicker>
                        <div className="ind-stat">{guidance.total}</div>
                        <div className="ind-card-meta">
                            {guidance.completed} selesai
                        </div>
                    </Card>
                </div>

                <div className="ind-section">
                    <h6>Mahasiswa per Status</h6>
                    <DataTable
                        head={
                            <tr>
                                <th>Status</th>
                                <th>Jumlah</th>
                            </tr>
                        }
                    >
                        {students.by_status.map((row) => (
                            <tr key={row.label}>
                                <td>{row.label}</td>
                                <td className="font-semibold">{row.count}</td>
                            </tr>
                        ))}
                    </DataTable>
                </div>

                <div className="ind-section">
                    <h6>Proposal per Status</h6>
                    <DataTable
                        head={
                            <tr>
                                <th>Status</th>
                                <th>Jumlah</th>
                            </tr>
                        }
                    >
                        {proposals.map((row) => (
                            <tr key={row.label}>
                                <td>{row.label}</td>
                                <td className="font-semibold">{row.count}</td>
                            </tr>
                        ))}
                    </DataTable>
                </div>

                <div className="ind-section">
                    <h6>Rekap Ujian</h6>
                    <DataTable
                        head={
                            <tr>
                                <th>Tahap</th>
                                <th>Pengajuan Menunggu</th>
                                <th>Terjadwal</th>
                                <th>Selesai</th>
                                <th>Lulus</th>
                                <th>Tidak Lulus</th>
                            </tr>
                        }
                    >
                        {exams.map((row) => (
                            <tr key={row.stage_label}>
                                <td className="font-semibold">
                                    {row.stage_label}
                                </td>
                                <td>{row.pending_requests}</td>
                                <td>{row.scheduled}</td>
                                <td>{row.completed}</td>
                                <td>{row.passed}</td>
                                <td>{row.failed}</td>
                            </tr>
                        ))}
                    </DataTable>
                </div>
            </div>
        </>
    );
}
