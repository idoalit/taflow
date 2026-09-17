<?php

namespace App\Http\Controllers;

use App\Actions\Thesis\NotifyThesisParticipants;
use App\Enums\GuidanceStatus;
use App\Enums\NotificationCategory;
use App\Models\GuidanceSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GuidanceSessionActionController extends Controller
{
    /**
     * Marking a session complete is what counts it towards the exam
     * requirements, so only a supervisor may do it.
     */
    public function complete(
        Request $request,
        GuidanceSession $guidanceSession,
        NotifyThesisParticipants $notify,
    ): RedirectResponse {
        Gate::authorize('complete', $guidanceSession);

        $validated = $request->validate([
            'completion_notes' => ['nullable', 'string', 'max:2000'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ], [], ['completion_notes' => 'catatan penyelesaian', 'feedback' => 'umpan balik']);

        $guidanceSession->update([
            ...$validated,
            'status' => GuidanceStatus::Completed,
            'completed_at' => now(),
        ]);

        $notify->student(
            $guidanceSession->student,
            NotificationCategory::Approval,
            'Sesi bimbingan ditandai selesai',
            "{$request->user()->name} menandai sesi \"{$guidanceSession->title}\" sebagai selesai.",
            route('guidance-sessions.show', $guidanceSession),
        );

        return back()->with('success', 'Sesi bimbingan ditandai selesai.');
    }
}
