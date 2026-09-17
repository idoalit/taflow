<?php

namespace App\Http\Controllers;

use App\Actions\Thesis\ComposeFinalGrade;
use App\Actions\Thesis\NotifyThesisParticipants;
use App\Enums\ExamParticipantRole;
use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\NotificationCategory;
use App\Http\Requests\Thesis\StoreExamEvaluationRequest;
use App\Http\Resources\ThesisExamDetailResource;
use App\Http\Resources\ThesisExamSummaryResource;
use App\Models\ExamEvaluation;
use App\Models\ExamParticipant;
use App\Models\RevisionNote;
use App\Models\RubricCriterion;
use App\Models\Student;
use App\Models\ThesisExam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pelaksanaan Ujian, for both stages. The design shows one screen with a tab
 * per stage, so one controller serves both rather than a proposal-only screen
 * and a research-only copy of it.
 */
class ExamController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ThesisExam::class);

        $counts = [];

        foreach (ExamStage::cases() as $case) {
            $counts[$case->value] = $this->visibleExams($request, $case)->count();
        }

        $stage = $this->resolveStage($request, $counts);

        $exams = $this->visibleExams($request, $stage)
            ->with(['student.user', 'evaluations'])
            ->latest('scheduled_at')
            ->get();

        return Inertia::render('thesis/exams/index', [
            'exams' => ThesisExamSummaryResource::collection($exams),
            'stage' => $stage->value,
            'stages' => array_map(fn (ExamStage $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'count' => $counts[$case->value],
            ], ExamStage::cases()),
        ]);
    }

    public function show(Request $request, ExamStage $stage, int $exam): Response
    {
        $thesisExam = $this->findExam($stage, $exam);

        Gate::authorize('view', $thesisExam);

        $thesisExam->load([
            'student.user', 'student.department',
            'evaluations.lecturer.user', 'evaluations.scores', 'minutes',
        ]);

        $grade = app(ComposeFinalGrade::class);
        $lecturerId = $request->user()->lecturer?->id;

        return Inertia::render('thesis/exams/show', [
            'exam' => new ThesisExamDetailResource($thesisExam),
            'canEvaluate' => Gate::allows('evaluate', $thesisExam),
            'canScoreSupervision' => Gate::allows('scoreSupervision', $thesisExam),
            'supervisionUrl' => route('exams.supervision.create', [
                'stage' => $stage,
                'exam' => $thesisExam->id,
            ]),
            'grade' => $grade->handle($thesisExam),
            'missing' => $grade->missing($thesisExam),
            'documents' => app(ExamDocumentController::class)->catalogue($thesisExam, $stage),
            'documentsUrl' => route('exams.documents.all', [
                'stage' => $stage,
                'exam' => $thesisExam->id,
            ]),
            'participants' => $this->participantRows($thesisExam, $request),
            'myRevisions' => $lecturerId === null ? [] : $thesisExam->revisionNotes()
                ->where('lecturer_id', $lecturerId)
                ->get()
                ->map(fn (RevisionNote $note): array => [
                    'body' => $note->body,
                    'due_date' => $note->due_date?->toDateString(),
                ])->values()->all(),
            'allRevisions' => Gate::allows('viewResult', $thesisExam)
                ? $thesisExam->revisionNotes()->with('lecturer.user')->get()
                    ->map(fn (RevisionNote $note): array => [
                        'id' => $note->id,
                        'lecturer' => $note->lecturer->user->name,
                        'position' => $note->position,
                        'body' => $note->body,
                        'status' => $note->status->value,
                        'status_label' => $note->status->label(),
                        'due_date' => $note->due_date?->translatedFormat('d M Y'),
                    ])->values()->all()
                : [],
            'minutes' => $thesisExam->minutes === null ? null : [
                'decision' => $thesisExam->minutes->decision->value,
                'decision_label' => $thesisExam->minutes->decision->label(),
                'revision_days' => $thesisExam->minutes->revision_days,
                'notes' => $thesisExam->minutes->notes,
            ],
            'results' => array_map(
                fn (ExamResult $result): array => ['value' => $result->value, 'label' => $result->label()],
                ExamResult::cases(),
            ),
            'released' => $thesisExam->isReleased(),
            'canDecide' => Gate::allows('decide', $thesisExam),
            'canWriteRevisions' => Gate::allows('writeRevisions', $thesisExam),
            'canRelease' => Gate::allows('release', $thesisExam),
            'canViewResult' => Gate::allows('viewResult', $thesisExam),
            'canTickRevisions' => $this->canTickRevisions($thesisExam),
            'urls' => [
                'sign' => route('exams.sign', ['stage' => $stage, 'exam' => $thesisExam->id]),
                'attendance' => route('exams.attendance.update', ['stage' => $stage, 'exam' => $thesisExam->id]),
                'revisions' => route('exams.revisions.store', ['stage' => $stage, 'exam' => $thesisExam->id]),
                'minutes' => route('exams.minutes.store', ['stage' => $stage, 'exam' => $thesisExam->id]),
                'release' => route('admin.exams.release', ['stage' => $stage, 'exam' => $thesisExam->id]),
            ],
        ]);
    }

    public function evaluate(Request $request, ExamStage $stage, int $exam): Response
    {
        $thesisExam = $this->findExam($stage, $exam);

        Gate::authorize('evaluate', $thesisExam);

        $thesisExam->load(['student.user', 'evaluations.lecturer.user']);

        $rubric = $thesisExam->effectiveRubric();

        abort_if(
            $rubric === null,
            403,
            'Program studi belum menetapkan rubrik untuk '.$stage->title()
            .'. Hubungi admin program studi sebelum mengisi penilaian.',
        );

        return Inertia::render('thesis/exams/evaluate', [
            'exam' => new ThesisExamDetailResource($thesisExam),
            'rubric' => [
                'name' => $rubric->name,
                'description' => $rubric->description,
                'pass_threshold' => $thesisExam->passThreshold(),
                'block_additive' => $rubric->isBlockAdditive(),
                // The blocks are sent as the rubric's own shape, so the form is
                // laid out the way the paper form is rather than as one flat
                // list of twenty-eight lines.
                'blocks' => $rubric->blocks()->map(fn (array $block): array => [
                    'code' => $block['code'],
                    'label' => $block['label'],
                    'max' => $block['max'],
                    'subblocks' => collect($block['subblocks'])->map(fn (array $sub): array => [
                        'code' => $sub['code'],
                        'label' => $sub['label'],
                        'max' => $sub['max'],
                        'items' => collect($sub['items'])
                            ->map(fn (RubricCriterion $criterion): array => $this->criterionRow($criterion))
                            ->values(),
                    ])->values(),
                ])->values(),
                'criteria' => $rubric->criteria
                    ->map(fn (RubricCriterion $criterion): array => $this->criterionRow($criterion))
                    ->values(),
            ],
            'results' => array_map(
                fn (ExamResult $result): array => ['value' => $result->value, 'label' => $result->label()],
                ExamResult::cases(),
            ),
        ]);
    }

    /**
     * An evaluation is final once saved, which is why the form warns about it.
     * The exam itself settles only when every panel member has submitted.
     */
    public function storeEvaluation(
        StoreExamEvaluationRequest $request,
        ExamStage $stage,
        int $exam,
        NotifyThesisParticipants $notify,
    ): RedirectResponse {
        $thesisExam = $this->findExam($stage, $exam);

        Gate::authorize('evaluate', $thesisExam);

        $lecturer = $request->user()->lecturer;
        $validated = $request->validated();
        $rubric = $thesisExam->effectiveRubric();

        abort_if($rubric === null, 403, 'Program studi belum menetapkan rubrik untuk '.$stage->title().'.');

        // Shape and range were settled by StoreExamEvaluationRequest.
        $marks = collect($rubric->criteria)
            ->mapWithKeys(fn (RubricCriterion $criterion): array => [
                $criterion->key => (float) $validated['scores'][$criterion->key],
            ])
            ->all();

        // A verdict is its total and its criterion scores together: a partial
        // write would keep a score nobody can account for.
        DB::transaction(function () use ($thesisExam, $lecturer, $rubric, $marks, $validated): void {
            $evaluation = $thesisExam->evaluations()->create([
                'lecturer_id' => $lecturer->id,
                'rubric_id' => $rubric->id,
                'panel_role' => $thesisExam->panel()
                    ->firstWhere('lecturerId', $lecturer->id)
                    ->supervisorRole,
                'final_score' => ExamEvaluation::scoreFor($rubric, $marks),
                'result' => $validated['result'],
                'feedback' => $validated['feedback'] ?? null,
                'revision_notes' => $validated['revision_notes'] ?? null,
                'evaluated_at' => now(),
            ]);

            foreach ($rubric->criteria as $criterion) {
                $evaluation->scores()->create([
                    'criterion_key' => $criterion->key,
                    'criterion_label' => $criterion->label,
                    'weight' => $criterion->weight,
                    'max_score' => $criterion->max_score,
                    'score' => $marks[$criterion->key],
                ]);
            }
        });

        $thesisExam->settleFromEvaluations();

        if ($thesisExam->refresh()->result !== null) {
            $notify->student(
                $thesisExam->student,
                NotificationCategory::Approval,
                "Hasil {$stage->title()} terbit",
                "Hasil {$stage->title()} Anda: {$thesisExam->result->label()}.",
                route('exams.show', ['stage' => $stage, 'exam' => $thesisExam->id]),
            );
        }

        return to_route('exams.show', ['stage' => $stage, 'exam' => $thesisExam->id])
            ->with('success', 'Evaluasi tersimpan.');
    }

    /**
     * One scorable line, as both the flat list and the blocks need it.
     *
     * @return array{key: string, label: string, weight: int|null, max_score: int, item_number: int|null}
     */
    private function criterionRow(RubricCriterion $criterion): array
    {
        return [
            'key' => $criterion->key,
            'label' => $criterion->label,
            'weight' => $criterion->weight,
            'max_score' => $criterion->max_score,
            'item_number' => $criterion->item_number,
        ];
    }

    /**
     * Whether the viewer may work through the checklist. Asked of a real note
     * so the rule stays in the policy rather than being restated here.
     */
    private function canTickRevisions(ThesisExam $exam): bool
    {
        $note = $exam->revisionNotes()->first();

        return $note !== null && Gate::allows('toggleRevision', $note);
    }

    /**
     * Each seat, with what this viewer may do to it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function participantRows(ThesisExam $exam, Request $request): array
    {
        return $exam->participants()
            ->with(['user', 'lecturer'])
            ->get()
            ->sortBy(fn (ExamParticipant $p): int => match ($p->role) {
                ExamParticipantRole::ChairExaminer => 0,
                ExamParticipantRole::MemberExaminer => 1,
                ExamParticipantRole::Supervisor => 2,
                ExamParticipantRole::Student => 3,
            })
            ->values()
            ->map(fn (ExamParticipant $participant): array => [
                'id' => $participant->id,
                'name' => $participant->user->name,
                'role_label' => $participant->role->label(),
                'attended' => $participant->attended,
                'signed' => $participant->hasSigned(),
                'is_me' => $participant->user_id === $request->user()->id,
                'can_mark' => Gate::allows('markAttendance', [$exam, $participant]),
            ])
            ->all();
    }

    /**
     * @return Builder<ThesisExam>
     */
    /**
     * The sittings this viewer may see listed, which is the same reach
     * ThesisExamPolicy::view() allows: their own students, plus any panel they
     * have been seated on.
     *
     * @return Builder<ThesisExam>
     */
    private function visibleExams(Request $request, ExamStage $stage): Builder
    {
        $user = $request->user();

        return $stage->modelClass()::query()
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->whereIn('student_id', Student::query()->visibleTo($user)->select('id'))
                    ->orWhereHas('participants', function (Builder $seat) use ($user): void {
                        $seat->where('user_id', $user->id);
                    });
            });
    }

    private function findExam(ExamStage $stage, int $id): ThesisExam
    {
        return $stage->modelClass()::query()->findOrFail($id);
    }

    /**
     * Honour an explicit stage; otherwise open on the proposal tab, unless it
     * is empty and the other one is not.
     *
     * @param  array<string, int>  $counts
     */
    private function resolveStage(Request $request, array $counts): ExamStage
    {
        $requested = ExamStage::tryFrom($request->string('stage')->toString());

        if ($requested !== null) {
            return $requested;
        }

        return $counts[ExamStage::Proposal->value] === 0 && $counts[ExamStage::Research->value] > 0
            ? ExamStage::Research
            : ExamStage::Proposal;
    }
}
