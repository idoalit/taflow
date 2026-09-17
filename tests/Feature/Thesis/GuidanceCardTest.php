<?php

use App\Models\GuidanceSession;
use App\Models\ThesisProposal;

beforeEach(function (): void {
    seedRoles();
});

it('prints only the completed sessions for the chosen stage', function (): void {
    $student = makeStudent();
    $supervisor = assignSupervisor($student);
    $supervisor->user->update(['name' => 'Dr. Budi Santoso']);

    ThesisProposal::factory()->for($student)->for($student->department)->approved()->create([
        'title' => 'Analisis Sentimen Ulasan E-Commerce',
    ]);

    GuidanceSession::factory()->for($student)->completed()->create(['title' => 'Bimbingan Bab 1']);
    GuidanceSession::factory()->for($student)->create(['title' => 'Bimbingan Bab 2 belum selesai']);
    GuidanceSession::factory()->for($student)->research()->completed()->create(['title' => 'Bimbingan Hasil']);

    $this->actingAs($student->user)
        ->get(route('guidance-cards.print', ['stage' => 'proposal']))
        ->assertOk()
        ->assertSee('KARTU BIMBINGAN PROPOSAL')
        ->assertSee('Bimbingan Bab 1')
        ->assertSee('Dr. Budi Santoso')
        ->assertSee('Analisis Sentimen Ulasan E-Commerce')
        ->assertDontSee('Bimbingan Bab 2 belum selesai')
        ->assertDontSee('Bimbingan Hasil');
});

it('prints the research card separately', function (): void {
    $student = makeStudent();
    GuidanceSession::factory()->for($student)->research()->completed()->create(['title' => 'Bimbingan Hasil']);

    $this->actingAs($student->user)
        ->get(route('guidance-cards.print', ['stage' => 'research']))
        ->assertOk()
        ->assertSee('KARTU BIMBINGAN HASIL PENELITIAN')
        ->assertSee('Bimbingan Hasil');
});

it('lets a supervisor print a card for their student but blocks a stranger', function (): void {
    $student = makeStudent();
    $lecturer = assignSupervisor($student);
    $stranger = makeLecturer();

    $this->actingAs($lecturer->user)
        ->get(route('guidance-cards.print-for-student', ['stage' => 'proposal', 'student' => $student]))
        ->assertOk();

    $this->actingAs($stranger->user)
        ->get(route('guidance-cards.print-for-student', ['stage' => 'proposal', 'student' => $student]))
        ->assertForbidden();
});

it('rejects an unknown guidance stage', function (): void {
    $student = makeStudent();

    $this->actingAs($student->user)
        ->get(route('guidance-cards.print', ['stage' => 'wisuda']))
        ->assertNotFound();
});
