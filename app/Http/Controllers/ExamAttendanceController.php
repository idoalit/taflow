<?php

namespace App\Http\Controllers;

use App\Enums\ExamStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Digital presensi. Anyone seated at the sitting marks their own attendance;
 * the program studi may mark anyone's, since it runs the room.
 */
class ExamAttendanceController extends Controller
{
    public function update(Request $request, ExamStage $stage, int $exam): RedirectResponse
    {
        $thesisExam = $stage->modelClass()::query()->where('id', $exam)->firstOrFail();

        Gate::authorize('view', $thesisExam);

        $validated = $request->validate([
            'participant_id' => ['required', 'integer'],
            'attended' => ['required', 'boolean'],
        ], [], [
            'participant_id' => 'peserta',
            'attended' => 'kehadiran',
        ]);

        $participant = $thesisExam->participants()
            ->where('id', $validated['participant_id'])
            ->firstOrFail();

        Gate::authorize('markAttendance', [$thesisExam, $participant]);

        $participant->update([
            'attended' => $validated['attended'],
            'attended_at' => $validated['attended'] ? now() : null,
        ]);

        return back()->with('success', $validated['attended']
            ? "Kehadiran {$participant->user->name} dicatat."
            : "Kehadiran {$participant->user->name} dibatalkan.");
    }
}
