<?php

use App\Enums\ApprovalStatus;
use App\Enums\ExamResult;
use App\Enums\ExamStatus;
use App\Models\ProposalExam;
use App\Models\ProposalExamRequest;
use App\Models\Student;
use App\Models\ThesisProposal;

beforeEach(function (): void {
    seedRoles();
});

/**
 * A student with an approved proposal and two supervisors — the panel for every
 * exam test below.
 */
function studentWithPanel(): Student
{
    $student = makeStudent();
    assignSupervisor($student, order: 1);
    assignSupervisor($student, order: 2);
    ThesisProposal::factory()->for($student)->for($student->department)->approved()->create();

    return $student;
}

it('lets a supervisor approve an exam request and passes it to the admin gate', function (): void {
    $student = studentWithPanel();
    $supervisor = $student->supervisors->first();
    $request = ProposalExamRequest::factory()
        ->for($student)
        ->for($student->activeProposal())
        ->create();

    $this->actingAs($supervisor->user)
        ->post(route('exam-verifications.verify', ['stage' => $request->stage(), 'examRequest' => $request]), [
            'decision' => 'approved',
            'supervisor_notes' => 'Mahasiswa siap diuji.',
        ])
        ->assertRedirect(route('exam-verifications.index', ['stage' => $request->stage()]));

    $request->refresh();

    expect($request->supervisor_status)->toBe(ApprovalStatus::Approved)
        ->and($request->supervisor_verified_by)->toBe($supervisor->user_id)
        // Still pending overall: the admin gate has not answered.
        ->and($request->status)->toBe(ApprovalStatus::Pending)
        ->and($request->awaitingLabel())->toBe('Menunggu verifikasi admin prodi');
});

it('settles the request as rejected when the supervisor refuses', function (): void {
    $student = studentWithPanel();
    $request = ProposalExamRequest::factory()
        ->for($student)
        ->for($student->activeProposal())
        ->create();

    $this->actingAs($student->supervisors->first()->user)
        ->post(route('exam-verifications.verify', ['stage' => $request->stage(), 'examRequest' => $request]), [
            'decision' => 'rejected',
            'supervisor_notes' => 'Bimbingan belum cukup.',
        ]);

    expect($request->refresh()->status)->toBe(ApprovalStatus::Rejected);
});

it('does not let an unrelated lecturer verify a request', function (): void {
    $student = studentWithPanel();
    $request = ProposalExamRequest::factory()
        ->for($student)
        ->for($student->activeProposal())
        ->create();

    $this->actingAs(makeLecturer()->user)
        ->post(route('exam-verifications.verify', ['stage' => $request->stage(), 'examRequest' => $request]), ['decision' => 'approved'])
        ->assertForbidden();
});

it('settles the exam only once every panel member has evaluated', function (): void {
    $student = studentWithPanel();
    [$first, $second] = $student->supervisors->all();
    $exam = ProposalExam::factory()->for($student)->create();
    $rubric = assignRubric($student->department);

    $this->actingAs($first->user)
        ->post(route('exams.evaluations.store', ['stage' => $exam->stage(), 'exam' => $exam]), [
            'scores' => marksOf($rubric, 85),
            'result' => ExamResult::Passed->value,
            'feedback' => 'Bagus.',
        ])
        ->assertRedirect();

    expect($exam->refresh()->result)->toBeNull()
        ->and($exam->status)->toBe(ExamStatus::Scheduled);

    $this->actingAs($second->user)
        ->post(route('exams.evaluations.store', ['stage' => $exam->stage(), 'exam' => $exam]), [
            'scores' => marksOf($rubric, 75),
            'result' => ExamResult::ConditionalPass->value,
        ]);

    $exam->refresh();

    // Mean of 85 and 75, and the harsher of the two verdicts.
    expect((float) $exam->final_score)->toBe(80.0)
        ->and($exam->result)->toBe(ExamResult::ConditionalPass)
        ->and($exam->status)->toBe(ExamStatus::Scored);
});

it('lets a panel member evaluate only once', function (): void {
    $student = studentWithPanel();
    $supervisor = $student->supervisors->first();
    $exam = ProposalExam::factory()->for($student)->create();
    $rubric = assignRubric($student->department);

    $payload = [
        'scores' => marksOf($rubric, 80),
        'result' => ExamResult::Passed->value,
    ];

    $this->actingAs($supervisor->user)
        ->post(route('exams.evaluations.store', ['stage' => $exam->stage(), 'exam' => $exam]), $payload)
        ->assertRedirect();

    $this->actingAs($supervisor->user)
        ->get(route('exams.evaluate', ['stage' => $exam->stage(), 'exam' => $exam]))
        ->assertForbidden();

    expect($exam->evaluations()->count())->toBe(1);
});

it('does not let someone outside the panel evaluate', function (): void {
    $student = studentWithPanel();
    $exam = ProposalExam::factory()->for($student)->create();
    $rubric = assignRubric($student->department);

    $this->actingAs(makeLecturer()->user)
        ->get(route('exams.evaluate', ['stage' => $exam->stage(), 'exam' => $exam]))
        ->assertForbidden();
});

it('validates the evaluation scores', function (): void {
    $student = studentWithPanel();
    $exam = ProposalExam::factory()->for($student)->create();
    $rubric = assignRubric($student->department);

    $this->actingAs($student->supervisors->first()->user)
        ->post(route('exams.evaluations.store', ['stage' => $exam->stage(), 'exam' => $exam]), [
            'scores' => marksOf($rubric, 140),
            'result' => 'juara',
        ])
        ->assertSessionHasErrors(['scores.kualitas_proposal', 'result']);

    expect($exam->evaluations()->count())->toBe(0);
});

it('shows the panel with who has evaluated', function (): void {
    $student = studentWithPanel();
    $first = $student->supervisors->first();
    $exam = ProposalExam::factory()->for($student)->create();
    $rubric = assignRubric($student->department);

    $this->actingAs($first->user)->post(route('exams.evaluations.store', ['stage' => $exam->stage(), 'exam' => $exam]), [
        'scores' => marksOf($rubric, 80),
        'result' => ExamResult::Passed->value,
    ]);

    $this->actingAs($first->user)
        ->get(route('exams.show', ['stage' => $exam->stage(), 'exam' => $exam]))
        ->assertInertia(fn ($page) => $page
            ->has('exam.panel', 2)
            ->where('exam.panel.0.evaluated', true)
            ->where('exam.panel.1.evaluated', false)
            ->where('exam.evaluation_count', 1)
            ->where('exam.panel_size', 2)
            ->where('canEvaluate', false));
});

it('scopes the exam list to the lecturer\'s own students', function (): void {
    $mine = studentWithPanel();
    $theirs = studentWithPanel();
    ProposalExam::factory()->for($mine)->create();
    ProposalExam::factory()->for($theirs)->create();

    $this->actingAs($mine->supervisors->first()->user)
        ->get(route('exams.index'))
        ->assertInertia(fn ($page) => $page
            ->has('exams', 1)
            ->where('exams.0.student', $mine->user->name)
            ->where('exams.0.my_evaluation', 'Belum diisi'));
});
