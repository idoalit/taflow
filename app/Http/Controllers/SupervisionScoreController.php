<?php

namespace App\Http\Controllers;

use App\Enums\ExamStage;
use App\Models\SupervisionIndicator;
use App\Models\SupervisionScore;
use App\Models\ThesisExam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Form Penilaian Proses Bimbingan: the pembimbing's own marks on how the
 * supervision went, kept apart from the exam rubric the examiners fill in.
 */
class SupervisionScoreController extends Controller
{
    public function create(Request $request, ExamStage $stage, int $exam): Response
    {
        $thesisExam = $this->find($stage, $exam);

        Gate::authorize('scoreSupervision', $thesisExam);

        $indicators = SupervisionIndicator::activeFor($thesisExam->student->department);

        abort_if(
            $indicators->isEmpty(),
            403,
            'Program studi belum menetapkan indikator penilaian proses bimbingan.',
        );

        $thesisExam->load('student.user');

        return Inertia::render('thesis/exams/supervision', [
            'exam' => [
                'id' => $thesisExam->id,
                'stage' => $stage->value,
                'title' => $thesisExam->title,
                'student' => $thesisExam->student->user->name,
                'student_number' => $thesisExam->student->student_number,
                'show_url' => route('exams.show', ['stage' => $stage, 'exam' => $thesisExam->id]),
            ],
            'indicators' => $indicators->map(fn (SupervisionIndicator $indicator): array => [
                'id' => $indicator->id,
                'code' => $indicator->code,
                'label' => $indicator->label,
                'max_score' => $indicator->max_score,
            ])->values(),
            'total_max' => (int) $indicators->sum('max_score'),
        ]);
    }

    public function store(Request $request, ExamStage $stage, int $exam): RedirectResponse
    {
        $thesisExam = $this->find($stage, $exam);

        Gate::authorize('scoreSupervision', $thesisExam);

        $indicators = SupervisionIndicator::activeFor($thesisExam->student->department);
        $lecturer = $request->user()->lecturer;

        $validated = $request->validate([
            'scores' => ['required', 'array'],
            'scores.*' => ['required', 'numeric'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'scores' => 'nilai indikator',
            'notes' => 'catatan',
        ]);

        $errors = [];

        foreach ($indicators as $indicator) {
            $value = $validated['scores'][$indicator->code] ?? null;

            if ($value === null || $value === '') {
                $errors["scores.{$indicator->code}"] = "Nilai indikator {$indicator->code} wajib diisi.";

                continue;
            }

            if ($value < 0 || $value > $indicator->max_score) {
                $errors["scores.{$indicator->code}"] =
                    "Nilai indikator {$indicator->code} harus antara 0 dan {$indicator->max_score}.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($thesisExam, $indicators, $lecturer, $validated): void {
            foreach ($indicators as $indicator) {
                SupervisionScore::updateOrCreate(
                    [
                        'exam_type' => $thesisExam->getMorphClass(),
                        'exam_id' => $thesisExam->id,
                        'lecturer_id' => $lecturer->id,
                        'indicator_code' => $indicator->code,
                    ],
                    [
                        'supervision_indicator_id' => $indicator->id,
                        // Copied, so editing the master list later cannot
                        // rewrite a mark that has already been given.
                        'indicator_label' => $indicator->label,
                        'max_score' => $indicator->max_score,
                        'score' => $validated['scores'][$indicator->code],
                        'notes' => $validated['notes'] ?? null,
                        'scored_at' => now(),
                    ],
                );
            }
        });

        return to_route('exams.show', ['stage' => $stage, 'exam' => $thesisExam->id])
            ->with('success', 'Penilaian proses bimbingan tersimpan.');
    }

    private function find(ExamStage $stage, int $id): ThesisExam
    {
        return $stage->modelClass()::query()->where('id', $id)->firstOrFail();
    }
}
