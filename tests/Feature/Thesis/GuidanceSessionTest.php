<?php

use App\Enums\GuidanceStage;
use App\Enums\GuidanceStatus;
use App\Enums\GuidanceTargetType;
use App\Enums\GuidanceType;
use App\Models\GuidanceSession;
use App\Models\Student;
use App\Models\ThesisProposal;

beforeEach(function (): void {
    seedRoles();
});

/**
 * The redesign gates guidance on an approved proposal, so most tests start
 * from a student who already has one.
 */
function studentWithApprovedProposal(): Student
{
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->approved()->create();

    return $student;
}

it('blocks creating a session until the proposal is approved', function (): void {
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->create();

    $this->actingAs($student->user)
        ->get(route('guidance-sessions.create'))
        ->assertForbidden();
});

it('lets a student open a face-to-face session with a schedule', function (): void {
    $student = studentWithApprovedProposal();

    $response = $this->actingAs($student->user)->post(route('guidance-sessions.store'), [
        'title' => 'Diskusi Bab 1 - Pendahuluan',
        'stage' => GuidanceStage::Proposal->value,
        'target_type' => 'single',
        'guidance_type' => GuidanceType::FaceToFace->value,
        'meeting_type' => 'offline',
        'meeting_location' => 'Ruang Dosen 301',
        'meeting_scheduled_at' => now()->addWeek()->format('Y-m-d\TH:i'),
    ]);

    $session = GuidanceSession::sole();

    $response->assertRedirect(route('guidance-sessions.show', $session));
    expect($session->status)->toBe(GuidanceStatus::Ongoing)
        ->and($session->meeting_location)->toBe('Ruang Dosen 301')
        ->and($session->meeting_scheduled_at)->not->toBeNull();
});

it('requires a location and schedule for a face-to-face session', function (): void {
    $student = studentWithApprovedProposal();

    $this->actingAs($student->user)
        ->post(route('guidance-sessions.store'), [
            'title' => 'Diskusi Bab 1',
            'stage' => GuidanceStage::Proposal->value,
            'target_type' => 'single',
            'guidance_type' => GuidanceType::FaceToFace->value,
            'meeting_type' => 'offline',
        ])
        ->assertSessionHasErrors(['meeting_location', 'meeting_scheduled_at']);
});

it('clears meeting details when the session is an async discussion', function (): void {
    $student = studentWithApprovedProposal();

    $this->actingAs($student->user)->post(route('guidance-sessions.store'), [
        'title' => 'Diskusi Bab 3',
        // The stage is incidental here; the assertion is about meeting fields.
        'stage' => GuidanceStage::Proposal->value,
        'target_type' => 'both',
        'guidance_type' => GuidanceType::OnlineAsync->value,
        'meeting_type' => 'offline',
        'meeting_location' => 'Ruang Dosen 301',
        'meeting_scheduled_at' => now()->addWeek()->format('Y-m-d\TH:i'),
    ]);

    $session = GuidanceSession::sole();

    expect($session->guidance_type)->toBe(GuidanceType::OnlineAsync)
        ->and($session->meeting_type)->toBeNull()
        ->and($session->meeting_location)->toBeNull()
        ->and($session->meeting_scheduled_at)->toBeNull();
});

it('filters the session list by stage within a tab', function (): void {
    $student = studentWithApprovedProposal();

    GuidanceSession::factory()->for($student)->completed()->create(['title' => 'Proposal selesai']);
    GuidanceSession::factory()->for($student)->research()->completed()->create(['title' => 'Penelitian selesai']);

    $this->actingAs($student->user)
        ->get(route('guidance-sessions.index', ['tab' => 'completed', 'stage' => 'research']))
        ->assertInertia(fn ($page) => $page
            ->has('sessions', 1)
            ->where('sessions.0.title', 'Penelitian selesai'));
});

it('lets a participant post a message in an async session', function (): void {
    $student = studentWithApprovedProposal();
    $lecturer = assignSupervisor($student);
    $session = GuidanceSession::factory()->for($student)->async()->create();

    $this->actingAs($lecturer->user)
        ->post(route('guidance-sessions.messages.store', $session), [
            'message' => 'Silakan lanjutkan penjelasan populasi dan sampel pada Bab 3.',
        ])
        ->assertRedirect();

    expect($session->messages()->count())->toBe(1)
        ->and($session->messages()->first()->user_id)->toBe($lecturer->user_id);
});

it('refuses messages on a face-to-face session', function (): void {
    $student = studentWithApprovedProposal();
    $session = GuidanceSession::factory()->for($student)->create();

    $this->actingAs($student->user)
        ->post(route('guidance-sessions.messages.store', $session), ['message' => 'Halo'])
        ->assertForbidden();
});

it('refuses messages from someone outside the session', function (): void {
    $student = studentWithApprovedProposal();
    $stranger = makeLecturer();
    $session = GuidanceSession::factory()->for($student)->async()->create();

    $this->actingAs($stranger->user)
        ->post(route('guidance-sessions.messages.store', $session), ['message' => 'Halo'])
        ->assertForbidden();
});

it('lets a supervisor mark a session complete but not the student', function (): void {
    $student = studentWithApprovedProposal();
    $lecturer = assignSupervisor($student);
    $session = GuidanceSession::factory()->for($student)->create();

    $this->actingAs($student->user)
        ->post(route('guidance-sessions.complete', $session), [])
        ->assertForbidden();

    $this->actingAs($lecturer->user)
        ->post(route('guidance-sessions.complete', $session), [
            'completion_notes' => 'Bab 1 disetujui.',
        ])
        ->assertRedirect();

    expect($session->refresh()->status)->toBe(GuidanceStatus::Completed)
        ->and($session->completed_at)->not->toBeNull();
});

it('lets the student cancel their own ongoing session', function (): void {
    $student = studentWithApprovedProposal();
    $session = GuidanceSession::factory()->for($student)->create();

    $this->actingAs($student->user)
        ->post(route('guidance-sessions.cancel', $session), [
            'cancellation_reason' => 'Bentrok dengan jadwal kuliah.',
        ])
        ->assertRedirect();

    expect($session->refresh()->status)->toBe(GuidanceStatus::Cancelled)
        ->and($session->cancellation_reason)->toBe('Bentrok dengan jadwal kuliah.');
});

it('exposes the supervisor\'s completion notes on a finished session', function (): void {
    $student = studentWithApprovedProposal();
    $session = GuidanceSession::factory()->for($student)->completed()->create([
        'completion_notes' => 'Latar belakang sudah menunjukkan kesenjangan penelitian dengan jelas.',
        'feedback' => 'Bab 1 diterima.',
    ]);

    $this->actingAs($student->user)
        ->get(route('guidance-sessions.show', $session))
        ->assertInertia(fn ($page) => $page
            ->where('session.status_label', 'Selesai')
            ->where('session.completion_notes', 'Latar belakang sudah menunjukkan kesenjangan penelitian dengan jelas.')
            ->where('session.feedback', 'Bab 1 diterima.')
            ->whereNot('session.completed_at', null));
});

it('records the completion notes a supervisor submits', function (): void {
    $student = studentWithApprovedProposal();
    $lecturer = assignSupervisor($student);
    $session = GuidanceSession::factory()->for($student)->create();

    $this->actingAs($lecturer->user)->post(route('guidance-sessions.complete', $session), [
        'completion_notes' => 'Rumusan masalah diperbaiki menjadi satu pertanyaan utama.',
        'feedback' => 'Lanjutkan ke tinjauan pustaka.',
    ]);

    expect($session->refresh()->completion_notes)
        ->toBe('Rumusan masalah diperbaiki menjadi satu pertanyaan utama.');
});

it('lists only the primary supervisor on a session targeted at Pembimbing Utama', function (): void {
    $student = studentWithApprovedProposal();
    $first = assignSupervisor($student, order: 1);
    assignSupervisor($student, order: 2);

    $session = GuidanceSession::factory()->for($student)->create([
        'target_type' => GuidanceTargetType::Single,
    ]);

    $this->actingAs($student->user)
        ->get(route('guidance-sessions.show', $session))
        ->assertInertia(fn ($page) => $page
            ->where('session.target_type_label', 'Pembimbing Utama')
            ->has('session.supervisors', 1)
            ->where('session.supervisors.0.name', $first->user->name)
            ->where('session.supervisors.0.position_label', 'Pembimbing 1'));
});

it('lists both supervisors on a session targeted at Semua Pembimbing', function (): void {
    $student = studentWithApprovedProposal();
    assignSupervisor($student, order: 1);
    $second = assignSupervisor($student, order: 2);

    $session = GuidanceSession::factory()->for($student)->create([
        'target_type' => GuidanceTargetType::Both,
    ]);

    $this->actingAs($student->user)
        ->get(route('guidance-sessions.show', $session))
        ->assertInertia(fn ($page) => $page
            ->where('session.target_type_label', 'Semua Pembimbing')
            ->has('session.supervisors', 2)
            ->where('session.supervisors.1.name', $second->user->name)
            ->where('session.supervisors.1.position_label', 'Pembimbing 2'));
});

it('shows the one supervisor a student actually has, whatever the target', function (): void {
    $student = studentWithApprovedProposal();
    $only = assignSupervisor($student, order: 1);

    $session = GuidanceSession::factory()->for($student)->create([
        'target_type' => GuidanceTargetType::Both,
    ]);

    $this->actingAs($student->user)
        ->get(route('guidance-sessions.show', $session))
        ->assertInertia(fn ($page) => $page
            ->has('session.supervisors', 1)
            ->where('session.supervisors.0.name', $only->user->name));
});

it('copes with a student who has no supervisor assigned yet', function (): void {
    $student = studentWithApprovedProposal();
    $session = GuidanceSession::factory()->for($student)->create();

    $this->actingAs($student->user)
        ->get(route('guidance-sessions.show', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('session.supervisors', 0));
});

it('still shows the full supervisory team on the proposal, not the session subset', function (): void {
    $student = makeStudent();
    assignSupervisor($student, order: 1);
    assignSupervisor($student, order: 2);
    $proposal = ThesisProposal::factory()->for($student)->for($student->department)->approved()->create();

    $this->actingAs($student->user)
        ->get(route('proposals.show', $proposal))
        ->assertInertia(fn ($page) => $page->has('proposal.supervisors', 2));
});

it('refuses a research-stage session before the proposal exam is passed', function (): void {
    $student = studentWithApprovedProposal();

    $this->actingAs($student->user)
        ->post(route('guidance-sessions.store'), [
            'title' => 'Bimbingan Hasil Analisis',
            'stage' => GuidanceStage::Research->value,
            'target_type' => 'single',
            'guidance_type' => GuidanceType::OnlineAsync->value,
        ])
        ->assertSessionHasErrors('stage');

    expect(GuidanceSession::count())->toBe(0);
});

it('allows a research-stage session once the proposal exam is passed', function (): void {
    $student = studentWithApprovedProposal();
    passProposalExam($student);

    $this->actingAs($student->user)
        ->post(route('guidance-sessions.store'), [
            'title' => 'Bimbingan Hasil Analisis',
            'stage' => GuidanceStage::Research->value,
            'target_type' => 'single',
            'guidance_type' => GuidanceType::OnlineAsync->value,
        ])
        ->assertSessionHasNoErrors();

    expect(GuidanceSession::sole()->stage)->toBe(GuidanceStage::Research);
});

it('still allows proposal-stage sessions before the exam', function (): void {
    $student = studentWithApprovedProposal();

    $this->actingAs($student->user)
        ->post(route('guidance-sessions.store'), [
            'title' => 'Bimbingan Bab 1',
            'stage' => GuidanceStage::Proposal->value,
            'target_type' => 'single',
            'guidance_type' => GuidanceType::OnlineAsync->value,
        ])
        ->assertSessionHasNoErrors();

    expect(GuidanceSession::count())->toBe(1);
});

it('marks the research stage as locked on the create screen', function (): void {
    $student = studentWithApprovedProposal();

    $this->actingAs($student->user)
        ->get(route('guidance-sessions.create'))
        ->assertInertia(fn ($page) => $page
            ->where('options.stages.0.disabled', false)
            ->where('options.stages.1.value', 'research')
            ->where('options.stages.1.disabled', true)
            ->where('options.stages.1.reason', fn (string $reason) => str_contains($reason, 'ujian proposal')));
});

it('ignores an unknown stage filter instead of hiding everything', function (): void {
    $student = studentWithApprovedProposal();
    GuidanceSession::factory()->count(2)->for($student)->create();

    $this->actingAs($student->user)
        ->get(route('guidance-sessions.index', ['tab' => 'scheduled', 'stage' => 'nonsense']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sessions', 2)
            ->where('filters.stage', 'all')
            ->has('filterOptions.stages', 3));
});
