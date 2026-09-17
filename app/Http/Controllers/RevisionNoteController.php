<?php

namespace App\Http\Controllers;

use App\Enums\ExamStage;
use App\Enums\RevisionStatus;
use App\Models\RevisionNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Lembar Revisi. An examiner replaces their whole list at once, which is what
 * lets the numbering follow the order they put the lines in.
 */
class RevisionNoteController extends Controller
{
    public function store(Request $request, ExamStage $stage, int $exam): RedirectResponse
    {
        $thesisExam = $stage->modelClass()::query()->where('id', $exam)->firstOrFail();

        Gate::authorize('writeRevisions', $thesisExam);

        $validated = $request->validate([
            'notes' => ['present', 'array'],
            'notes.*.body' => ['required', 'string', 'max:2000'],
            'notes.*.due_date' => ['nullable', 'date'],
        ], [
            'notes.*.body.required' => 'Isi poin revisi tidak boleh kosong.',
        ], [
            'notes' => 'daftar revisi',
        ]);

        $lecturer = $request->user()->lecturer;

        DB::transaction(function () use ($thesisExam, $lecturer, $validated): void {
            $existing = $thesisExam->revisionNotes()
                ->where('lecturer_id', $lecturer->id)
                ->get()
                ->keyBy('body');

            $thesisExam->revisionNotes()->where('lecturer_id', $lecturer->id)->delete();

            foreach (array_values($validated['notes']) as $index => $note) {
                $thesisExam->revisionNotes()->create([
                    'lecturer_id' => $lecturer->id,
                    'position' => $index + 1,
                    'body' => $note['body'],
                    'due_date' => $note['due_date'] ?? null,
                    // A line the student had already ticked off keeps its state
                    // when the examiner reorders the sheet around it.
                    'status' => $existing[$note['body']]->status ?? RevisionStatus::Open,
                    'completed_at' => $existing[$note['body']]->completed_at ?? null,
                ]);
            }
        });

        return back()->with('success', 'Lembar revisi tersimpan.');
    }

    /**
     * The student ticking a line off their merged checklist.
     */
    public function toggle(Request $request, RevisionNote $revisionNote): RedirectResponse
    {
        Gate::authorize('toggleRevision', $revisionNote);

        $done = $revisionNote->status === RevisionStatus::Open;

        $revisionNote->update([
            'status' => $done ? RevisionStatus::Done : RevisionStatus::Open,
            'completed_at' => $done ? now() : null,
        ]);

        return back();
    }
}
