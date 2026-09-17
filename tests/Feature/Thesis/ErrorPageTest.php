<?php

use App\Models\ThesisProposal;

beforeEach(function (): void {
    seedRoles();
});

/**
 * These assert the page the user actually lands on. Checking the Gate alone is
 * not enough: the reason has to survive Laravel wrapping the refusal in an HTTP
 * exception before Inertia renders it.
 */
it('renders a refusal as the themed error page carrying the reason', function (): void {
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->approved()->create([
        'title' => 'Analisis Sentimen Ulasan E-Commerce',
    ]);

    $this->actingAs($student->user)
        ->get(route('proposals.create'))
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page
            ->component('error')
            ->where('status', 403)
            ->where('reason', fn (string $reason) => str_contains($reason, 'sudah memiliki proposal aktif')
                && str_contains($reason, 'Analisis Sentimen Ulasan E-Commerce')));
});

it('explains a guidance refusal on the error page too', function (): void {
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->create();

    $this->actingAs($student->user)
        ->get(route('guidance-sessions.create'))
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page
            ->component('error')
            ->where('reason', fn (string $reason) => str_contains($reason, 'setelah proposal disetujui pembimbing')));
});

it('never leaks the generic English refusal to the user', function (): void {
    $student = makeStudent();
    $other = makeStudent();
    $proposal = ThesisProposal::factory()->for($other)->for($other->department)->create();

    $this->actingAs($student->user)
        ->get(route('proposals.show', $proposal))
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page
            ->component('error')
            ->where('status', 403)
            // No tailored reason here, so the page falls back to its own copy
            // rather than showing Laravel's English string.
            ->where('reason', null));
});

it('renders a missing page as the themed error page', function (): void {
    $student = makeStudent();

    $this->actingAs($student->user)
        ->get('/proposals/999999')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('error')->where('status', 404));
});

it('shows the error page to a signed-out visitor without an app shell', function (): void {
    $this->get('/halaman-yang-tidak-ada')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('error')->where('status', 404));
});
