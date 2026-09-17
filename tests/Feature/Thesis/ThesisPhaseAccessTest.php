<?php

use App\Actions\Thesis\ThesisPhaseAccess;
use App\Enums\ExamResult;
use App\Enums\GuidanceStage;
use App\Models\GuidanceSession;
use App\Models\ProposalExam;
use App\Models\Student;
use App\Models\ThesisProposal;

beforeEach(function (): void {
    seedRoles();
});

/**
 * A student with an approved proposal in a department that runs a proposal exam
 * — the ordinary starting point for these checks.
 */
function studentAwaitingProposalExam(array $settings = []): Student
{
    $student = makeStudent(makeDepartment($settings));
    ThesisProposal::factory()->for($student)->for($student->department)->approved()->create();

    return $student;
}

it('keeps research guidance locked until the proposal exam is passed', function (): void {
    $access = ThesisPhaseAccess::forStudent(studentAwaitingProposalExam());

    expect($access->isOpen(GuidanceStage::Proposal))->toBeTrue()
        ->and($access->isOpen(GuidanceStage::Research))->toBeFalse()
        ->and($access->reasonFor(GuidanceStage::Research))->toContain('belum dijadwalkan');
});

it('opens research guidance once the proposal exam is passed', function (): void {
    $student = studentAwaitingProposalExam();
    passProposalExam($student);

    expect(ThesisPhaseAccess::forStudent($student)->isOpen(GuidanceStage::Research))->toBeTrue();
});

it('treats Lulus Bersyarat as passing', function (): void {
    $student = studentAwaitingProposalExam();
    passProposalExam($student, ExamResult::ConditionalPass);

    expect(ThesisPhaseAccess::forStudent($student)->isOpen(GuidanceStage::Research))->toBeTrue();
});

it('keeps research guidance locked when the proposal exam was failed', function (): void {
    $student = studentAwaitingProposalExam();
    passProposalExam($student, ExamResult::Failed);

    $access = ThesisPhaseAccess::forStudent($student);

    expect($access->isOpen(GuidanceStage::Research))->toBeFalse()
        ->and($access->reasonFor(GuidanceStage::Research))->toContain('Tidak Lulus');
});

it('says the exam is scheduled while it has not been held yet', function (): void {
    $student = studentAwaitingProposalExam();
    ProposalExam::factory()->for($student)->create();

    expect(ThesisPhaseAccess::forStudent($student)->reasonFor(GuidanceStage::Research))
        ->toContain('dijadwalkan pada');
});

it('falls back to the guidance quota when the department requires no proposal exam', function (): void {
    $student = studentAwaitingProposalExam([
        'requires_proposal_exam' => false,
        'min_proposal_guidance_sessions' => 2,
    ]);

    $locked = ThesisPhaseAccess::forStudent($student);
    expect($locked->isOpen(GuidanceStage::Research))->toBeFalse()
        ->and($locked->reasonFor(GuidanceStage::Research))->toContain('tidak mewajibkan ujian proposal');

    GuidanceSession::factory()->count(2)->for($student)->completed()->create();

    expect(ThesisPhaseAccess::forStudent($student->refresh())->isOpen(GuidanceStage::Research))
        ->toBeTrue();
});

it('locks both stages until the proposal itself is approved', function (): void {
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->create();

    $access = ThesisPhaseAccess::forStudent($student);

    expect($access->isOpen(GuidanceStage::Proposal))->toBeFalse()
        ->and($access->isOpen(GuidanceStage::Research))->toBeFalse()
        ->and($access->openStages())->toBeEmpty();
});

it('describes each stage for the create screen', function (): void {
    $options = ThesisPhaseAccess::forStudent(studentAwaitingProposalExam())->toOptions();

    expect($options)->toHaveCount(2)
        ->and($options[0])->toMatchArray(['value' => 'proposal', 'disabled' => false])
        ->and($options[1]['value'])->toBe('research')
        ->and($options[1]['disabled'])->toBeTrue()
        ->and($options[1]['reason'])->not->toBeNull();
});
