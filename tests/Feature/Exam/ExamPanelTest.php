<?php

use App\Actions\Thesis\BuildExamPanel;
use App\Enums\ExamParticipantRole;
use App\Enums\ExamStage;
use App\Enums\SupervisorRole;
use App\Enums\UserRole;
use App\Models\ProposalExam;
use App\Models\Student;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    seedRoles();
});

/**
 * @return array{0: Student, 1: ProposalExam}
 */
function examWithStudent(?int $minExaminers = null, bool $allowsSupervisorAsExaminer = true): array
{
    $department = makeDepartment([
        ...$minExaminers === null ? [] : ['min_examiners' => $minExaminers],
        'allows_supervisor_as_examiner' => $allowsSupervisorAsExaminer,
    ]);
    $student = makeStudent($department);

    return [$student, ProposalExam::factory()->for($student)->create()];
}

function seat(int $lecturerId, ExamParticipantRole $role): array
{
    return ['lecturer_id' => $lecturerId, 'role' => $role->value];
}

it('seats a panel and the student it is examining', function (): void {
    [$student, $exam] = examWithStudent();
    $chair = makeLecturer();
    $member = makeLecturer();
    $supervisor = assignSupervisor($student);

    app(BuildExamPanel::class)->handle($exam, [
        seat($chair->id, ExamParticipantRole::ChairExaminer),
        seat($member->id, ExamParticipantRole::MemberExaminer),
        seat($supervisor->id, ExamParticipantRole::Supervisor),
    ]);

    $participants = $exam->participants()->get();

    expect($participants)->toHaveCount(4)
        ->and($participants->where('role', ExamParticipantRole::Student)->first()->user_id)
        ->toBe($student->user_id)
        ->and($exam->chairExaminer()->lecturer_id)->toBe($chair->id)
        // The panel the exam reads is its own, chair first.
        ->and($exam->panel()->pluck('label')->all())
        ->toBe(['Ketua Penguji', 'Anggota Penguji 1']);
});

it('requires exactly one chair', function (): void {
    [$student, $exam] = examWithStudent();
    $first = makeLecturer();
    $second = makeLecturer();

    expect(fn () => app(BuildExamPanel::class)->handle($exam, [
        seat($first->id, ExamParticipantRole::ChairExaminer),
        seat($second->id, ExamParticipantRole::ChairExaminer),
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(BuildExamPanel::class)->handle($exam, [
        seat($first->id, ExamParticipantRole::MemberExaminer),
        seat($second->id, ExamParticipantRole::MemberExaminer),
    ]))->toThrow(ValidationException::class);

    expect($exam->participants()->count())->toBe(0);
});

it('honours the program studi minimum number of examiners', function (): void {
    [$student, $exam] = examWithStudent(minExaminers: 3);
    $lecturers = collect(range(1, 3))->map(fn (): object => makeLecturer());

    expect(fn () => app(BuildExamPanel::class)->handle($exam, [
        seat($lecturers[0]->id, ExamParticipantRole::ChairExaminer),
        seat($lecturers[1]->id, ExamParticipantRole::MemberExaminer),
    ]))->toThrow(ValidationException::class);

    app(BuildExamPanel::class)->handle($exam, [
        seat($lecturers[0]->id, ExamParticipantRole::ChairExaminer),
        seat($lecturers[1]->id, ExamParticipantRole::MemberExaminer),
        seat($lecturers[2]->id, ExamParticipantRole::MemberExaminer),
    ]);

    expect($exam->examiners())->toHaveCount(3)
        ->and($exam->panel()->pluck('label')->all())
        ->toBe(['Ketua Penguji', 'Anggota Penguji 1', 'Anggota Penguji 2']);
});

it('refuses a supervisor as examiner only when the program studi forbids it', function (): void {
    [$student, $exam] = examWithStudent(allowsSupervisorAsExaminer: false);
    $supervisor = assignSupervisor($student);
    $other = makeLecturer();

    expect(fn () => app(BuildExamPanel::class)->handle($exam, [
        seat($supervisor->id, ExamParticipantRole::ChairExaminer),
        seat($other->id, ExamParticipantRole::MemberExaminer),
    ]))->toThrow(ValidationException::class);

    [$permissive, $permissiveExam] = examWithStudent(allowsSupervisorAsExaminer: true);
    $itsSupervisor = assignSupervisor($permissive);
    $another = makeLecturer();

    app(BuildExamPanel::class)->handle($permissiveExam, [
        seat($itsSupervisor->id, ExamParticipantRole::ChairExaminer),
        seat($another->id, ExamParticipantRole::MemberExaminer),
    ]);

    expect($permissiveExam->examiners())->toHaveCount(2);
});

it('gives one lecturer only one seat', function (): void {
    [$student, $exam] = examWithStudent();
    $lecturer = makeLecturer();
    $other = makeLecturer();

    expect(fn () => app(BuildExamPanel::class)->handle($exam, [
        seat($lecturer->id, ExamParticipantRole::ChairExaminer),
        seat($lecturer->id, ExamParticipantRole::MemberExaminer),
        seat($other->id, ExamParticipantRole::MemberExaminer),
    ]))->toThrow(ValidationException::class);
});

it('falls back to the student roster for a sitting with no panel of its own', function (): void {
    $student = makeStudent();
    assignSupervisor($student, order: 1);
    assignSupervisor($student, order: 2);
    $exam = ProposalExam::factory()->for($student)->create();

    expect($exam->hasOwnPanel())->toBeFalse()
        ->and($exam->panel())->toHaveCount(2)
        ->and($exam->panel()->first()->supervisorRole)->toBe(SupervisorRole::Supervisor);
});

it('lets a participant record their own attendance but not someone else\'s', function (): void {
    [$student, $exam] = examWithStudent();
    $chair = makeLecturer();
    $member = makeLecturer();

    app(BuildExamPanel::class)->handle($exam, [
        seat($chair->id, ExamParticipantRole::ChairExaminer),
        seat($member->id, ExamParticipantRole::MemberExaminer),
    ]);

    $chairSeat = $exam->participants()->where('lecturer_id', $chair->id)->sole();
    $memberSeat = $exam->participants()->where('lecturer_id', $member->id)->sole();
    $route = route('exams.attendance.update', ['stage' => ExamStage::Proposal, 'exam' => $exam]);

    $this->actingAs($chair->user)
        ->post($route, ['participant_id' => $chairSeat->id, 'attended' => true])
        ->assertRedirect();

    $this->actingAs($chair->user)
        ->post($route, ['participant_id' => $memberSeat->id, 'attended' => true])
        ->assertForbidden();

    expect($chairSeat->refresh()->attended)->toBeTrue()
        ->and($chairSeat->attended_at)->not->toBeNull()
        ->and($memberSeat->refresh()->attended)->toBeFalse();
});

it('lets the program studi record anyone\'s attendance', function (): void {
    [$student, $exam] = examWithStudent();
    $admin = makeAdmin($student->department);
    $chair = makeLecturer();
    $member = makeLecturer();

    app(BuildExamPanel::class)->handle($exam, [
        seat($chair->id, ExamParticipantRole::ChairExaminer),
        seat($member->id, ExamParticipantRole::MemberExaminer),
    ]);

    $seat = $exam->participants()->where('lecturer_id', $member->id)->sole();

    $this->actingAs($admin)
        ->withSession(inWorkspace(UserRole::Admin, $student->department))
        ->post(route('exams.attendance.update', ['stage' => ExamStage::Proposal, 'exam' => $exam]), [
            'participant_id' => $seat->id,
            'attended' => true,
        ])
        ->assertRedirect();

    expect($seat->refresh()->attended)->toBeTrue();
});
