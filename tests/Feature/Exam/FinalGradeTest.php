<?php

use App\Actions\Thesis\BuildExamPanel;
use App\Actions\Thesis\ComposeFinalGrade;
use App\Enums\ExamParticipantRole;
use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Models\ExamMinutes;
use App\Models\SupervisionScore;

beforeEach(function (): void {
    seedRoles();
});

it('composes the final grade from both marks and the configured weights', function (): void {
    ['exam' => $exam, 'rubric' => $rubric, 'supervisor' => $supervisor, 'examiners' => $examiners]
        = sittingWithForms();

    // 80% and 90% of 100 → mean 85. Supervision 16 × 5 = 80.
    markExam($exam, $rubric, $examiners[0], 0.8);
    markExam($exam, $rubric, $examiners[1], 0.9);
    markSupervision($exam, $supervisor, 16);

    $grade = app(ComposeFinalGrade::class)->handle($exam->refresh());

    expect($grade['exam_score'])->toBe(85.0)
        ->and($grade['examiner_count'])->toBe(2)
        ->and($grade['supervision_score'])->toBe(80.0)
        ->and($grade['exam_weight'])->toBe(0.9)
        ->and($grade['supervision_weight'])->toBe(0.1)
        // 85 × 0.9 + 80 × 0.1 = 76.5 + 8 = 84.5
        ->and($grade['final_score'])->toBe(84.5)
        ->and($grade['letter'])->toBe('AB')
        ->and($grade['complete'])->toBeTrue();
});

it('averages correctly with three examiners', function (): void {
    ['exam' => $exam, 'rubric' => $rubric, 'supervisor' => $supervisor, 'examiners' => $examiners]
        = sittingWithForms(examinerCount: 3);

    markExam($exam, $rubric, $examiners[0], 0.6);
    markExam($exam, $rubric, $examiners[1], 0.9);
    markExam($exam, $rubric, $examiners[2], 0.9);
    markSupervision($exam, $supervisor, 10);

    $grade = app(ComposeFinalGrade::class)->handle($exam->refresh());

    // Divided by three, not by the two the faculty usually seats.
    expect($grade['examiner_count'])->toBe(3)
        ->and($grade['exam_score'])->toBe(80.0)
        ->and($grade['supervision_score'])->toBe(50.0)
        ->and($grade['final_score'])->toBe(77.0);
});

it('handles the extremes', function (): void {
    ['exam' => $exam, 'rubric' => $rubric, 'supervisor' => $supervisor, 'examiners' => $examiners]
        = sittingWithForms();

    foreach ($examiners as $examiner) {
        markExam($exam, $rubric, $examiner, 1.0);
    }
    markSupervision($exam, $supervisor, 20);

    expect(app(ComposeFinalGrade::class)->handle($exam->refresh())['final_score'])->toBe(100.0);

    ['exam' => $zeroExam, 'rubric' => $zeroRubric, 'supervisor' => $zeroSupervisor, 'examiners' => $zeroExaminers]
        = sittingWithForms();

    foreach ($zeroExaminers as $examiner) {
        markExam($zeroExam, $zeroRubric, $examiner, 0.0);
    }
    markSupervision($zeroExam, $zeroSupervisor, 0);

    expect(app(ComposeFinalGrade::class)->handle($zeroExam->refresh())['final_score'])->toBe(0.0);
});

it('follows the program studi weights rather than a constant', function (): void {
    ['exam' => $exam, 'rubric' => $rubric, 'supervisor' => $supervisor, 'examiners' => $examiners]
        = sittingWithForms();

    $exam->student->department->activeThesisSetting()->update([
        'exam_score_weight' => 0.700,
        'supervision_score_weight' => 0.300,
    ]);

    foreach ($examiners as $examiner) {
        markExam($exam, $rubric, $examiner, 0.8);
    }
    markSupervision($exam, $supervisor, 10);

    // 80 × 0.7 + 50 × 0.3 = 56 + 15 = 71
    expect(app(ComposeFinalGrade::class)->handle($exam->refresh())['final_score'])->toBe(71.0);
});

it('names what is missing instead of composing an incomplete grade', function (): void {
    ['exam' => $exam, 'rubric' => $rubric, 'examiners' => $examiners] = sittingWithForms();

    $grade = app(ComposeFinalGrade::class);

    expect($grade->handle($exam)['complete'])->toBeFalse()
        ->and($grade->missing($exam))->toHaveCount(2);

    markExam($exam, $rubric, $examiners[0], 0.8);
    markExam($exam, $rubric, $examiners[1], 0.8);

    // Exam marking is done; the supervision form is not.
    expect($grade->handle($exam->refresh())['exam_score'])->toBe(80.0)
        ->and($grade->handle($exam)['complete'])->toBeFalse()
        ->and($grade->missing($exam))->toContain(
            'Penilaian proses bimbingan belum diisi lengkap oleh pembimbing.',
        );
});

it('refuses a supervision mark outside an indicator scale', function (): void {
    ['exam' => $exam, 'supervisor' => $supervisor] = sittingWithForms();

    $this->actingAs($supervisor->user)
        ->post(route('exams.supervision.store', ['stage' => ExamStage::Proposal, 'exam' => $exam]), [
            'scores' => ['1a' => 25, '1b' => 10, '1c' => 10, '1d' => 10, '2' => 10],
        ])
        ->assertSessionHasErrors('scores.1a');

    expect(SupervisionScore::count())->toBe(0);
});

it('lets only the seated pembimbing mark the supervision form', function (): void {
    ['exam' => $exam, 'examiners' => $examiners] = sittingWithForms();

    $this->actingAs($examiners[0]->user)
        ->get(route('exams.supervision.create', ['stage' => ExamStage::Proposal, 'exam' => $exam]))
        ->assertForbidden();
});

it('copies the indicator label and maximum onto each mark', function (): void {
    ['exam' => $exam, 'supervisor' => $supervisor] = sittingWithForms();

    markSupervision($exam, $supervisor, 15);

    $score = SupervisionScore::where('indicator_code', '1a')->sole();

    expect($score->indicator_label)->toStartWith('Konsultasi dengan pembimbing')
        ->and($score->max_score)->toBe(20)
        ->and($score->descriptor())->toBe('Baik');
});

it('is idempotent: a pembimbing re-submitting replaces their marks', function (): void {
    ['exam' => $exam, 'supervisor' => $supervisor] = sittingWithForms();

    markSupervision($exam, $supervisor, 12);
    markSupervision($exam, $supervisor, 18);

    expect(SupervisionScore::count())->toBe(5)
        ->and(app(ComposeFinalGrade::class)->handle($exam)['supervision_score'])->toBe(90.0);
});

it('averages the supervision score across two pembimbing rather than summing it', function (): void {
    $sitting = sittingWithForms();
    $exam = $sitting['exam'];

    $second = assignSupervisor($exam->student, order: 2);

    app(BuildExamPanel::class)->handle($exam, [
        ['lecturer_id' => $sitting['examiners'][0]->id, 'role' => ExamParticipantRole::ChairExaminer->value],
        ['lecturer_id' => $sitting['examiners'][1]->id, 'role' => ExamParticipantRole::MemberExaminer->value],
        ['lecturer_id' => $sitting['supervisor']->id, 'role' => ExamParticipantRole::Supervisor->value],
        ['lecturer_id' => $second->id, 'role' => ExamParticipantRole::Supervisor->value],
    ]);

    markSupervision($exam, $sitting['supervisor'], 16);
    markSupervision($exam, $second, 18);

    $grade = app(ComposeFinalGrade::class)->handle($exam);

    // 80 and 90, not 170 — and the maximum stays one pembimbing's 100.
    expect($grade['supervision_score'])->toBe(85.0)
        ->and($grade['supervision_max'])->toBe(100);
});

it('refuses to settle a sitting the mahasiswa was never marked present at', function (): void {
    ['exam' => $exam, 'rubric' => $rubric, 'supervisor' => $supervisor, 'examiners' => $examiners]
        = sittingWithForms();

    markExam($exam, $rubric, $examiners[0], 0.8);
    markExam($exam, $rubric, $examiners[1], 0.9);
    markSupervision($exam, $supervisor, 16);

    expect(app(ComposeFinalGrade::class)->missing($exam))->toBe([]);

    // The presensi is undone: whatever the marks say, there is no sitting to
    // record a verdict on.
    $exam->participants()
        ->where('role', ExamParticipantRole::Student)
        ->update(['attended' => false, 'attended_at' => null]);

    expect(app(ComposeFinalGrade::class)->missing($exam))
        ->toBe(['Mahasiswa belum ditandai hadir pada presensi.']);

    test()->actingAs($examiners[0]->user)
        ->post(route('exams.minutes.store', ['stage' => ExamStage::Proposal, 'exam' => $exam]), [
            'decision' => ExamResult::ConditionalPass->value,
            'revision_days' => 14,
        ])
        ->assertSessionHasErrors('decision');

    expect(ExamMinutes::count())->toBe(0);
});
