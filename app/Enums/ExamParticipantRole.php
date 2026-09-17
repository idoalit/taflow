<?php

namespace App\Enums;

/**
 * A seat at one exam session. Unlike SupervisorRole, which describes a lasting
 * relationship to a student, this describes who sat at a particular exam and in
 * what capacity — a distinction the attendance list and the minutes both need.
 */
enum ExamParticipantRole: string
{
    case ChairExaminer = 'ketua_penguji';
    case MemberExaminer = 'anggota_penguji';
    case Supervisor = 'pembimbing';
    case Student = 'mahasiswa';

    /**
     * Human readable label, in Bahasa Indonesia, as shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::ChairExaminer => 'Ketua Penguji',
            self::MemberExaminer => 'Anggota Penguji',
            self::Supervisor => 'Pembimbing',
            self::Student => 'Mahasiswa',
        };
    }

    /**
     * Whether this seat marks the exam rubric. Supervisors mark the supervision
     * form instead, and the student marks nothing.
     */
    public function scoresExam(): bool
    {
        return in_array($this, [self::ChairExaminer, self::MemberExaminer], true);
    }

    /**
     * Seats that a panel is built from, in the order the documents list them.
     *
     * @return array<int, self>
     */
    public static function panelRoles(): array
    {
        return [self::ChairExaminer, self::MemberExaminer, self::Supervisor];
    }
}
