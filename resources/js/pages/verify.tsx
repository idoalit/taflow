import { Head } from '@inertiajs/react';

type Props = {
    document: {
        valid: boolean;
        student: string;
        student_number: string;
        department: string;
        faculty: string | null;
        stage: string;
        title: string;
        examined_at: string;
        decision: string | null;
        final_score: string | null;
        letter: string | null;
        released_at: string | null;
        institution: string;
    };
};

/**
 * The page a document's QR code leads to. Stands alone — no sidebar, no
 * account — because whoever holds the printed page should be able to check it.
 */
export default function VerifyDocument({ document }: Props) {
    const rows: [string, string | null][] = [
        ['Mahasiswa', document.student],
        ['NIM', document.student_number],
        ['Program Studi', document.department],
        ['Jurusan', document.faculty],
        ['Ujian', document.stage],
        ['Judul', document.title],
        ['Dilaksanakan', document.examined_at],
        ['Keputusan', document.decision],
        [
            'Nilai Akhir',
            document.final_score === null
                ? null
                : `${document.final_score}${document.letter ? ` (${document.letter})` : ''}`,
        ],
        ['Dirilis', document.released_at],
    ];

    return (
        <div className="ind flex min-h-dvh items-start justify-center p-[var(--ind-space-6)]">
            <Head title="Verifikasi Dokumen" />

            <div className="ind-card ind-bp w-full max-w-[560px]">
                <i className="ind-corner tl" />
                <i className="ind-corner tr" />
                <i className="ind-corner bl" />
                <i className="ind-corner br" />

                <span className="ind-kicker">Verifikasi Dokumen</span>
                <h3 className="mt-1 mb-1">Dokumen ini sah</h3>
                <p className="ind-muted m-0 text-[13px]">
                    Diterbitkan oleh {document.institution}. Data di bawah ini
                    diambil langsung dari sistem, bukan dari berkas yang Anda
                    pegang.
                </p>

                <table className="ind-table mt-[var(--ind-space-5)]">
                    <tbody>
                        {rows
                            .filter(([, value]) => value !== null)
                            .map(([label, value]) => (
                                <tr key={label}>
                                    <td className="ind-muted w-[40%] align-top">
                                        {label}
                                    </td>
                                    <td className="font-semibold">{value}</td>
                                </tr>
                            ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
