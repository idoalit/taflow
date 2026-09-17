<?php

use App\Models\Student;
use App\Models\ThesisProposal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedRoles();
    Storage::fake('local');
});

/**
 * Documents live on the private disk, so the download endpoint is the only way
 * to reach them and has to enforce the same visibility rule as the screens.
 */
function uploadedProposalPath(Student $student): string
{
    return UploadedFile::fake()
        ->create('proposal.pdf', 50, 'application/pdf')
        ->store("thesis/{$student->id}/proposals", 'local');
}

it('serves a document to the student who owns it', function (): void {
    $student = makeStudent();
    $path = uploadedProposalPath($student);

    $this->actingAs($student->user)
        ->get(route('documents.show', ['path' => $path, 'name' => 'proposal.pdf']))
        ->assertOk()
        ->assertDownload('proposal.pdf');
});

it('serves a document to the student\'s supervisor', function (): void {
    $student = makeStudent();
    $lecturer = assignSupervisor($student);
    $path = uploadedProposalPath($student);

    $this->actingAs($lecturer->user)
        ->get(route('documents.show', ['path' => $path]))
        ->assertOk();
});

it('refuses a document to an unrelated user', function (): void {
    $student = makeStudent();
    $stranger = makeStudent();
    $path = uploadedProposalPath($student);

    $this->actingAs($stranger->user)
        ->get(route('documents.show', ['path' => $path]))
        ->assertForbidden();
});

it('ignores a download name that tries to smuggle a path', function (): void {
    $student = makeStudent();
    $path = uploadedProposalPath($student);

    $this->actingAs($student->user)
        ->get(route('documents.show', ['path' => $path, 'name' => '../../evil.sh']))
        ->assertOk()
        ->assertDownload('evil.sh');
});

it('refuses a path that tries to escape the thesis directory', function (): void {
    $student = makeStudent();

    $this->actingAs($student->user)
        ->get(route('documents.show', ['path' => '../../.env']))
        ->assertNotFound();
});

it('refuses a path outside the thesis namespace', function (): void {
    $student = makeStudent();
    Storage::disk('local')->put('secrets/keys.txt', 'nope');

    $this->actingAs($student->user)
        ->get(route('documents.show', ['path' => 'secrets/keys.txt']))
        ->assertNotFound();
});

it('requires authentication to download a document', function (): void {
    $student = makeStudent();
    ThesisProposal::factory()->for($student)->for($student->department)->create();

    $this->get(route('documents.show', ['path' => "thesis/{$student->id}/proposals/x.pdf"]))
        ->assertRedirect(route('login'));
});

it('renders a whitelisted type inline when previewing', function (): void {
    $student = makeStudent();
    $path = uploadedProposalPath($student);

    $response = $this->actingAs($student->user)
        ->get(route('documents.show', ['path' => $path, 'name' => 'proposal.pdf', 'preview' => 1]));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('x-content-type-options', 'nosniff');

    expect($response->headers->get('content-disposition'))->toStartWith('inline');
});

it('downloads a type it cannot render even when previewing', function (): void {
    $student = makeStudent();
    $path = UploadedFile::fake()
        ->create('draft.docx', 20)
        ->store("thesis/{$student->id}/proposals", 'local');

    $response = $this->actingAs($student->user)
        ->get(route('documents.show', ['path' => $path, 'name' => 'draft.docx', 'preview' => 1]));

    $response->assertOk();

    expect($response->headers->get('content-disposition'))->toStartWith('attachment');
});

it('downloads by default even for a previewable type', function (): void {
    $student = makeStudent();
    $path = uploadedProposalPath($student);

    $this->actingAs($student->user)
        ->get(route('documents.show', ['path' => $path, 'name' => 'proposal.pdf']))
        ->assertDownload('proposal.pdf');
});

it('still enforces visibility on a preview', function (): void {
    $student = makeStudent();
    $stranger = makeStudent();
    $path = uploadedProposalPath($student);

    $this->actingAs($stranger->user)
        ->get(route('documents.show', ['path' => $path, 'preview' => 1]))
        ->assertForbidden();
});

it('tells the frontend which documents can be previewed', function (): void {
    $student = makeStudent();
    $proposal = ThesisProposal::factory()->for($student)->for($student->department)->create([
        'document_path' => uploadedProposalPath($student),
        'document_name' => 'proposal.pdf',
    ]);

    $this->actingAs($student->user)
        ->get(route('proposals.show', $proposal))
        ->assertInertia(fn ($page) => $page
            ->where('proposal.document.previewable', true)
            ->where('proposal.document.kind', 'PDF')
            ->whereNot('proposal.document.preview_url', null));
});
