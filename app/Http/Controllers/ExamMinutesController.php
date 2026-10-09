<?php

namespace App\Http\Controllers;

use App\Actions\Thesis\ComposeFinalGrade;
use App\Enums\AuditAction;
use App\Enums\ExamParticipantRole;
use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\ExamStatus;
use App\Models\AuditLog;
use App\Models\ExamMinutes;
use App\Models\ThesisExam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Berita Acara. The chair records the panel's decision, and the score it was
 * taken on is written down with it rather than read live afterwards.
 */
class ExamMinutesController extends Controller
{
    public function store(
        Request $request,
        ExamStage $stage,
        int $exam,
        ComposeFinalGrade $compose,
    ): RedirectResponse {
        $thesisExam = $stage->modelClass()::query()->where('id', $exam)->firstOrFail();

        Gate::authorize('decide', $thesisExam);

        $validated = $request->validate([
            'decision' => ['required', Rule::enum(ExamResult::class)],
            'revision_days' => ['required', 'integer', 'min:0', 'max:180'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'decision' => 'keputusan',
            'revision_days' => 'lama perbaikan',
            'notes' => 'catatan',
        ]);

        $missing = $compose->missing($thesisExam);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'decision' => 'Berita acara belum dapat ditetapkan: '.implode(' ', $missing),
            ]);
        }

        $grade = $compose->handle($thesisExam);

        $minutes = ExamMinutes::updateOrCreate(
            ['exam_type' => $thesisExam->getMorphClass(), 'exam_id' => $thesisExam->id],
            [
                'decided_by' => $request->user()->id,
                'decision' => $validated['decision'],
                'exam_score' => $grade['exam_score'],
                'supervision_score' => $grade['supervision_score'],
                'final_score' => $grade['final_score'],
                'letter' => $grade['letter'],
                'revision_days' => $validated['revision_days'],
                'notes' => $validated['notes'] ?? null,
                'decided_at' => now(),
            ],
        );

        $thesisExam->update([
            'result' => $validated['decision'],
            'final_score' => $grade['final_score'],
            'status' => ExamStatus::Scored,
            'completed_at' => $thesisExam->completed_at ?? now(),
        ]);

        AuditLog::record(
            $minutes,
            AuditAction::MinutesDecided,
            $request->user(),
            changes: [
                'decision' => $validated['decision'],
                'final_score' => $grade['final_score'],
            ],
            request: $request,
        );

        return back()->with('success', 'Berita acara tersimpan.');
    }

    /**
     * Who may take the decision: the chair of this sitting.
     */
    public static function chairs(ThesisExam $exam): bool
    {
        return $exam->participants()
            ->where('role', ExamParticipantRole::ChairExaminer)
            ->exists();
    }
}
