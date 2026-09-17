<?php

namespace App\Actions\Thesis;

use App\Enums\ExamParticipantRole;
use App\Enums\SupervisorRole;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\ThesisExam;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Seats a panel at one exam sitting, and refuses the combinations a program
 * studi's rules do not allow.
 *
 * The student is seated too: the attendance list and the minutes both need a
 * row for them to sign.
 */
final readonly class BuildExamPanel
{
    /**
     * @param  array<int, array{lecturer_id: int, role: string}>  $seats
     */
    public function handle(ThesisExam $exam, array $seats): void
    {
        $student = $exam->student;
        $setting = $student->department->activeThesisSetting();

        $this->guard($student, $seats, $setting->allows_supervisor_as_examiner, $setting->min_examiners);

        $exam->participants()->delete();

        $lecturers = Lecturer::query()
            ->whereIn('id', array_column($seats, 'lecturer_id'))
            ->with('user')
            ->get()
            ->keyBy('id');

        foreach ($seats as $seat) {
            $lecturer = $lecturers[$seat['lecturer_id']];

            $exam->participants()->create([
                'user_id' => $lecturer->user_id,
                'lecturer_id' => $lecturer->id,
                'role' => $seat['role'],
            ]);
        }

        $exam->participants()->create([
            'user_id' => $student->user_id,
            'role' => ExamParticipantRole::Student,
        ]);
    }

    /**
     * @param  array<int, array{lecturer_id: int, role: string}>  $seats
     */
    private function guard(Student $student, array $seats, bool $allowsSupervisorAsExaminer, int $minExaminers): void
    {
        $roles = collect($seats)->pluck('role');
        $chairs = $roles->filter(fn (string $role): bool => $role === ExamParticipantRole::ChairExaminer->value);
        $examiners = $roles->filter(
            fn (string $role): bool => in_array($role, [
                ExamParticipantRole::ChairExaminer->value,
                ExamParticipantRole::MemberExaminer->value,
            ], true),
        );

        $errors = [];

        if ($chairs->count() !== 1) {
            $errors['panel'] = 'Panel harus memiliki tepat satu Ketua Penguji.';
        }

        if ($examiners->count() < $minExaminers) {
            $errors['panel'] = "Program studi ini mensyaratkan sedikitnya {$minExaminers} penguji.";
        }

        $lecturerIds = array_column($seats, 'lecturer_id');

        if (count($lecturerIds) !== count(array_unique($lecturerIds))) {
            $errors['panel'] = 'Seorang dosen hanya boleh menempati satu kursi pada panel ini.';
        }

        if (! $allowsSupervisorAsExaminer) {
            $clash = $this->supervisorsSeatedAsExaminers($student, $seats);

            if ($clash->isNotEmpty()) {
                $errors['panel'] = 'Program studi ini melarang pembimbing menguji mahasiswa bimbingannya sendiri: '
                    .$clash->implode(', ').'.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, array{lecturer_id: int, role: string}>  $seats
     * @return Collection<int, string>
     */
    private function supervisorsSeatedAsExaminers(Student $student, array $seats): Collection
    {
        $supervisorIds = $student->supervisorAssignments()
            ->where('role', SupervisorRole::Supervisor)
            ->pluck('lecturer_id');

        $examinerIds = collect($seats)
            ->filter(fn (array $seat): bool => in_array($seat['role'], [
                ExamParticipantRole::ChairExaminer->value,
                ExamParticipantRole::MemberExaminer->value,
            ], true))
            ->pluck('lecturer_id');

        return Lecturer::query()
            ->whereIn('id', $supervisorIds->intersect($examinerIds))
            ->with('user')
            ->get()
            ->map(fn (Lecturer $lecturer): string => $lecturer->user->name);
    }
}
