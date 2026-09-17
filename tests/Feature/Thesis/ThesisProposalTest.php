<?php

use App\Enums\ProposalStatus;
use App\Models\ThesisProposal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedRoles();
    Storage::fake('local');
});

it('lets a student submit a proposal, with the alignment round still closed', function (): void {
    $student = makeStudent();
    assignSupervisor($student, order: 1);
    assignSupervisor($student, order: 2);

    $response = $this->actingAs($student->user)->post(route('proposals.store'), [
        'title' => 'Analisis Sentimen Ulasan E-Commerce dengan BERT',
        'background' => str_repeat('Konteks penelitian yang cukup panjang. ', 5),
        'problem_formulation' => 'Bagaimana performa model BERT dibanding leksikon?',
        'objectives' => 'Membangun dan mengevaluasi model klasifikasi sentimen.',
        'document' => UploadedFile::fake()->create('proposal.pdf', 200, 'application/pdf'),
    ]);

    $proposal = ThesisProposal::sole();

    $response->assertRedirect(route('proposals.show', $proposal));
    expect($proposal->status)->toBe(ProposalStatus::Submitted)
        ->and($proposal->student_id)->toBe($student->id)
        ->and($proposal->department_id)->toBe($student->department_id);

    // Persamaan Persepsi only opens once a supervisor approves the proposal.
    expect($proposal->perceptionAlignments()->count())->toBe(0);

    Storage::disk('local')->assertExists($proposal->document_path);
});

it('rejects a proposal submission that is missing its document', function (): void {
    $student = makeStudent();

    $this->actingAs($student->user)
        ->post(route('proposals.store'), [
            'title' => 'Judul',
            'background' => str_repeat('Konteks. ', 10),
            'problem_formulation' => 'Rumusan masalah yang memadai.',
            'objectives' => 'Tujuan penelitian yang memadai.',
        ])
        ->assertSessionHasErrors('document');

    expect(ThesisProposal::count())->toBe(0);
});

it('hides the submit action while a proposal is already in flight', function (): void {
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->create();

    $this->actingAs($student->user)
        ->get(route('proposals.index'))
        ->assertInertia(fn ($page) => $page->where('canCreate', false));

    $this->actingAs($student->user)
        ->get(route('proposals.create'))
        ->assertForbidden();
});

it('allows a new proposal once the previous one was rejected', function (): void {
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->rejected()->create();

    $this->actingAs($student->user)
        ->get(route('proposals.create'))
        ->assertOk();
});

it('does not let a student open another student\'s proposal', function (): void {
    $student = makeStudent();
    $other = makeStudent();
    $proposal = ThesisProposal::factory()->for($other)->for($other->department)->create();

    $this->actingAs($student->user)
        ->get(route('proposals.show', $proposal))
        ->assertForbidden();
});

it('lets an assigned supervisor open the proposal', function (): void {
    $student = makeStudent();
    $lecturer = assignSupervisor($student);
    $proposal = ThesisProposal::factory()->for($student)->for($student->department)->create();

    $this->actingAs($lecturer->user)
        ->get(route('proposals.show', $proposal))
        ->assertOk();
});

it('does not let an unrelated lecturer open the proposal', function (): void {
    $student = makeStudent();
    $stranger = makeLecturer();
    $proposal = ThesisProposal::factory()->for($student)->for($student->department)->create();

    $this->actingAs($stranger->user)
        ->get(route('proposals.show', $proposal))
        ->assertForbidden();
});

it('scopes the proposal list for a program admin to their own department', function (): void {
    $department = makeDepartment();
    $admin = makeAdmin($department);

    $mine = makeStudent($department);
    $theirs = makeStudent();

    ThesisProposal::factory()->for($mine)->for($department)->create(['title' => 'Milik prodi ini']);
    ThesisProposal::factory()->for($theirs)->for($theirs->department)->create(['title' => 'Prodi lain']);

    $this->actingAs($admin)
        ->get(route('proposals.index'))
        ->assertInertia(fn ($page) => $page
            ->has('proposals', 1)
            ->where('proposals.0.title', 'Milik prodi ini'));
});
