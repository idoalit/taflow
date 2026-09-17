<?php

namespace App\Actions\Thesis;

use App\Enums\ExamParticipantRole;
use App\Enums\ExamResult;
use App\Models\ExamEvaluation;
use App\Models\ExamEvaluationScore;
use App\Models\ExamParticipant;
use App\Models\RevisionNote;
use App\Models\SupervisionScore;
use App\Models\ThesisExam;
use App\Support\Terbilang;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Everything the official documents print, gathered once.
 *
 * Nothing here is hardcoded: the institution comes from config, the jurusan and
 * city from the program studi, and every name, number and date from the sitting
 * itself. Templates do no arithmetic — they lay out what this produces.
 */
final readonly class BuildExamDocument
{
    public function __construct(
        private ComposeFinalGrade $compose,
        private BuildVerificationQr $qr,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(ThesisExam $exam): array
    {
        $exam->loadMissing(['student.user', 'student.department', 'minutes']);
        $student = $exam->student;
        $department = $student->department;
        $grade = $this->compose->handle($exam);
        $minutes = $exam->minutes;

        $scheduled = $exam->scheduled_at;
        $city = $department->city ?: config('institution.city');

        return [
            'institution' => [
                'name' => config('institution.name'),
                'unit' => config('institution.unit'),
                'address' => config('institution.address'),
                'phone' => config('institution.phone'),
                'website' => config('institution.website'),
                'logo' => $this->logoDataUri(),
            ],
            'student' => [
                'name' => $student->user->name,
                'number' => $student->student_number,
                'department' => $department->name,
                'faculty' => $department->faculty,
            ],
            'exam' => [
                'stage' => $exam->stage()->title(),
                'session_title' => $exam->title,
                // The documents state the tugas akhir's title, not the name the
                // sitting was scheduled under.
                // ?? uses isset semantics, so a student with no proposal at all
                // falls back rather than fataling.
                'title' => $student->activeProposal()->title ?? $exam->title,
                'day' => $scheduled->translatedFormat('l'),
                'date' => $scheduled->translatedFormat('d F Y'),
                'date_in_words' => $this->dateInWords($scheduled),
                'time' => $scheduled->format('H:i').' – '
                    .$scheduled->copy()->addMinutes($exam->duration_minutes)->format('H:i').' WIB',
                'venue' => $exam->venue ?: '—',
                'city' => $city,
                'signed_on' => "{$city}, ".$scheduled->translatedFormat('d F Y'),
                'rubric' => $exam->effectiveRubric()?->name,
            ],
            'participants' => $this->participants($exam),
            'examiners' => $this->examinerForms($exam),
            'supervision' => $this->supervisionForms($exam),
            'revisions' => $this->revisionSheets($exam),
            'grade' => [
                ...$grade,
                'final_in_words' => $grade['final_score'] === null
                    ? null
                    : Terbilang::decimal($grade['final_score']),
                'exam_in_words' => $grade['exam_score'] === null
                    ? null
                    : Terbilang::decimal($grade['exam_score']),
            ],
            'minutes' => $minutes === null ? null : [
                'decision' => $minutes->decision,
                'decision_label' => $minutes->decision->label(),
                'options' => $this->decisionOptions($minutes->decision),
                'final_score' => $minutes->final_score,
                'final_in_words' => Terbilang::decimal((float) $minutes->final_score),
                'letter' => $minutes->letter,
                'revision_days' => $minutes->revision_days,
                'revision_days_in_words' => Terbilang::integer($minutes->revision_days),
                'requires_revision' => $minutes->requiresRevision(),
                'notes' => $minutes->notes,
            ],
            'verification' => [
                'url' => $this->qr->urlFor($exam),
                'qr' => $this->qr->dataUriFor($exam),
            ],
        ];
    }

    /**
     * "hari ini, Selasa Tanggal Lima Belas Bulan September Tahun Dua Ribu Dua
     * Puluh Enam" — the form the minutes open with.
     *
     * @return array{day: string, date: string, month: string, year: string}
     */
    private function dateInWords(CarbonInterface $moment): array
    {
        return [
            'day' => $moment->translatedFormat('l'),
            'date' => ucwords(Terbilang::integer((int) $moment->format('j'))),
            'month' => $moment->translatedFormat('F'),
            'year' => ucwords(Terbilang::integer((int) $moment->format('Y'))),
        ];
    }

    /**
     * Every seat, in the order the attendance sheet lists them.
     *
     * @return array<int, array<string, mixed>>
     */
    private function participants(ThesisExam $exam): array
    {
        $order = [
            ExamParticipantRole::Student->value => 0,
            ExamParticipantRole::ChairExaminer->value => 1,
            ExamParticipantRole::MemberExaminer->value => 2,
            ExamParticipantRole::Supervisor->value => 3,
        ];

        $members = 0;

        return $exam->participants()
            ->with(['user', 'lecturer', 'signature'])
            ->get()
            ->sortBy(fn (ExamParticipant $p): int => $order[$p->role->value])
            ->values()
            ->map(function (ExamParticipant $participant) use (&$members): array {
                $ordinal = $participant->role === ExamParticipantRole::MemberExaminer
                    ? ++$members
                    : 0;

                return [
                    'name' => $participant->user->name,
                    'identifier' => $participant->role === ExamParticipantRole::Student
                        ? $participant->user->student?->student_number
                        : $participant->lecturer?->lecturer_number,
                    'role' => $participant->role,
                    'role_label' => $participant->positionLabel($ordinal),
                    'attended' => $participant->attended,
                    'signature' => $participant->signature?->toDataUri(),
                ];
            })
            ->all();
    }

    /**
     * One marking form per examiner, with each block's subtotal.
     *
     * @return array<int, array<string, mixed>>
     */
    private function examinerForms(ThesisExam $exam): array
    {
        $rubric = $exam->effectiveRubric();

        return $exam->evaluations()
            ->with(['lecturer.user', 'scores'])
            ->get()
            ->map(function (ExamEvaluation $evaluation) use ($rubric): array {
                $marks = $evaluation->scores
                    ->mapWithKeys(fn (ExamEvaluationScore $s): array => [
                        $s->criterion_key => (float) $s->score,
                    ])
                    ->all();

                return [
                    'lecturer' => $evaluation->lecturer->user->name,
                    'lecturer_number' => $evaluation->lecturer->lecturer_number,
                    // A weighted rubric has no blocks to subtotal; its form
                    // prints weights instead.
                    'block_additive' => $rubric?->isBlockAdditive() ?? false,
                    'blocks' => $rubric === null || ! $rubric->isBlockAdditive()
                        ? []
                        : ExamEvaluation::blockSubtotals($rubric, $marks),
                    'rows' => $evaluation->scores->map(fn (ExamEvaluationScore $s): array => [
                        'label' => $s->criterion_label,
                        'max' => $s->max_score,
                        'score' => $s->score,
                    ])->values()->all(),
                    'criteria' => $rubric === null ? collect() : $rubric->criteria,
                    'marks' => $marks,
                    'total' => $evaluation->final_score,
                    'result_label' => $evaluation->result->label(),
                    'feedback' => $evaluation->feedback,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function supervisionForms(ThesisExam $exam): array
    {
        return SupervisionScore::query()
            ->where('exam_type', $exam->getMorphClass())
            ->where('exam_id', $exam->id)
            ->with('lecturer.user')
            ->get()
            ->groupBy('lecturer_id')
            ->map(fn (Collection $scores): array => [
                'lecturer' => $scores->first()->lecturer->user->name,
                'lecturer_number' => $scores->first()->lecturer->lecturer_number,
                'rows' => $scores->map(fn (SupervisionScore $s): array => [
                    'code' => $s->indicator_code,
                    'label' => $s->indicator_label,
                    'max' => $s->max_score,
                    'score' => $s->score,
                    'descriptor' => $s->descriptor(),
                ])->values()->all(),
                'total' => round($scores->sum(fn (SupervisionScore $s): float => (float) $s->score), 2),
                'notes' => $scores->first()->notes,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function revisionSheets(ThesisExam $exam): array
    {
        return $exam->revisionNotes()
            ->with('lecturer.user')
            ->get()
            ->groupBy('lecturer_id')
            ->map(fn (Collection $notes): array => [
                'lecturer' => $notes->first()->lecturer->user->name,
                'lecturer_number' => $notes->first()->lecturer->lecturer_number,
                'items' => $notes->map(fn (RevisionNote $note): array => [
                    'position' => $note->position,
                    'body' => $note->body,
                    'due_date' => $note->due_date?->translatedFormat('d F Y'),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * The three verdicts, with the two that were not chosen struck through —
     * the paper form does this with a pen, so the document does it itself.
     *
     * @return array<int, array{label: string, chosen: bool}>
     */
    private function decisionOptions(ExamResult $chosen): array
    {
        return array_map(fn (ExamResult $result): array => [
            'label' => mb_strtoupper($result->label()),
            'chosen' => $result === $chosen,
        ], ExamResult::cases());
    }

    private function logoDataUri(): ?string
    {
        $path = config('institution.logo_path');

        if (! is_string($path) || $path === '' || ! file_exists(public_path($path))) {
            return null;
        }

        $mime = str_ends_with(strtolower($path), '.svg') ? 'image/svg+xml' : 'image/png';

        return "data:{$mime};base64,".base64_encode((string) file_get_contents(public_path($path)));
    }
}
