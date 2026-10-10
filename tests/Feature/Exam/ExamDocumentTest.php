<?php

use App\Actions\Thesis\BuildExamDocument;
use App\Enums\ExamDocument;
use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Http\Controllers\ExamDocumentController;
use App\Models\ThesisProposal;
use App\Support\Terbilang;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    seedRoles();
    Storage::fake('documents');
});

/** A sitting carried all the way through to a released result. */
function completedSitting(): array
{
    $sitting = sittingWithForms();
    $exam = $sitting['exam'];

    markExam($exam, $sitting['rubric'], $sitting['examiners'][0], 0.8);
    markExam($exam, $sitting['rubric'], $sitting['examiners'][1], 0.9);
    markSupervision($exam, $sitting['supervisor'], 16);

    test()->actingAs($sitting['examiners'][0]->user)->post(
        route('exams.revisions.store', ['stage' => ExamStage::Proposal, 'exam' => $exam]),
        ['notes' => [['body' => 'Perbaiki rumusan masalah.'], ['body' => 'Tambah referensi.']]],
    );

    test()->actingAs($sitting['examiners'][0]->user)->post(
        route('exams.minutes.store', ['stage' => ExamStage::Proposal, 'exam' => $exam]),
        ['decision' => ExamResult::ConditionalPass->value, 'revision_days' => 7],
    );

    return $sitting;
}

it('spells numbers the way an official document does', function (): void {
    expect(Terbilang::integer(2026))->toBe('dua ribu dua puluh enam')
        ->and(Terbilang::integer(15))->toBe('lima belas')
        ->and(Terbilang::integer(11))->toBe('sebelas')
        ->and(Terbilang::integer(1000))->toBe('seribu')
        ->and(Terbilang::integer(0))->toBe('nol')
        ->and(Terbilang::decimal(84.5))->toBe('delapan puluh empat koma lima puluh')
        ->and(Terbilang::decimal(84.05))->toBe('delapan puluh empat koma nol lima')
        ->and(Terbilang::decimal(100.0))->toBe('seratus');
});

it('renders every document as a PDF', function (ExamDocument $document): void {
    $sitting = completedSitting();
    $exam = $sitting['exam']->refresh();

    $response = $this->actingAs($sitting['examiners'][0]->user)
        ->get(route('exams.documents.show', [
            'stage' => ExamStage::Proposal,
            'exam' => $exam,
            'document' => $document->value,
        ]));

    $response->assertOk();

    // dompdf hands back a plain response, not a streamed one.
    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF-')
        ->and(strlen((string) $response->getContent()))->toBeGreaterThan(1000);
})->with([
    ExamDocument::Attendance,
    ExamDocument::ExamScore,
    ExamDocument::Revision,
    ExamDocument::Supervision,
    ExamDocument::ScoreList,
    ExamDocument::Minutes,
]);

it('refuses a document whose contents do not exist yet, naming what is missing', function (): void {
    $sitting = sittingWithForms();

    $response = $this->actingAs($sitting['examiners'][0]->user)
        ->get(route('exams.documents.show', [
            'stage' => ExamStage::Proposal,
            'exam' => $sitting['exam'],
            'document' => ExamDocument::Minutes->value,
        ]));

    $response->assertStatus(422);

    expect($response->baseResponse->exception->getMessage())
        ->toContain('Penilaian ujian belum lengkap')
        ->toContain('Berita acara belum ditetapkan');
});

it('bundles every ready document into one archive', function (): void {
    $sitting = completedSitting();

    $response = $this->actingAs($sitting['examiners'][0]->user)
        ->get(route('exams.documents.all', [
            'stage' => ExamStage::Proposal,
            'exam' => $sitting['exam'],
        ]));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/zip');

    $path = tempnam(sys_get_temp_dir(), 'zip-test');
    file_put_contents($path, $response->streamedContent());

    $zip = new ZipArchive;
    $zip->open($path);
    $names = collect(range(0, $zip->numFiles - 1))->map(fn (int $i): string => $zip->getNameIndex($i));
    $zip->close();
    @unlink($path);

    expect($names)->toHaveCount(6)
        ->and($names->first())->toContain('daftar-hadir');
});

it('lists what each document still needs', function (): void {
    $sitting = sittingWithForms();
    $catalogue = app(ExamDocumentController::class)
        ->catalogue($sitting['exam'], ExamStage::Proposal);

    $byValue = collect($catalogue)->keyBy('value');

    // The attendance sheet only needs a panel, which this sitting has.
    expect($byValue['daftar-hadir']['ready'])->toBeTrue()
        ->and($byValue['berita-acara']['ready'])->toBeFalse()
        ->and($byValue['berita-acara']['missing'])->toContain('Berita acara belum ditetapkan.');
});

it('hides documents from the student until the result is released', function (): void {
    $sitting = completedSitting();
    $student = $sitting['exam']->student;

    $this->actingAs($student->user)
        ->get(route('exams.documents.show', [
            'stage' => ExamStage::Proposal,
            'exam' => $sitting['exam'],
            'document' => ExamDocument::Minutes->value,
        ]))
        ->assertForbidden();
});

it('prints the block form for a block rubric and the weighted form otherwise', function (): void {
    $sitting = completedSitting();
    $build = app(BuildExamDocument::class);

    // sittingWithForms seeds the faculty's block rubric.
    $data = $build->handle($sitting['exam']->refresh());
    $form = $data['examiners'][0];

    expect($form['block_additive'])->toBeTrue()
        ->and(collect($form['blocks'])->pluck('max', 'code')->all())
        ->toBe(['A' => 15, 'B' => 40, 'C' => 15, 'D' => 30])
        // Each block's earned subtotal is its own, not the whole score.
        ->and(collect($form['blocks'])->sum('earned'))->toBe((float) $form['total']);
});

it('names the tugas akhir rather than the sitting', function (): void {
    $sitting = completedSitting();
    $student = $sitting['exam']->student;
    $proposal = ThesisProposal::factory()
        ->for($student)->for($student->department)->approved()
        ->create(['title' => 'Judul Tugas Akhir Sebenarnya']);

    $data = app(BuildExamDocument::class)->handle($sitting['exam']->refresh());

    expect($data['exam']['title'])->toBe('Judul Tugas Akhir Sebenarnya')
        ->and($data['exam']['session_title'])->not->toBe($data['exam']['title']);
});

it('strikes through the verdicts the panel did not choose', function (): void {
    $sitting = completedSitting();
    $data = app(BuildExamDocument::class)->handle($sitting['exam']->refresh());

    $options = collect($data['minutes']['options']);

    expect($options->where('chosen', true)->pluck('label')->all())->toBe(['LULUS BERSYARAT'])
        ->and($options->where('chosen', false)->pluck('label')->all())
        ->toBe(['LULUS', 'TIDAK LULUS']);
});

it('spells the exam date out for the minutes', function (): void {
    $sitting = completedSitting();
    $exam = $sitting['exam'];
    $exam->update(['scheduled_at' => now()->parse('2026-08-04 09:00')]);

    $words = app(BuildExamDocument::class)
        ->handle($exam->refresh())['exam']['date_in_words'];

    expect($words['day'])->toBe('Selasa')
        ->and($words['date'])->toBe('Empat')
        ->and($words['month'])->toBe('Agustus')
        ->and($words['year'])->toBe('Dua Ribu Dua Puluh Enam');
});
