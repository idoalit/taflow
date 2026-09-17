<?php

use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\ExamStatus;
use App\Enums\RevisionStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ExamMinutes;
use App\Models\RevisionNote;
use App\Support\Workspace;

beforeEach(function (): void {
    seedRoles();
});

/** A fully marked sitting, ready for a decision. */
function markedSitting(): array
{
    $sitting = sittingWithForms();

    markExam($sitting['exam'], $sitting['rubric'], $sitting['examiners'][0], 0.8);
    markExam($sitting['exam'], $sitting['rubric'], $sitting['examiners'][1], 0.9);
    markSupervision($sitting['exam'], $sitting['supervisor'], 16);

    return $sitting;
}

function route_for(string $name, $exam): string
{
    return route($name, ['stage' => ExamStage::Proposal, 'exam' => $exam]);
}

it('lets only the chair settle the berita acara', function (): void {
    ['exam' => $exam, 'examiners' => $examiners] = markedSitting();

    $this->actingAs($examiners[1]->user)
        ->post(route_for('exams.minutes.store', $exam), [
            'decision' => ExamResult::Passed->value,
            'revision_days' => 7,
        ])
        ->assertForbidden();

    $this->actingAs($examiners[0]->user)
        ->post(route_for('exams.minutes.store', $exam), [
            'decision' => ExamResult::ConditionalPass->value,
            'revision_days' => 14,
            'notes' => 'Perbaiki bab IV.',
        ])
        ->assertRedirect();

    $minutes = ExamMinutes::sole();

    expect($minutes->decision)->toBe(ExamResult::ConditionalPass)
        ->and($minutes->revision_days)->toBe(14)
        // The score is written down as it stood, not read live afterwards.
        ->and((float) $minutes->final_score)->toBe(84.5)
        ->and($minutes->letter)->toBe('AB')
        ->and($exam->refresh()->status)->toBe(ExamStatus::Scored)
        ->and(AuditLog::where('action', 'minutes.decided')->count())->toBe(1);
});

it('refuses a decision while marking is incomplete', function (): void {
    $sitting = sittingWithForms();

    $this->actingAs($sitting['examiners'][0]->user)
        ->post(route_for('exams.minutes.store', $sitting['exam']), [
            'decision' => ExamResult::Passed->value,
            'revision_days' => 7,
        ])
        ->assertSessionHasErrors('decision');

    expect(ExamMinutes::count())->toBe(0);
});

it('releases only once the minutes exist, and notifies the student', function (): void {
    ['exam' => $exam, 'examiners' => $examiners] = markedSitting();
    $department = $exam->student->department;
    $admin = makeAdmin($department);
    $session = ['workspace' => (new Workspace(UserRole::Admin, $department))->key()];

    $this->actingAs($admin)->withSession($session)
        ->post(route_for('admin.exams.release', $exam))
        ->assertSessionHasErrors('release');

    $this->actingAs($examiners[0]->user)->post(route_for('exams.minutes.store', $exam), [
        'decision' => ExamResult::Passed->value,
        'revision_days' => 0,
    ]);

    $this->actingAs($admin)->withSession($session)
        ->post(route_for('admin.exams.release', $exam))
        ->assertRedirect();

    expect($exam->refresh()->status)->toBe(ExamStatus::Released)
        ->and($exam->released_at)->not->toBeNull()
        ->and($exam->released_by)->toBe($admin->id)
        ->and($exam->student->user->notifications()->count())->toBeGreaterThan(0)
        ->and(AuditLog::where('action', 'exam.released')->count())->toBe(1);
});

it('locks everything behind a released result', function (): void {
    ['exam' => $exam, 'examiners' => $examiners, 'supervisor' => $supervisor] = markedSitting();
    $department = $exam->student->department;
    $admin = makeAdmin($department);
    $session = ['workspace' => (new Workspace(UserRole::Admin, $department))->key()];

    $this->actingAs($examiners[0]->user)->post(route_for('exams.minutes.store', $exam), [
        'decision' => ExamResult::Passed->value,
        'revision_days' => 0,
    ]);
    $this->actingAs($admin)->withSession($session)->post(route_for('admin.exams.release', $exam));

    $exam->refresh();

    // The chair can no longer change the decision…
    $this->actingAs($examiners[0]->user)
        ->post(route_for('exams.minutes.store', $exam), [
            'decision' => ExamResult::Failed->value,
            'revision_days' => 0,
        ])
        ->assertForbidden();

    // …nor write revisions…
    $this->actingAs($examiners[0]->user)
        ->post(route_for('exams.revisions.store', $exam), ['notes' => [['body' => 'Terlambat.']]])
        ->assertForbidden();

    // …nor the pembimbing re-mark the supervision form.
    $this->actingAs($supervisor->user)
        ->get(route_for('exams.supervision.create', $exam))
        ->assertForbidden();

    expect(ExamMinutes::sole()->decision)->toBe(ExamResult::Passed);
});

it('reopens a released result only with a reason, and records it', function (): void {
    ['exam' => $exam, 'examiners' => $examiners] = markedSitting();
    $department = $exam->student->department;
    $admin = makeAdmin($department);
    $session = ['workspace' => (new Workspace(UserRole::Admin, $department))->key()];

    $this->actingAs($examiners[0]->user)->post(route_for('exams.minutes.store', $exam), [
        'decision' => ExamResult::Passed->value,
        'revision_days' => 0,
    ]);
    $this->actingAs($admin)->withSession($session)->post(route_for('admin.exams.release', $exam));

    $this->actingAs($admin)->withSession($session)
        ->delete(route_for('admin.exams.unlock', $exam), ['reason' => 'pendek'])
        ->assertSessionHasErrors('reason');

    expect($exam->refresh()->status)->toBe(ExamStatus::Released);

    $this->actingAs($admin)->withSession($session)
        ->delete(route_for('admin.exams.unlock', $exam), [
            'reason' => 'Salah entri nilai penguji kedua, disepakati rapat dewan penguji.',
        ])
        ->assertRedirect();

    $log = AuditLog::where('action', 'exam.unlocked')->sole();

    expect($exam->refresh()->status)->toBe(ExamStatus::Scored)
        ->and($exam->released_at)->toBeNull()
        ->and($log->reason)->toContain('Salah entri nilai')
        ->and($log->user_id)->toBe($admin->id);
});

it('numbers an examiner\'s revision sheet in the order it was written', function (): void {
    ['exam' => $exam, 'examiners' => $examiners] = markedSitting();

    $this->actingAs($examiners[0]->user)
        ->post(route_for('exams.revisions.store', $exam), [
            'notes' => [
                ['body' => 'Perbaiki rumusan masalah.'],
                ['body' => 'Tambahkan referensi terbaru.'],
            ],
        ])
        ->assertRedirect();

    expect(RevisionNote::orderBy('position')->pluck('body')->all())
        ->toBe(['Perbaiki rumusan masalah.', 'Tambahkan referensi terbaru.'])
        ->and(RevisionNote::pluck('position')->all())->toBe([1, 2]);
});

it('keeps a ticked revision ticked when the examiner reorders the sheet', function (): void {
    ['exam' => $exam, 'examiners' => $examiners] = markedSitting();
    $department = $exam->student->department;
    $admin = makeAdmin($department);
    $session = ['workspace' => (new Workspace(UserRole::Admin, $department))->key()];

    $this->actingAs($examiners[0]->user)->post(route_for('exams.revisions.store', $exam), [
        'notes' => [['body' => 'Poin satu.'], ['body' => 'Poin dua.']],
    ]);

    $this->actingAs($examiners[0]->user)->post(route_for('exams.minutes.store', $exam), [
        'decision' => ExamResult::ConditionalPass->value,
        'revision_days' => 7,
    ]);
    $this->actingAs($admin)->withSession($session)->post(route_for('admin.exams.release', $exam));

    $first = RevisionNote::where('body', 'Poin satu.')->sole();

    $this->actingAs($exam->student->user)
        ->post(route('revisions.toggle', $first))
        ->assertRedirect();

    expect($first->refresh()->status)->toBe(RevisionStatus::Done);

    // Reordered by the examiner after an unlock: the tick follows the line.
    $this->actingAs($admin)->withSession($session)->delete(route_for('admin.exams.unlock', $exam), [
        'reason' => 'Perlu menambah satu poin revisi yang terlewat.',
    ]);

    $this->actingAs($examiners[0]->user)->post(route_for('exams.revisions.store', $exam), [
        'notes' => [['body' => 'Poin dua.'], ['body' => 'Poin satu.'], ['body' => 'Poin tiga.']],
    ]);

    expect(RevisionNote::where('body', 'Poin satu.')->sole())
        ->status->toBe(RevisionStatus::Done)
        ->position->toBe(2);
});

it('hides the result from the student until it is released', function (): void {
    ['exam' => $exam, 'examiners' => $examiners] = markedSitting();
    $student = $exam->student;

    expect($student->user->can('viewResult', $exam))->toBeFalse()
        ->and($examiners[0]->user->can('viewResult', $exam))->toBeTrue();

    $department = $student->department;
    $admin = makeAdmin($department);
    $this->actingAs($examiners[0]->user)->post(route_for('exams.minutes.store', $exam), [
        'decision' => ExamResult::Passed->value,
        'revision_days' => 0,
    ]);
    $this->actingAs($admin)
        ->withSession(['workspace' => (new Workspace(UserRole::Admin, $department))->key()])
        ->post(route_for('admin.exams.release', $exam));

    expect($student->user->fresh()->can('viewResult', $exam->refresh()))->toBeTrue();
});
