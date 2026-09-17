<?php

use App\Enums\ExamParticipantRole;
use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Models\ProposalExam;
use Inertia\Testing\AssertableInertia;

/**
 * The detail screen is where a sitting is actually run, so it has to hand the
 * page every seat, sheet and control the viewer is allowed to touch — and none
 * of the ones they are not.
 */
beforeEach(function (): void {
    seedRoles();
});

function showExamAs($user, $exam)
{
    return test()->actingAs($user)
        ->get(route('exams.show', ['stage' => ExamStage::Proposal, 'exam' => $exam]));
}

it('hands the chair the presensi, the berita acara and the revision sheet', function (): void {
    $sitting = sittingWithForms();
    $exam = $sitting['exam'];
    $chair = $sitting['examiners'][0];

    markExam($exam, $sitting['rubric'], $chair, 0.8);
    markExam($exam, $sitting['rubric'], $sitting['examiners'][1], 0.9);
    markSupervision($exam, $sitting['supervisor'], 16);

    showExamAs($chair->user, $exam)->assertInertia(
        fn (AssertableInertia $page) => $page
            ->component('thesis/exams/show')
            // Two examiners, one pembimbing and the student.
            ->has('participants', 4)
            ->where('participants.0.role_label', ExamParticipantRole::ChairExaminer->label())
            ->where('participants.0.is_me', true)
            ->where('participants.0.can_mark', true)
            ->where('canDecide', true)
            ->where('canWriteRevisions', true)
            ->where('canRelease', false)
            ->where('released', false)
            ->has('results')
            ->has('documents')
            ->where('urls.minutes', route('exams.minutes.store', ['stage' => ExamStage::Proposal, 'exam' => $exam])),
    );
});

it('does not offer a member examiner the berita acara', function (): void {
    $sitting = sittingWithForms();

    showExamAs($sitting['examiners'][1]->user, $sitting['exam'])->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('canDecide', false)
            ->where('canWriteRevisions', true)
            ->where('canRelease', false),
    );
});

it('keeps the student out of the panel controls until the result is released', function (): void {
    ['exam' => $exam, 'examiners' => $examiners, 'rubric' => $rubric, 'supervisor' => $supervisor]
        = sittingWithForms();

    $student = $exam->student;

    showExamAs($student->user, $exam)->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('canDecide', false)
            ->where('canWriteRevisions', false)
            ->where('canTickRevisions', false)
            ->where('canViewResult', false)
            ->where('allRevisions', [])
            ->where('myRevisions', []),
    );

    markExam($exam, $rubric, $examiners[0], 0.8);
    markExam($exam, $rubric, $examiners[1], 0.9);
    markSupervision($exam, $supervisor, 16);

    $this->actingAs($examiners[0]->user)->post(route('exams.revisions.store', ['stage' => ExamStage::Proposal, 'exam' => $exam]), [
        'notes' => [['body' => 'Perbaiki rumusan masalah pada Bab I.']],
    ])->assertRedirect();

    $this->actingAs($examiners[0]->user)->post(route('exams.minutes.store', ['stage' => ExamStage::Proposal, 'exam' => $exam]), [
        'decision' => ExamResult::ConditionalPass->value,
        'revision_days' => 14,
    ])->assertRedirect();

    releaseExam($exam);

    showExamAs($student->user, $exam->refresh())->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('released', true)
            ->where('canViewResult', true)
            ->where('canTickRevisions', true)
            ->has('allRevisions', 1)
            ->where('allRevisions.0.body', 'Perbaiki rumusan masalah pada Bab I.'),
    );
});

it('gives each examiner back only their own revision sheet', function (): void {
    ['exam' => $exam, 'examiners' => $examiners] = sittingWithForms();

    $this->actingAs($examiners[0]->user)->post(route('exams.revisions.store', ['stage' => ExamStage::Proposal, 'exam' => $exam]), [
        'notes' => [['body' => 'Lengkapi tabel matriks konfusi pada Bab IV.']],
    ])->assertRedirect();

    showExamAs($examiners[0]->user, $exam)->assertInertia(
        fn (AssertableInertia $page) => $page->has('myRevisions', 1),
    );

    showExamAs($examiners[1]->user, $exam)->assertInertia(
        fn (AssertableInertia $page) => $page->where('myRevisions', []),
    );
});

it('sends the block rubric to the scoring form as blocks, not a flat list', function (): void {
    ['exam' => $exam, 'examiners' => $examiners] = sittingWithForms();

    $this->actingAs($examiners[0]->user)
        ->get(route('exams.evaluate', ['stage' => ExamStage::Proposal, 'exam' => $exam]))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('thesis/exams/evaluate')
                ->where('rubric.block_additive', true)
                // A, B, C, D on the faculty form.
                ->has('rubric.blocks', 4)
                ->where('rubric.blocks.0.code', 'A')
                ->where('rubric.blocks.0.max', 15)
                // B is the only block split into sub-blocks.
                ->has('rubric.blocks.1.subblocks', 4)
                ->where('rubric.blocks.1.subblocks.0.code', 'I')
                ->has('rubric.criteria', 28)
                // Weight carries no meaning under this scheme; the maxima do.
                ->where('rubric.criteria.0.weight', null)
                ->where('rubric.criteria.0.item_number', 1),
        );
});

it('still sends a weighted rubric with its weights', function (): void {
    $student = makeStudent();
    $examiner = assignSupervisor($student);
    $rubric = assignRubric($student->department);
    $exam = ProposalExam::factory()->for($student)->create();

    $this->actingAs($examiner->user)
        ->get(route('exams.evaluate', ['stage' => ExamStage::Proposal, 'exam' => $exam]))
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('rubric.block_additive', false)
                ->where('rubric.criteria.0.weight', $rubric->criteria->first()->weight),
        );
});
