<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Thesis\BuildVerificationQr;
use App\Actions\Thesis\ComposeFinalGrade;
use App\Actions\Thesis\NotifyThesisParticipants;
use App\Enums\ExamStage;
use App\Enums\ExamStatus;
use App\Enums\NotificationCategory;
use App\Http\Controllers\Admin\Concerns\ScopesToDepartment;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ThesisExam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Releasing is the program studi publishing a result, and locking it.
 *
 * Nothing behind a released exam may change without an explicit unlock, which
 * costs a reason and leaves a trail.
 */
class ExamReleaseController extends Controller
{
    use ScopesToDepartment;

    public function store(
        Request $request,
        ExamStage $stage,
        int $exam,
        ComposeFinalGrade $compose,
        NotifyThesisParticipants $notify,
    ): RedirectResponse {
        $thesisExam = $this->find($request, $stage, $exam);

        Gate::authorize('release', $thesisExam);

        $missing = $compose->missing($thesisExam);

        if ($thesisExam->minutes === null) {
            $missing[] = 'Berita acara belum ditetapkan oleh ketua penguji.';
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'release' => 'Hasil belum dapat dirilis: '.implode(' ', $missing),
            ]);
        }

        $thesisExam->update([
            'status' => ExamStatus::Released,
            'released_at' => now(),
            'released_by' => $request->user()->id,
        ]);

        app(BuildVerificationQr::class)->tokenFor($thesisExam->refresh());

        AuditLog::record($thesisExam, 'exam.released', $request->user(), request: $request);

        $notify->student(
            $thesisExam->student,
            NotificationCategory::Approval,
            "Hasil {$stage->title()} dirilis",
            'Nilai akhir dan daftar revisi Anda sudah dapat dilihat.',
            route('exams.show', ['stage' => $stage, 'exam' => $thesisExam->id]),
        );

        return back()->with('success', 'Hasil ujian dirilis.');
    }

    /**
     * Reopening a released result. The reason is required, and recorded.
     */
    public function destroy(Request $request, ExamStage $stage, int $exam): RedirectResponse
    {
        $thesisExam = $this->find($request, $stage, $exam);

        Gate::authorize('release', $thesisExam);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'reason.required' => 'Alasan membuka kunci wajib diisi.',
            'reason.min' => 'Jelaskan alasannya sedikitnya 10 karakter.',
        ], [
            'reason' => 'alasan',
        ]);

        $thesisExam->update([
            'status' => ExamStatus::Scored,
            'released_at' => null,
            'released_by' => null,
        ]);

        AuditLog::record(
            $thesisExam,
            'exam.unlocked',
            $request->user(),
            reason: $validated['reason'],
            request: $request,
        );

        return back()->with('success', 'Kunci hasil dibuka. Alasan tercatat pada log audit.');
    }

    private function find(Request $request, ExamStage $stage, int $id): ThesisExam
    {
        $department = $this->department($request);

        return $stage->modelClass()::query()
            ->whereHas('student', fn (Builder $query) => $query->where('department_id', $department->id))
            ->where('id', $id)
            ->firstOrFail();
    }
}
