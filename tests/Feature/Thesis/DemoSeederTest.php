<?php

use App\Actions\Thesis\ComposeFinalGrade;
use App\Actions\Thesis\ThesisPhaseAccess;
use App\Enums\ApprovalStatus;
use App\Enums\ExamResult;
use App\Enums\ExamStatus;
use App\Enums\GuidanceStage;
use App\Enums\GuidanceStatus;
use App\Enums\RevisionStatus;
use App\Enums\ScoringScheme;
use App\Models\GuidanceSession;
use App\Models\ResearchExam;
use App\Models\ResearchExamRequest;
use App\Models\ResearchLogbook;
use App\Models\Student;
use App\Models\ThesisProposal;
use Database\Seeders\DemoThesisSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * The demo account is what anyone trying the app sees first, so it has to carry
 * a complete worked example — including the supervisor's closing notes and real
 * files behind every attachment.
 */
beforeEach(function (): void {
    Storage::fake('local');
    app(RolePermissionSeeder::class)->run();
    app(DemoThesisSeeder::class)->run();
});

it('gives every completed session the supervisor\'s completion notes', function (): void {
    $completed = GuidanceSession::query()->where('status', GuidanceStatus::Completed)->get();

    expect($completed)->not->toBeEmpty();

    foreach ($completed as $session) {
        expect($session->completion_notes)->not->toBeEmpty()
            ->and($session->completed_at)->not->toBeNull();
    }
});

it('backs every seeded attachment with a file that really exists', function (): void {
    $paths = collect([
        ThesisProposal::query()->whereNotNull('document_path')->pluck('document_path'),
        GuidanceSession::query()->whereNotNull('document_path')->pluck('document_path'),
        ResearchLogbook::query()->whereNotNull('attachment_path')->pluck('attachment_path'),
    ])->flatten();

    expect($paths)->not->toBeEmpty();

    foreach ($paths as $path) {
        Storage::disk('local')->assertExists($path);
        expect(Storage::disk('local')->get($path))->not->toBeEmpty();
    }
});

it('writes sample documents as valid PDFs', function (): void {
    $path = ThesisProposal::query()
        ->where('title', 'like', 'Analisis Sentimen%')
        ->value('document_path');
    $contents = Storage::disk('local')->get($path);

    expect($contents)->toStartWith('%PDF-1.4')
        ->and(rtrim($contents))->toEndWith('%%EOF')
        ->and($contents)->toContain('Analisis Sentimen Ulasan E-Commerce dengan BERT');
});

it('seeds the async session with a readable chat thread', function (): void {
    $session = GuidanceSession::query()->where('guidance_type', 'online_async')->sole();
    $messages = $session->messages;

    expect($messages)->toHaveCount(3)
        ->and($messages->pluck('user_id')->unique())->toHaveCount(2)
        ->and($messages->whereNotNull('attachment_path'))->toHaveCount(1);

    // The thread has to read in order for the transcript to make sense.
    $timestamps = $messages->map(fn ($message): int => $message->created_at->getTimestamp())->all();

    expect($timestamps)->toBe(collect($timestamps)->sort()->values()->all());
});

it('keeps every seeded document reachable through the download endpoint', function (): void {
    $student = Student::query()->where('student_number', '21051234')->sole();
    $path = ThesisProposal::query()->where('student_id', $student->id)->value('document_path');

    $this->actingAs($student->user)
        ->get(route('documents.show', ['path' => $path, 'name' => 'Proposal_Rani_Amelia_v3.pdf']))
        ->assertOk()
        ->assertDownload('Proposal_Rani_Amelia_v3.pdf');
});

it('keeps the first demo student in the proposal stage', function (): void {
    $rani = Student::query()->where('student_number', '21051234')->sole();

    expect($rani->guidanceSessions()->where('stage', GuidanceStage::Research)->count())->toBe(0)
        ->and($rani->hasPassedProposalExam())->toBeFalse()
        ->and(ThesisPhaseAccess::forStudent($rani)->isOpen(GuidanceStage::Research))->toBeFalse();
});

it('seeds a second student who has already passed ujian proposal', function (): void {
    $dimas = Student::query()->where('student_number', '21051235')->sole();
    $exam = $dimas->latestProposalExam();

    expect($exam)->not->toBeNull()
        ->and($exam->status)->toBe(ExamStatus::Scored)
        ->and($exam->result)->toBe(ExamResult::Passed)
        ->and($dimas->hasPassedProposalExam())->toBeTrue()
        ->and(ThesisPhaseAccess::forStudent($dimas)->isOpen(GuidanceStage::Research))->toBeTrue()
        ->and($dimas->guidanceSessions()->where('stage', GuidanceStage::Research)->count())->toBe(2);
});

it('links the seeded exam request to its scheduled exam', function (): void {
    $dimas = Student::query()->where('student_number', '21051235')->sole();
    $request = $dimas->proposalExamRequests()->sole();

    expect($request->status)->toBe(ApprovalStatus::Approved)
        ->and($request->proposal_exam_id)->toBe($dimas->latestProposalExam()->id)
        ->and($request->documents)->toHaveCount(1);
});

it('leaves the proposal-stage student without a research logbook', function (): void {
    $rani = Student::query()->where('student_number', '21051234')->sole();
    $dimas = Student::query()->where('student_number', '21051235')->sole();

    expect($rani->researchLogbooks()->count())->toBe(0)
        ->and($rani->user->can('create', ResearchLogbook::class))->toBeFalse()
        ->and($dimas->researchLogbooks()->count())->toBe(3)
        ->and($dimas->user->can('create', ResearchLogbook::class))->toBeTrue();
});

it('books the far-along demo student for an ujian hasil', function (): void {
    $student = Student::whereRelation('user', 'email', 'dimas@fipro.test')->sole();

    $exam = ResearchExam::where('student_id', $student->id)->sole();
    $request = ResearchExamRequest::where('student_id', $student->id)->sole();

    expect($exam->status)->toBe(ExamStatus::Scheduled)
        ->and($exam->scheduled_at->isFuture())->toBeTrue()
        ->and($exam->evaluations()->count())->toBe(0)
        ->and($request->research_exam_id)->toBe($exam->id)
        ->and($request->status)->toBe(ApprovalStatus::Approved);
});

it('seeds one ujian hasil that is entirely finished and released', function (): void {
    $student = Student::whereRelation('user', 'email', 'bagas@fipro.test')->sole();
    $exam = ResearchExam::where('student_id', $student->id)->sole();

    expect($exam->status)->toBe(ExamStatus::Released)
        ->and($exam->result)->toBe(ExamResult::ConditionalPass)
        ->and($exam->isReleased())->toBeTrue()
        ->and($exam->verification_token)->not->toBeNull()
        ->and($exam->minutes)->not->toBeNull()
        ->and($exam->revisionNotes()->count())->toBe(4)
        // One point already ticked off, so the checklist shows both states.
        ->and($exam->revisionNotes()->where('status', RevisionStatus::Done)->count())->toBe(2);

    // The panel is seated and every seat signed, which is what the Daftar Hadir
    // and every signature block in the documents are drawn from.
    $participants = $exam->participants;

    expect($participants)->toHaveCount(5)
        ->and($participants->where('attended', true))->toHaveCount(5)
        ->and($participants->whereNotNull('signature_id'))->toHaveCount(5);

    foreach ($participants as $participant) {
        Storage::disk('local')->assertExists($participant->signature->path);
    }
});

it('leaves nothing missing behind the released result', function (): void {
    $student = Student::whereRelation('user', 'email', 'bagas@fipro.test')->sole();
    $exam = ResearchExam::where('student_id', $student->id)->sole();

    $compose = app(ComposeFinalGrade::class);
    $grade = $compose->handle($exam);

    expect($compose->missing($exam))->toBe([])
        ->and($grade['complete'])->toBeTrue()
        ->and($grade['examiner_count'])->toBe(2)
        // Two pembimbing marked, and the component still reads out of 100.
        ->and($grade['supervision_max'])->toBe(100)
        ->and($grade['letter'])->not->toBeNull()
        // The minutes were taken on this score, so the two cannot disagree.
        ->and((float) $exam->minutes->final_score)->toBe($grade['final_score']);
});

it('marks ujian hasil with the block-additive faculty rubric', function (): void {
    $student = Student::whereRelation('user', 'email', 'bagas@fipro.test')->sole();
    $exam = ResearchExam::where('student_id', $student->id)->sole();
    $rubric = $exam->effectiveRubric();

    expect($rubric->scoring_scheme)->toBe(ScoringScheme::BlockAdditive)
        ->and($rubric->criteria)->toHaveCount(28);

    // Every examiner filled in every line; a blank row is not a form the
    // faculty accepts.
    foreach ($exam->evaluations as $evaluation) {
        expect($evaluation->scores)->toHaveCount(28);
    }
});
