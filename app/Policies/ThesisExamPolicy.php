<?php

namespace App\Policies;

use App\Enums\ExamParticipantRole;
use App\Enums\ExamStatus;
use App\Models\ExamParticipant;
use App\Models\ThesisExam;
use App\Models\User;
use App\Support\PanelMember;
use Illuminate\Auth\Access\Response;

class ThesisExamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('exam.view');
    }

    /**
     * Putting an exam on the calendar is the program studi's job, never the
     * supervisor's.
     */
    public function schedule(User $user): bool
    {
        return $user->can('exam.schedule');
    }

    public function view(User $user, ThesisExam $exam): bool
    {
        if (! $user->can('exam.view')) {
            return false;
        }

        // Being seated at a sitting is itself sight of it. An examiner drafted
        // onto one panel has no business in the student's other records, so
        // this is deliberately narrower than Student::scopeVisibleTo.
        return $exam->student->isVisibleTo($user)
            || $exam->participants()->where('user_id', $user->id)->exists();
    }

    /**
     * Attendance is your own to record; the program studi may record anyone's,
     * since it runs the room.
     */
    public function markAttendance(User $user, ThesisExam $exam, ExamParticipant $participant): Response
    {
        if ($user->can('exam.schedule') && $exam->student->isVisibleTo($user)) {
            return Response::allow();
        }

        return $participant->user_id === $user->id
            ? Response::allow()
            : Response::deny('Anda hanya dapat menandai kehadiran Anda sendiri.');
    }

    /**
     * Releasing, and reopening what was released, belong to the program studi.
     */
    public function release(User $user, ThesisExam $exam): Response
    {
        if (! $user->can('exam.schedule') || ! $exam->student->isVisibleTo($user)) {
            return Response::deny('Hanya admin program studi yang dapat merilis hasil ujian.');
        }

        return Response::allow();
    }

    /**
     * The chair of this sitting settles the berita acara.
     */
    public function decide(User $user, ThesisExam $exam): Response
    {
        if ($exam->isReleased()) {
            return Response::deny(
                'Hasil ujian sudah dirilis dan terkunci. Minta admin program studi '
                .'membuka kunci bila keputusan perlu diubah.',
            );
        }

        $lecturer = $user->lecturer;

        if ($lecturer === null) {
            return Response::deny('Hanya ketua penguji yang dapat menetapkan berita acara.');
        }

        $chairs = $exam->participants()
            ->where('role', ExamParticipantRole::ChairExaminer)
            ->where('lecturer_id', $lecturer->id)
            ->exists();

        return $chairs
            ? Response::allow()
            : Response::deny('Hanya ketua penguji sidang ini yang dapat menetapkan berita acara.');
    }

    /**
     * Writing a revision sheet: any examiner seated at this sitting, until the
     * result is released.
     */
    public function writeRevisions(User $user, ThesisExam $exam): Response
    {
        if ($exam->isReleased()) {
            return Response::deny('Hasil ujian sudah dirilis dan terkunci.');
        }

        $lecturer = $user->lecturer;

        if ($lecturer === null) {
            return Response::deny('Hanya dosen penguji yang dapat mengisi lembar revisi.');
        }

        $seated = $exam->participants()
            ->whereIn('role', [
                ExamParticipantRole::ChairExaminer,
                ExamParticipantRole::MemberExaminer,
            ])
            ->where('lecturer_id', $lecturer->id)
            ->exists();

        return $seated
            ? Response::allow()
            : Response::deny('Anda bukan penguji pada sidang ini.');
    }

    /**
     * Whether the marks may be shown to the student. Everything before release
     * belongs to the panel.
     */
    public function viewResult(User $user, ThesisExam $exam): bool
    {
        if ($exam->isReleased()) {
            return $this->view($user, $exam);
        }

        // Before release, only the people running the exam see the numbers.
        return $user->can('exam.schedule')
            || $exam->participants()
                ->where('user_id', $user->id)
                ->whereNot('role', ExamParticipantRole::Student)
                ->exists();
    }

    /**
     * The supervision form belongs to the pembimbing seated at this sitting.
     * An examiner marks the exam rubric instead; the two are different forms
     * and different people.
     */
    public function scoreSupervision(User $user, ThesisExam $exam): Response
    {
        $lecturer = $user->lecturer;

        if (! $user->can('exam.evaluate') || $lecturer === null) {
            return Response::deny('Hanya dosen yang dapat mengisi penilaian proses bimbingan.');
        }

        $seated = $exam->participants()
            ->where('role', ExamParticipantRole::Supervisor)
            ->where('lecturer_id', $lecturer->id)
            ->exists();

        if ($exam->isReleased()) {
            return Response::deny('Hasil ujian sudah dirilis dan terkunci.');
        }

        if ($seated) {
            return Response::allow();
        }

        // A sitting with no panel of its own falls back to the roster, the same
        // way the exam panel does.
        if (! $exam->hasOwnPanel() && $exam->student->isSupervisedBy($user)) {
            return Response::allow();
        }

        return Response::deny('Anda bukan pembimbing mahasiswa pada sidang ini.');
    }

    /**
     * Each panel member evaluates once, and only while the exam is actually
     * under way or already scheduled — never after it has been cancelled.
     */
    public function evaluate(User $user, ThesisExam $exam): Response
    {
        $lecturer = $user->lecturer;

        if (! $user->can('exam.evaluate') || $lecturer === null) {
            return Response::deny('Hanya dosen yang dapat mengisi evaluasi ujian.');
        }

        if (! $exam->panel()->contains(fn (PanelMember $member): bool => $member->lecturerId === $lecturer->id)) {
            return Response::deny('Anda bukan anggota panel pada ujian ini.');
        }

        if ($exam->status === ExamStatus::Cancelled) {
            return Response::deny('Ujian ini telah dibatalkan.');
        }

        if ($exam->isReleased()) {
            return Response::deny('Hasil ujian sudah dirilis dan terkunci.');
        }

        if ($exam->evaluations()->where('lecturer_id', $lecturer->id)->exists()) {
            return Response::deny("Anda sudah mengisi evaluasi untuk {$exam->stage()->title()} ini dan tidak dapat mengubahnya.");
        }

        return Response::allow();
    }
}
