import { Head, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import { FileText } from 'lucide-react';
import {
    SearchSelect,
    type SearchSelectOption,
} from '@/components/industry/search-select';
import { Card, Field } from '@/components/industry/ui';
import { print, printForStudent } from '@/routes/guidance-cards';

type Props = {
    stages: { value: string; title: string; description: string }[];
    students: SearchSelectOption<number>[];
};

export default function GuidanceCardIndex({ stages, students }: Props) {
    setLayoutProps({ title: 'Cetak Kartu Bimbingan' });

    const [studentId, setStudentId] = useState<number | null>(
        students[0]?.value ?? null,
    );

    const printUrl = (stage: string) =>
        studentId === null
            ? print(stage).url
            : printForStudent({ stage, student: studentId }).url;

    return (
        <>
            <Head title="Cetak Kartu Bimbingan" />

            <div className="flex flex-col gap-[var(--ind-space-4)]">
                <div>
                    <h3 className="mb-1">Cetak Kartu Bimbingan</h3>
                    <p className="ind-muted m-0">
                        {students.length > 0
                            ? 'Pilih mahasiswa dan tahap bimbingan yang ingin dicetak.'
                            : 'Pilih tahap bimbingan yang ingin dicetak. Kartu berisi riwayat bimbingan dan kolom paraf pembimbing.'}
                    </p>
                </div>

                {students.length > 0 && (
                    <div className="max-w-[420px]">
                        <Field
                            label="Mahasiswa"
                            hint="Ketik nama atau NIM untuk menyaring daftar."
                        >
                            <SearchSelect
                                value={studentId}
                                options={students}
                                onChange={setStudentId}
                                placeholder="Pilih mahasiswa"
                                searchPlaceholder="Cari nama atau NIM..."
                                emptyLabel="Mahasiswa tidak ditemukan."
                            />
                        </Field>
                    </div>
                )}

                <div className="grid [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))] gap-[var(--ind-space-4)]">
                    {stages.map((stage) => (
                        <Card key={stage.value}>
                            <FileText
                                size={18}
                                strokeWidth={1.5}
                                style={{ color: 'var(--ind-accent-700)' }}
                            />
                            <div className="ind-card-title mt-[6px]">
                                {stage.title}
                            </div>
                            <p className="ind-card-body m-0">
                                {stage.description}
                            </p>
                            <a
                                href={printUrl(stage.value)}
                                target="_blank"
                                rel="noreferrer"
                                className="ind-btn ind-btn-primary self-start"
                            >
                                Cetak
                            </a>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}
