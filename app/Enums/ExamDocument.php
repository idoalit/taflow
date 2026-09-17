<?php

namespace App\Enums;

/**
 * The official documents a sitting produces.
 */
enum ExamDocument: string
{
    case Attendance = 'daftar-hadir';
    case ExamScore = 'penilaian-ujian';
    case Revision = 'lembar-revisi';
    case Supervision = 'penilaian-bimbingan';
    case ScoreList = 'daftar-nilai';
    case Minutes = 'berita-acara';

    /**
     * Human readable label, in Bahasa Indonesia, as shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Attendance => 'Daftar Hadir Ujian',
            self::ExamScore => 'Form Penilaian Ujian',
            self::Revision => 'Lembar Revisi',
            self::Supervision => 'Form Penilaian Proses Bimbingan',
            self::ScoreList => 'Daftar Nilai Ujian',
            self::Minutes => 'Berita Acara Ujian',
        };
    }

    /**
     * @return view-string
     */
    public function view(): string
    {
        return match ($this) {
            self::Attendance => 'documents.attendance',
            self::ExamScore => 'documents.exam-score',
            self::Revision => 'documents.revision',
            self::Supervision => 'documents.supervision',
            self::ScoreList => 'documents.score-list',
            self::Minutes => 'documents.minutes',
        };
    }

    /**
     * Whether this document is printed once per examiner or supervisor.
     */
    public function isPerLecturer(): bool
    {
        return in_array($this, [self::ExamScore, self::Revision, self::Supervision], true);
    }

    /**
     * What must exist before this document says anything true.
     *
     * @return array<int, string>
     */
    public function requires(): array
    {
        return match ($this) {
            self::Attendance => ['panel'],
            self::ExamScore, self::ScoreList => ['panel', 'exam_scores'],
            self::Revision => ['panel'],
            self::Supervision => ['panel', 'supervision_scores'],
            self::Minutes => ['panel', 'exam_scores', 'supervision_scores', 'minutes'],
        };
    }
}
