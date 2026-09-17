<?php

use App\Enums\UserRole;
use App\Models\GuidanceSession;
use App\Models\ThesisProposal;
use App\Models\User;

beforeEach(function (): void {
    seedRoles();
});

it('shows the student their active proposal and nearest session', function (): void {
    $student = makeStudent();
    $lecturer = assignSupervisor($student);
    $lecturer->user->update(['name' => 'Dr. Budi Santoso']);

    ThesisProposal::factory()->for($student)->for($student->department)->approved()->create([
        'title' => 'Analisis Sentimen Ulasan E-Commerce dengan BERT',
    ]);

    GuidanceSession::factory()->for($student)->create([
        'title' => 'Bimbingan Bab 3 - Metodologi',
        'meeting_scheduled_at' => now()->addDays(2),
    ]);

    $this->actingAs($student->user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('thesis/dashboard/student')
            ->where('proposal.title', 'Analisis Sentimen Ulasan E-Commerce dengan BERT')
            ->where('proposal.status_label', 'Disetujui')
            ->where('primarySupervisor', 'Dr. Budi Santoso')
            ->where('upcomingSession.title', 'Bimbingan Bab 3 - Metodologi')
            ->has('activity'));
});

it('renders the dashboard for a student with nothing yet', function (): void {
    $student = makeStudent();

    $this->actingAs($student->user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('thesis/dashboard/student')
            ->where('proposal', null)
            ->where('upcomingSession', null)
            ->has('activity', 0));
});

it('rejects a student account with no student record attached', function (): void {
    $user = User::factory()->create();
    $user->syncRoles([UserRole::Student->value]);

    $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
});

it('offers the proposal shortcut only when a proposal can actually be created', function (): void {
    $student = makeStudent();

    $this->actingAs($student->user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('canCreateProposal', true));
});

it('stops offering the create shortcut once a proposal is in flight', function (): void {
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->approved()->create();

    $this->actingAs($student->user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('canCreateProposal', false));
});

it('shares whether the program studi requires a ujian proposal', function (): void {
    $student = makeStudent();

    $this->actingAs($student->user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('thesis.requiresProposalExam', true));

    $other = makeStudent(makeDepartment(['requires_proposal_exam' => false]));

    $this->actingAs($other->user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('thesis.requiresProposalExam', false));
});

it('shares no thesis settings for a user who is not a student', function (): void {
    $lecturer = makeLecturer();

    $this->actingAs($lecturer->user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('thesis', null));
});
