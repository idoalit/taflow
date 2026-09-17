<?php

namespace App\Actions\Thesis;

use App\Enums\ExamDocument;
use App\Models\ThesisExam;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Renders one official document to PDF.
 *
 * A document printed once per examiner becomes one PDF with a page each, which
 * is how the paper forms are handed out.
 */
final readonly class RenderExamDocument
{
    public function __construct(private BuildExamDocument $build) {}

    public function pdf(ThesisExam $exam, ExamDocument $document): \Barryvdh\DomPDF\PDF
    {
        $data = $this->build->handle($exam);

        return Pdf::loadView('documents.layout', [
            'title' => $document->label(),
            'institution' => $data['institution'],
            'pages' => $this->pages($data, $document),
        ])->setPaper('a4');
    }

    public function filename(ThesisExam $exam, ExamDocument $document): string
    {
        $student = Str::slug($exam->student->user->name);

        return "{$document->value}-{$student}-{$exam->student->student_number}.pdf";
    }

    /**
     * Each page rendered to HTML, so the layout can stitch them with a break
     * and a letterhead between.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    private function pages(array $data, ExamDocument $document): array
    {
        $signatures = $this->signatureByName($data);

        return match ($document) {
            ExamDocument::Attendance, ExamDocument::ScoreList, ExamDocument::Minutes => [
                view($document->view(), [...$data, 'signatures' => $signatures])->render(),
            ],
            ExamDocument::ExamScore => array_map(
                fn (array $form): string => view($document->view(), [
                    ...$data,
                    'form' => $form,
                    'signature' => $signatures[$form['lecturer']] ?? null,
                ])->render(),
                $data['examiners'],
            ),
            ExamDocument::Revision => array_map(
                fn (array $sheet): string => view($document->view(), [
                    ...$data,
                    'sheet' => $sheet,
                    'signature' => $signatures[$sheet['lecturer']] ?? null,
                ])->render(),
                $data['revisions'],
            ),
            ExamDocument::Supervision => array_map(
                fn (array $sheet): string => view($document->view(), [
                    ...$data,
                    'sheet' => $sheet,
                    'signature' => $signatures[$sheet['lecturer']] ?? null,
                ])->render(),
                $data['supervision'],
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    private function signatureByName(array $data): array
    {
        $map = [];

        foreach ($data['participants'] as $person) {
            $map[$person['name']] = $person['signature'];
        }

        return $map;
    }
}
