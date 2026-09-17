<?php

use App\Enums\ExamResult;
use App\Models\ResearchLogbook;
use App\Models\Student;
use App\Models\ThesisProposal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedRoles();
    Storage::fake('local');
});

/**
 * The logbook only opens once the ujian proposal has been passed, so every test
 * that writes to it starts from a student who has reached that point.
 */
function studentInResearchPhase(): Student
{
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->approved()->create();
    passProposalExam($student);

    return $student;
}

it('stores a logbook entry with an attachment', function (): void {
    $student = studentInResearchPhase();

    $this->actingAs($student->user)
        ->post(route('research-logbooks.store'), [
            'log_date' => now()->toDateString(),
            'progress' => 65,
            'description' => 'Melakukan preprocessing dataset ulasan dan membersihkan data duplikat.',
            'attachment' => UploadedFile::fake()->create('training_log.xlsx', 80),
        ])
        ->assertRedirect(route('research-logbooks.index'));

    $entry = ResearchLogbook::sole();

    expect($entry->student_id)->toBe($student->id)
        ->and($entry->progress)->toBe(65)
        ->and($entry->attachment_name)->toBe('training_log.xlsx');

    Storage::disk('local')->assertExists($entry->attachment_path);
});

it('rejects a logbook entry dated in the future', function (): void {
    $student = studentInResearchPhase();

    $this->actingAs($student->user)
        ->post(route('research-logbooks.store'), [
            'log_date' => now()->addWeek()->toDateString(),
            'progress' => 20,
            'description' => 'Rencana kegiatan minggu depan.',
        ])
        ->assertSessionHasErrors('log_date');
});

it('summarises progress from the most recent entry', function (): void {
    $student = makeStudent();

    ResearchLogbook::factory()->for($student)->create(['log_date' => now()->subMonth(), 'progress' => 30]);
    ResearchLogbook::factory()->for($student)->create(['log_date' => now()->subDay(), 'progress' => 62]);

    $this->actingAs($student->user)
        ->get(route('research-logbooks.index'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.latest_progress', 62)
            ->where('stats.total', 2)
            ->has('entries', 2));
});

it('lets a supervisor read their student\'s logbook but not a stranger\'s', function (): void {
    $student = makeStudent();
    $lecturer = assignSupervisor($student);
    $stranger = makeLecturer();

    ResearchLogbook::factory()->for($student)->create();

    $this->actingAs($lecturer->user)
        ->get(route('research-logbooks.index'))
        ->assertInertia(fn ($page) => $page->has('entries', 1));

    $this->actingAs($stranger->user)
        ->get(route('research-logbooks.index'))
        ->assertInertia(fn ($page) => $page->has('entries', 0));
});

it('does not let a lecturer add a logbook entry', function (): void {
    $lecturer = makeLecturer();

    $this->actingAs($lecturer->user)
        ->get(route('research-logbooks.create'))
        ->assertForbidden();
});

it('refuses logbook entries while the student is still on their proposal', function (): void {
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->approved()->create();

    $this->actingAs($student->user)
        ->get(route('research-logbooks.create'))
        ->assertForbidden();

    $this->actingAs($student->user)
        ->post(route('research-logbooks.store'), [
            'log_date' => now()->toDateString(),
            'progress' => 10,
            'description' => 'Mencoba mencatat sebelum masuk tahap penelitian.',
        ])
        ->assertForbidden();

    expect(ResearchLogbook::count())->toBe(0);
});

it('explains on the index why the logbook cannot be filled yet', function (): void {
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->approved()->create();

    $this->actingAs($student->user)
        ->get(route('research-logbooks.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canCreate', false)
            ->where('lockReason', fn (string $reason) => str_contains($reason, 'ujian proposal')));
});

it('opens the logbook once the proposal exam is passed', function (): void {
    $student = studentInResearchPhase();

    $this->actingAs($student->user)
        ->get(route('research-logbooks.index'))
        ->assertInertia(fn ($page) => $page
            ->where('canCreate', true)
            ->where('lockReason', null));
});

it('keeps the logbook open after a conditional pass', function (): void {
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->approved()->create();
    passProposalExam($student, ExamResult::ConditionalPass);

    $this->actingAs($student->user)
        ->get(route('research-logbooks.create'))
        ->assertOk();
});

it('lets a student still correct an entry they already wrote', function (): void {
    $student = studentInResearchPhase();
    $entry = ResearchLogbook::factory()->for($student)->create();

    expect($student->user->can('update', $entry))->toBeTrue();
});
