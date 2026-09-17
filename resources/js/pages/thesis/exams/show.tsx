import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    AttendanceSection,
    type DocumentRow,
    DocumentSection,
    MinutesForm,
    type Participant,
    ReleaseSection,
    RevisionChecklist,
    RevisionEditor,
} from '@/components/industry/exam-sections';
import {
    Avatar,
    BtnLink,
    Card,
    CardKicker,
    DataTable,
    Tag,
} from '@/components/industry/ui';
import { index } from '@/routes/exams';
import type { ThesisExamDetail } from '@/types';

type Grade = {
    exam_score: number | null;
    exam_scores: { lecturer: string; score: number }[];
    examiner_count: number;
    supervision_score: number | null;
    supervision_max: number;
    exam_weight: number;
    supervision_weight: number;
    final_score: number | null;
    letter: string | null;
    complete: boolean;
};

type Props = {
    exam: ThesisExamDetail;
    canEvaluate: boolean;
    canScoreSupervision: boolean;
    supervisionUrl: string;
    grade: Grade;
    missing: string[];
    documents: DocumentRow[];
    documentsUrl: string;
    participants: Participant[];
    myRevisions: { body: string; due_date: string | null }[];
    allRevisions: {
        id: number;
        lecturer: string;
        position: number;
        body: string;
        status: string;
        status_label: string;
        due_date: string | null;
    }[];
    minutes: {
        decision: string;
        decision_label: string;
        revision_days: number;
        notes: string | null;
    } | null;
    results: { value: string; label: string }[];
    released: boolean;
    canDecide: boolean;
    canWriteRevisions: boolean;
    canRelease: boolean;
    canViewResult: boolean;
    canTickRevisions: boolean;
    urls: {
        sign: string;
        attendance: string;
        revisions: string;
        minutes: string;
        release: string;
    };
};

export default function ExamShow({
    exam,
    canEvaluate,
    canScoreSupervision,
    supervisionUrl,
    grade,
    missing,
    documents,
    documentsUrl,
    participants,
    myRevisions,
    allRevisions,
    minutes,
    results,
    released,
    canDecide,
    canWriteRevisions,
    canRelease,
    canViewResult,
    canTickRevisions,
    urls,
}: Props) {
    setLayoutProps({ title: 'Detail Ujian' });

    return (
        <>
            <Head title={exam.title} />

            <div className="ind-page">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0 flex-1">
                        <Link href={index()} className="text-[12.5px]">
                            ← Pelaksanaan Ujian
                        </Link>
                        <h3 style={{ margin: '6px 0 8px' }}>{exam.title}</h3>
                        <div className="flex flex-wrap items-center gap-[10px]">
                            <Tag label={exam.stage_label} tone="accent" />
                            <span className="ind-muted text-[13px]">
                                {exam.student} · {exam.scheduled_at}
                                {exam.venue ? ` · ${exam.venue}` : ''}
                            </span>
                            <Tag label={exam.status_label} />
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-[var(--ind-space-3)]">
                        {canEvaluate && !released && (
                            <BtnLink variant="primary" href={exam.evaluate_url}>
                                Isi Evaluasi
                            </BtnLink>
                        )}
                        {canScoreSupervision && !released && (
                            <BtnLink href={supervisionUrl}>
                                Penilaian Proses Bimbingan
                            </BtnLink>
                        )}
                    </div>
                </div>

                <Card>
                    <CardKicker>Susunan Panel</CardKicker>
                    <div className="flex flex-col gap-[var(--ind-space-3)]">
                        {exam.panel.map((member) => (
                            <div
                                key={member.lecturer_id}
                                className="flex flex-wrap items-center justify-between gap-2"
                            >
                                <div className="flex items-center gap-[10px]">
                                    <Avatar
                                        initials={member.initials}
                                        size={26}
                                    />
                                    <span className="text-[13px] font-semibold">
                                        {member.name}
                                    </span>
                                    <Tag
                                        label={member.role_label}
                                        tone="neutral"
                                    />
                                </div>

                                {member.evaluated ? (
                                    <Tag
                                        label={`Sudah Dievaluasi · Skor ${member.score}`}
                                        tone="accent"
                                    />
                                ) : (
                                    <Tag
                                        label="Belum Dievaluasi"
                                        tone="outline"
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                    <div className="ind-card-meta">
                        {exam.evaluation_count} dari {exam.panel_size} anggota
                        panel telah menilai
                    </div>
                </Card>

                <AttendanceSection
                    participants={participants}
                    attendanceUrl={urls.attendance}
                    signUrl={urls.sign}
                    locked={released}
                />

                <div className="ind-section">
                    <h6>Nilai Akhir</h6>

                    {grade.complete ? (
                        <DataTable
                            head={
                                <tr>
                                    <th>Komponen</th>
                                    <th>Nilai</th>
                                    <th>Bobot</th>
                                    <th>Nilai × Bobot</th>
                                </tr>
                            }
                        >
                            <tr>
                                <td>
                                    Nilai Ujian
                                    <span className="ind-muted block text-[12px]">
                                        Rerata {grade.examiner_count} penguji
                                    </span>
                                </td>
                                <td>{grade.exam_score?.toFixed(2)}</td>
                                <td>{grade.exam_weight}</td>
                                <td>
                                    {(
                                        (grade.exam_score ?? 0) *
                                        grade.exam_weight
                                    ).toFixed(2)}
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    Nilai Proses Bimbingan
                                    <span className="ind-muted block text-[12px]">
                                        dari {grade.supervision_max}
                                    </span>
                                </td>
                                <td>{grade.supervision_score?.toFixed(2)}</td>
                                <td>{grade.supervision_weight}</td>
                                <td>
                                    {(
                                        (grade.supervision_score ?? 0) *
                                        grade.supervision_weight
                                    ).toFixed(2)}
                                </td>
                            </tr>
                            <tr>
                                <td className="font-semibold">NILAI AKHIR</td>
                                <td />
                                <td />
                                <td className="font-semibold">
                                    {grade.final_score?.toFixed(2)}
                                    {grade.letter && ` (${grade.letter})`}
                                </td>
                            </tr>
                        </DataTable>
                    ) : (
                        <Card>
                            <CardKicker>Belum Dapat Dihitung</CardKicker>
                            <ul className="ind-card-body mt-1 mb-0 flex list-disc flex-col gap-1 pl-5">
                                {missing.map((item) => (
                                    <li key={item}>{item}</li>
                                ))}
                            </ul>
                        </Card>
                    )}
                </div>

                {exam.result_label && minutes === null && (
                    <Card>
                        <CardKicker>Hasil Evaluasi Panel</CardKicker>
                        <div className="ind-card-title">
                            {exam.result_label}
                            {exam.final_score
                                ? ` · Skor akhir ${exam.final_score}`
                                : ''}
                        </div>
                    </Card>
                )}

                {canWriteRevisions && (
                    <RevisionEditor
                        notes={myRevisions}
                        action={urls.revisions}
                    />
                )}

                {canViewResult && (
                    <RevisionChecklist
                        revisions={allRevisions}
                        canTick={canTickRevisions}
                    />
                )}

                {canDecide ? (
                    <MinutesForm
                        minutes={minutes}
                        results={results}
                        missing={missing}
                        action={urls.minutes}
                    />
                ) : (
                    minutes !== null && (
                        <Card>
                            <CardKicker>Berita Acara</CardKicker>
                            <div className="ind-card-title">
                                {minutes.decision_label}
                            </div>
                            <div className="ind-card-meta">
                                Batas perbaikan {minutes.revision_days} hari
                                kerja
                                {minutes.notes ? ` · ${minutes.notes}` : ''}
                            </div>
                        </Card>
                    )
                )}

                {canRelease && (
                    <ReleaseSection released={released} action={urls.release} />
                )}

                {exam.feedback.length > 0 && (
                    <div>
                        <h6 className="ind-muted mb-[var(--ind-space-3)]">
                            Umpan Balik Panel
                        </h6>
                        <div className="flex flex-col gap-[var(--ind-space-3)]">
                            {exam.feedback.map((entry) => (
                                <Card key={entry.lecturer}>
                                    <CardKicker>
                                        {entry.lecturer} · {entry.result_label}
                                    </CardKicker>
                                    <p className="ind-card-body m-0 whitespace-pre-line">
                                        {entry.body}
                                    </p>
                                </Card>
                            ))}
                        </div>
                    </div>
                )}

                <DocumentSection documents={documents} allUrl={documentsUrl} />
            </div>
        </>
    );
}
