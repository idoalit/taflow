<?php

use App\Enums\ApprovalStatus;
use App\Enums\GuidanceStage;
use App\Enums\ProposalStatus;
use App\Enums\UserRole;
use App\Models\GuidanceSession;
use App\Models\ThesisProposal;
use App\Models\User;

beforeEach(function (): void {
    seedRoles();
});

/**
 * A submitted proposal with two supervisors, which is where every review flow
 * in the Dosen role starts.
 */
function proposalAwaitingReview(): ThesisProposal
{
    $student = makeStudent();
    assignSupervisor($student, order: 1);
    assignSupervisor($student, order: 2);

    return ThesisProposal::factory()->for($student)->for($student->department)->create();
}

it('opens a Persamaan Persepsi slot per supervisor when a proposal is approved', function (): void {
    $proposal = proposalAwaitingReview();
    $supervisor = $proposal->student->supervisors->first();

    $this->actingAs($supervisor->user)
        ->post(route('proposals.approve', $proposal))
        ->assertRedirect();

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Approved)
        ->and($proposal->approved_by)->toBe($supervisor->id)
        ->and($proposal->perceptionAlignments()->count())->toBe(2)
        ->and($proposal->perceptionAlignments()->pluck('supervisor_order')->sort()->values()->all())
        ->toBe([1, 2]);
});

it('sends a proposal back for revision with a reason', function (): void {
    $proposal = proposalAwaitingReview();
    $supervisor = $proposal->student->supervisors->first();

    $this->actingAs($supervisor->user)
        ->post(route('proposals.reject', $proposal), [
            'rejection_reason' => 'Rumusan masalah belum spesifik, mohon dipertajam.',
        ])
        ->assertRedirect();

    expect($proposal->refresh()->status)->toBe(ProposalStatus::NeedsRevision)
        ->and($proposal->rejection_reason)->toContain('belum spesifik');
});

it('requires a reason when asking for revision', function (): void {
    $proposal = proposalAwaitingReview();
    $supervisor = $proposal->student->supervisors->first();

    $this->actingAs($supervisor->user)
        ->post(route('proposals.reject', $proposal), ['rejection_reason' => 'pendek'])
        ->assertSessionHasErrors('rejection_reason');

    expect($proposal->refresh()->status)->toBe(ProposalStatus::Submitted);
});

it('does not let an unrelated lecturer review the proposal', function (): void {
    $proposal = proposalAwaitingReview();
    $stranger = makeLecturer();

    $this->actingAs($stranger->user)
        ->post(route('proposals.approve', $proposal))
        ->assertForbidden();
});

it('takes Persamaan Persepsi feedback in supervisor order', function (): void {
    $proposal = proposalAwaitingReview();
    [$first, $second] = $proposal->student->supervisors->all();

    $this->actingAs($first->user)->post(route('proposals.approve', $proposal));

    $slots = $proposal->perceptionAlignments()->orderBy('supervisor_order')->get();

    // The second supervisor cannot go first.
    $this->actingAs($second->user)
        ->post(route('perception-alignments.feedback', $slots[1]), [
            'feedback' => 'Saya setuju dengan arahan pembimbing utama.',
        ])
        ->assertForbidden();

    $this->actingAs($first->user)
        ->post(route('perception-alignments.feedback', $slots[0]), [
            'feedback' => 'Topik layak dilanjutkan, pertajam teknik analisis data.',
        ])
        ->assertRedirect();

    // Now it is the second supervisor's turn.
    $this->actingAs($second->user)
        ->post(route('perception-alignments.feedback', $slots[1]), [
            'feedback' => 'Saya setuju dengan arahan pembimbing utama.',
        ])
        ->assertRedirect();

    expect($proposal->refresh()->alignmentStatusLabel())->toBe('Selesai');
});

it('reports which supervisor the round is waiting on', function (): void {
    $proposal = proposalAwaitingReview();
    $first = $proposal->student->supervisors->first();

    $this->actingAs($first->user)->post(route('proposals.approve', $proposal));
    expect($proposal->refresh()->alignmentStatusLabel())->toBe('Menunggu Feedback Pembimbing 1');

    $slot = $proposal->perceptionAlignments()->where('supervisor_order', 1)->sole();
    $this->actingAs($first->user)->post(route('perception-alignments.feedback', $slot), [
        'feedback' => 'Topik layak dilanjutkan dan sudah sesuai roadmap prodi.',
    ]);

    expect($proposal->refresh()->alignmentStatusLabel())->toBe('Menunggu Feedback Pembimbing 2');
});

it('sends the proposal back when a supervisor rejects during Persamaan Persepsi', function (): void {
    $proposal = proposalAwaitingReview();
    $first = $proposal->student->supervisors->first();

    $this->actingAs($first->user)->post(route('proposals.approve', $proposal));
    $slot = $proposal->perceptionAlignments()->where('supervisor_order', 1)->sole();

    $this->actingAs($first->user)
        ->post(route('perception-alignments.reject', $slot), [
            'rejection_reason' => 'Metodologi tidak sesuai dengan rumusan masalah.',
        ])
        ->assertRedirect(route('perception-alignments.index'));

    expect($slot->refresh()->status)->toBe(ApprovalStatus::Rejected)
        ->and($proposal->refresh()->status)->toBe(ProposalStatus::NeedsRevision)
        ->and($proposal->alignmentStatusLabel())->toBe('Ditolak');
});

it('lists only the rounds for students the lecturer supervises', function (): void {
    $mine = proposalAwaitingReview();
    $supervisor = $mine->student->supervisors->first();
    $this->actingAs($supervisor->user)->post(route('proposals.approve', $mine));

    $theirs = proposalAwaitingReview();
    $theirSupervisor = $theirs->student->supervisors->first();
    $this->actingAs($theirSupervisor->user)->post(route('proposals.approve', $theirs));

    $this->actingAs($supervisor->user)
        ->get(route('perception-alignments.index'))
        ->assertInertia(fn ($page) => $page
            ->has('rounds', 1)
            ->where('rounds.0.id', $mine->id)
            ->where('rounds.0.needs_me', true));
});

it('guards guidance until the alignment gate has been opened by approval', function (): void {
    $proposal = proposalAwaitingReview();

    expect($proposal->student->user->can('create', GuidanceSession::class))->toBeFalse();

    $this->actingAs($proposal->student->supervisors->first()->user)
        ->post(route('proposals.approve', $proposal));

    expect($proposal->student->user->can('create', GuidanceSession::class))->toBeTrue()
        ->and($proposal->student->completedGuidanceCount(GuidanceStage::Proposal))->toBe(0);
});

it('shows the lecturer dashboard with their own counts', function (): void {
    $proposal = proposalAwaitingReview();
    $supervisor = $proposal->student->supervisors->first();

    $this->actingAs($supervisor->user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('thesis/dashboard/lecturer')
            ->has('students', 1)
            ->where('kpis.0.value', 1)
            ->where('kpis.1.value', 1));
});

it('refuses the lecturer dashboard to an account with no dosen record', function (): void {
    $user = User::factory()->create();
    $user->syncRoles([UserRole::Lecturer->value]);

    $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
});
