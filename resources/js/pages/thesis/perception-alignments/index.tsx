import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import {
    DataTable,
    EmptyRow,
    Input,
    PageHeader,
    Tag,
} from '@/components/industry/ui';
import { show } from '@/routes/perception-alignments';

type Round = {
    id: number;
    title: string;
    student: string;
    student_number: string;
    my_role: string;
    status_label: string;
    needs_me: boolean;
};

export default function PerceptionAlignmentIndex({
    rounds,
}: {
    rounds: Round[];
}) {
    setLayoutProps({ title: 'Persamaan Persepsi' });

    const [search, setSearch] = useState('');
    const term = search.trim().toLowerCase();

    const visible = rounds.filter(
        (round) =>
            term === '' ||
            round.title.toLowerCase().includes(term) ||
            round.student.toLowerCase().includes(term),
    );

    return (
        <>
            <Head title="Persamaan Persepsi" />

            <div className="flex flex-col gap-[var(--ind-space-4)]">
                <PageHeader
                    title="Persamaan Persepsi"
                    description="Umpan balik antar dosen pembimbing sebelum sesi bimbingan formal dimulai."
                />

                <Input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Cari judul atau nama mahasiswa..."
                    className="max-w-[320px]"
                />

                <DataTable
                    head={
                        <tr>
                            <th>Judul Proposal</th>
                            <th>Mahasiswa</th>
                            <th>Peran Saya</th>
                            <th>Status</th>
                        </tr>
                    }
                >
                    {visible.length === 0 ? (
                        <EmptyRow colSpan={4}>
                            Belum ada persamaan persepsi yang berjalan.
                        </EmptyRow>
                    ) : (
                        visible.map((round) => (
                            <tr
                                key={round.id}
                                className="cursor-pointer"
                                onClick={() => router.visit(show(round.id))}
                            >
                                <td className="font-semibold">
                                    {round.title}
                                    {round.needs_me && (
                                        <Tag
                                            label="Menunggu Anda"
                                            tone="accent"
                                            className="ml-2"
                                        />
                                    )}
                                </td>
                                <td>
                                    {round.student}
                                    <span className="ind-muted block text-[12px]">
                                        {round.student_number}
                                    </span>
                                </td>
                                <td>
                                    <Tag label={round.my_role} tone="outline" />
                                </td>
                                <td>
                                    <Tag label={round.status_label} />
                                </td>
                            </tr>
                        ))
                    )}
                </DataTable>
            </div>
        </>
    );
}
