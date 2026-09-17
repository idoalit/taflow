<?php

namespace App\Http\Controllers;

use App\Actions\Thesis\NotifyThesisParticipants;
use App\Actions\Thesis\StoreThesisDocument;
use App\Enums\NotificationCategory;
use App\Http\Requests\Thesis\StoreGuidanceMessageRequest;
use App\Models\GuidanceSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class GuidanceMessageController extends Controller
{
    /**
     * Post a message into an async guidance session. Both the student and their
     * supervisors use this same endpoint.
     */
    public function store(
        StoreGuidanceMessageRequest $request,
        GuidanceSession $guidanceSession,
        StoreThesisDocument $storeDocument,
        NotifyThesisParticipants $notify,
    ): RedirectResponse {
        Gate::authorize('participate', $guidanceSession);

        $attachment = $request->hasFile('attachment')
            ? $storeDocument($request->file('attachment'), $guidanceSession->student, 'guidance')
            : null;

        $guidanceSession->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $request->validated('message'),
            'attachment_path' => $attachment['path'] ?? null,
            'attachment_name' => $attachment['name'] ?? null,
        ]);

        $notify->counterpart(
            $guidanceSession->student,
            $request->user(),
            NotificationCategory::Document,
            'Pesan bimbingan baru',
            "{$request->user()->name} mengirim pesan pada sesi \"{$guidanceSession->title}\".",
            route('guidance-sessions.show', $guidanceSession),
        );

        return back();
    }
}
