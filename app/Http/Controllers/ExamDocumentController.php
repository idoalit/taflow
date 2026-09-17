<?php

namespace App\Http\Controllers;

use App\Actions\Thesis\RenderExamDocument;
use App\Enums\ExamDocument;
use App\Enums\ExamParticipantRole;
use App\Enums\ExamStage;
use App\Models\SupervisionScore;
use App\Models\ThesisExam;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * The official documents, as PDFs.
 *
 * A document is refused rather than printed empty when what it states does not
 * exist yet; the caller is told which parts are missing.
 */
class ExamDocumentController extends Controller
{
    public function __construct(private readonly RenderExamDocument $render) {}

    public function show(Request $request, ExamStage $stage, int $exam, ExamDocument $document): Response
    {
        $thesisExam = $this->find($stage, $exam);

        Gate::authorize('viewResult', $thesisExam);

        $this->guardComplete($thesisExam, $document);

        return $this->render->pdf($thesisExam, $document)
            ->download($this->render->filename($thesisExam, $document));
    }

    /**
     * Every document that can be produced, as one ZIP.
     */
    public function all(Request $request, ExamStage $stage, int $exam): StreamedResponse
    {
        $thesisExam = $this->find($stage, $exam);

        Gate::authorize('viewResult', $thesisExam);

        $ready = array_filter(
            ExamDocument::cases(),
            fn (ExamDocument $document): bool => $this->missingFor($thesisExam, $document) === [],
        );

        abort_if(
            $ready === [],
            422,
            'Belum ada dokumen yang dapat diunduh: '
            .implode(' ', $this->missingFor($thesisExam, ExamDocument::Minutes)),
        );

        $archive = tempnam(sys_get_temp_dir(), 'fipro-docs');
        $zip = new ZipArchive;
        $zip->open($archive, ZipArchive::OVERWRITE | ZipArchive::CREATE);

        foreach ($ready as $document) {
            $zip->addFromString(
                $this->render->filename($thesisExam, $document),
                $this->render->pdf($thesisExam, $document)->output(),
            );
        }

        $zip->close();

        $name = 'dokumen-ujian-'.Str::slug($thesisExam->student->user->name).'.zip';

        return response()->streamDownload(function () use ($archive): void {
            readfile($archive);
            @unlink($archive);
        }, $name, ['Content-Type' => 'application/zip']);
    }

    /**
     * What each document still needs, so a screen can list it.
     *
     * @return array<int, string>
     */
    private function missingFor(ThesisExam $exam, ExamDocument $document): array
    {
        $missing = [];
        $requires = $document->requires();

        if (in_array('panel', $requires, true) && ! $exam->hasOwnPanel()) {
            $missing[] = 'Panel sidang belum ditetapkan.';
        }

        if (in_array('exam_scores', $requires, true)) {
            $scored = $exam->evaluations()->count();
            $panel = $exam->panel()->count();

            if ($scored === 0 || $scored < $panel) {
                $missing[] = "Penilaian ujian belum lengkap ({$scored} dari {$panel} penguji).";
            }
        }

        if (in_array('supervision_scores', $requires, true)) {
            $marked = SupervisionScore::query()
                ->where('exam_type', $exam->getMorphClass())
                ->where('exam_id', $exam->id)
                ->exists();

            if (! $marked) {
                $missing[] = 'Penilaian proses bimbingan belum diisi.';
            }
        }

        if (in_array('minutes', $requires, true) && $exam->minutes === null) {
            $missing[] = 'Berita acara belum ditetapkan.';
        }

        if ($document === ExamDocument::Revision && $exam->revisionNotes()->count() === 0) {
            $missing[] = 'Belum ada lembar revisi dari penguji.';
        }

        if ($document === ExamDocument::Supervision && $exam->participants()
            ->where('role', ExamParticipantRole::Supervisor)->doesntExist()) {
            $missing[] = 'Belum ada pembimbing pada panel sidang ini.';
        }

        return $missing;
    }

    private function guardComplete(ThesisExam $exam, ExamDocument $document): void
    {
        $missing = $this->missingFor($exam, $document);

        abort_if(
            $missing !== [],
            422,
            "Dokumen \"{$document->label()}\" belum dapat dibuat. ".implode(' ', $missing),
        );
    }

    private function find(ExamStage $stage, int $id): ThesisExam
    {
        return $stage->modelClass()::query()->where('id', $id)->firstOrFail();
    }

    /**
     * Every document with its readiness, for the screen that lists them.
     *
     * @return array<int, array{value: string, label: string, ready: bool, missing: array<int, string>, url: string}>
     */
    public function catalogue(ThesisExam $exam, ExamStage $stage): array
    {
        return array_map(function (ExamDocument $document) use ($exam, $stage): array {
            $missing = $this->missingFor($exam, $document);

            return [
                'value' => $document->value,
                'label' => $document->label(),
                'ready' => $missing === [],
                'missing' => $missing,
                'url' => route('exams.documents.show', [
                    'stage' => $stage,
                    'exam' => $exam->id,
                    'document' => $document->value,
                ]),
            ];
        }, ExamDocument::cases());
    }
}
