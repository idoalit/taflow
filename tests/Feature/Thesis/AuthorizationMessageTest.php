<?php

use App\Enums\GuidanceStage;
use App\Models\GuidanceSession;
use App\Models\ProposalExamRequest;
use App\Models\ThesisProposal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

beforeEach(function (): void {
    seedRoles();
});

/**
 * A refusal has to say why. These assert the reason the policy gives, since
 * that text is what the error page shows the student.
 */
it('explains that a student already has an active proposal', function (): void {
    $student = makeStudent();
    $proposal = ThesisProposal::factory()->for($student)->for($student->department)->approved()->create([
        'title' => 'Analisis Sentimen Ulasan E-Commerce',
    ]);

    $denial = Gate::forUser($student->user)->inspect('create', ThesisProposal::class);

    expect($denial->allowed())->toBeFalse()
        ->and($denial->message())
        ->toContain('sudah memiliki proposal aktif')
        ->toContain($proposal->title)
        ->toContain('disetujui')
        ->not->toBe('This action is unauthorized.');
});

it('explains that guidance needs an approved proposal first', function (): void {
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->create();

    $denial = Gate::forUser($student->user)->inspect('create', GuidanceSession::class);

    expect($denial->allowed())->toBeFalse()
        ->and($denial->message())->toContain('setelah proposal disetujui pembimbing');
});

it('explains that guidance needs a proposal at all', function (): void {
    $student = makeStudent();

    expect(Gate::forUser($student->user)->inspect('create', GuidanceSession::class)->message())
        ->toContain('belum mengajukan proposal');
});

it('explains that an exam request is still being processed', function (): void {
    $student = makeStudent();
    $proposal = ThesisProposal::factory()->for($student)->for($student->department)->approved()->create();
    ProposalExamRequest::factory()->for($student)->for($proposal)->create();

    expect(Gate::forUser($student->user)->inspect('create', ProposalExamRequest::class)->message())
        ->toContain('masih diproses')
        ->toContain('menunggu verifikasi dosen pembimbing');
});

it('carries the reason through the thrown authorization exception', function (): void {
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->create();

    try {
        Gate::forUser($student->user)->authorize('create', ThesisProposal::class);
        $this->fail('Expected the gate to refuse.');
    } catch (AuthorizationException $exception) {
        expect($exception->getMessage())->toContain('sudah memiliki proposal aktif');
    }
});

it('explains that research guidance needs a passed ujian proposal', function (): void {
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->approved()->create();

    $denial = Gate::forUser($student->user)
        ->inspect('create', [GuidanceSession::class, GuidanceStage::Research]);

    expect($denial->allowed())->toBeFalse()
        ->and($denial->message())
        ->toContain('lulus ujian proposal')
        ->not->toBe('This action is unauthorized.');
});

it('still allows proposal guidance while research is refused', function (): void {
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->approved()->create();

    expect(Gate::forUser($student->user)
        ->inspect('create', [GuidanceSession::class, GuidanceStage::Proposal])
        ->allowed())->toBeTrue();
});

it('explains that the program studi requires no ujian proposal', function (): void {
    $department = makeDepartment(['requires_proposal_exam' => false]);
    $student = makeStudent($department);
    ThesisProposal::factory()->for($student)->for($department)->approved()->create();

    expect(Gate::forUser($student->user)->inspect('create', ProposalExamRequest::class)->message())
        ->toContain('tidak mewajibkan ujian proposal');
});
