<?php

use App\Actions\Thesis\BuildVerificationQr;
use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\SignatureType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ExamParticipant;
use App\Models\Signature;
use App\Support\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedRoles();
    Storage::fake('documents');
});

/** A 1x1 transparent PNG, as a canvas would hand it over. */
function signatureDataUri(): string
{
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
}

function releaseSitting(array $sitting): void
{
    $exam = $sitting['exam'];
    $department = $exam->student->department;
    $admin = makeAdmin($department);

    markExam($exam, $sitting['rubric'], $sitting['examiners'][0], 0.8);
    markExam($exam, $sitting['rubric'], $sitting['examiners'][1], 0.9);
    markSupervision($exam, $sitting['supervisor'], 16);

    test()->actingAs($sitting['examiners'][0]->user)->post(
        route('exams.minutes.store', ['stage' => ExamStage::Proposal, 'exam' => $exam]),
        ['decision' => ExamResult::Passed->value, 'revision_days' => 0],
    );

    test()->actingAs($admin)
        ->withSession(['workspace' => (new Workspace(UserRole::Admin, $department))->key()])
        ->post(route('admin.exams.release', ['stage' => ExamStage::Proposal, 'exam' => $exam]));
}

it('stores a drawn signature and marks the seat as signed', function (): void {
    $sitting = sittingWithForms();
    $chair = $sitting['examiners'][0];

    $this->actingAs($chair->user)
        ->post(route('exams.sign', ['stage' => ExamStage::Proposal, 'exam' => $sitting['exam']]), [
            'signature' => signatureDataUri(),
        ])
        ->assertRedirect();

    $signature = Signature::sole();
    $seat = ExamParticipant::where('lecturer_id', $chair->id)->sole();

    expect($signature->type)->toBe(SignatureType::Drawn)
        ->and($signature->user_id)->toBe($chair->user_id)
        ->and($signature->ip_address)->not->toBeNull()
        ->and(Storage::disk('documents')->exists($signature->path))->toBeTrue()
        ->and($seat->signed_at)->not->toBeNull()
        ->and($seat->signature_id)->toBe($signature->id)
        // Signing is itself a statement of having been there.
        ->and($seat->attended)->toBeTrue()
        ->and(AuditLog::where('action', 'participant.signed')->count())->toBe(1);
});

it('accepts an uploaded signature image', function (): void {
    $sitting = sittingWithForms();

    $this->actingAs($sitting['supervisor']->user)
        ->post(route('exams.sign', ['stage' => ExamStage::Proposal, 'exam' => $sitting['exam']]), [
            'file' => UploadedFile::fake()->image('ttd.png', 200, 80),
        ])
        ->assertRedirect();

    expect(Signature::sole()->type)->toBe(SignatureType::Uploaded);
});

it('refuses to sign for someone who is not seated', function (): void {
    $sitting = sittingWithForms();
    $outsider = makeLecturer();

    $this->actingAs($outsider->user)
        ->post(route('exams.sign', ['stage' => ExamStage::Proposal, 'exam' => $sitting['exam']]), [
            'signature' => signatureDataUri(),
        ])
        ->assertForbidden();

    expect(Signature::count())->toBe(0);
});

it('refuses anything that is not an image', function (): void {
    $sitting = sittingWithForms();

    $this->actingAs($sitting['examiners'][0]->user)
        ->post(route('exams.sign', ['stage' => ExamStage::Proposal, 'exam' => $sitting['exam']]), [
            'signature' => 'data:text/html;base64,PHNjcmlwdD4=',
        ])
        ->assertSessionHasErrors('signature');

    expect(Signature::count())->toBe(0);
});

it('mints a verification token only when a result is released', function (): void {
    $sitting = sittingWithForms();
    $qr = app(BuildVerificationQr::class);

    expect($qr->tokenFor($sitting['exam']))->toBeNull()
        ->and($qr->dataUriFor($sitting['exam']))->toBeNull();

    releaseSitting($sitting);
    $exam = $sitting['exam']->refresh();

    expect($qr->tokenFor($exam))->not->toBeNull()
        ->and($qr->urlFor($exam))->toContain('/verifikasi/')
        ->and($qr->dataUriFor($exam))->toStartWith('data:image/png;base64,');
});

it('keeps the same token across repeated reads', function (): void {
    $sitting = sittingWithForms();
    releaseSitting($sitting);

    $qr = app(BuildVerificationQr::class);
    $exam = $sitting['exam']->refresh();

    expect($qr->tokenFor($exam))->toBe($qr->tokenFor($exam->refresh()));
});

it('verifies a released document without an account', function (): void {
    $sitting = sittingWithForms();
    releaseSitting($sitting);

    $exam = $sitting['exam']->refresh();
    $token = app(BuildVerificationQr::class)->tokenFor($exam);

    $this->get(route('documents.verify', ['token' => $token]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('verify')
            ->where('document.valid', true)
            ->where('document.student', $exam->student->user->name)
            ->where('document.decision', 'Lulus')
            // The page says what the document says, never the marks behind it.
            ->missing('document.exam_scores'));
});

it('refuses an unknown or unreleased token', function (): void {
    $this->get(route('documents.verify', ['token' => 'tidak-ada']))->assertNotFound();

    $sitting = sittingWithForms();
    $sitting['exam']->forceFill(['verification_token' => 'belum-dirilis'])->save();

    $this->get(route('documents.verify', ['token' => 'belum-dirilis']))->assertNotFound();
});
