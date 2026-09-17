<?php

namespace App\Http\Controllers;

use App\Actions\Thesis\StoreThesisDocument;
use App\Actions\Thesis\ThesisPhaseAccess;
use App\Http\Requests\Thesis\StoreResearchLogbookRequest;
use App\Http\Resources\LogbookEntryResource;
use App\Models\ResearchLogbook;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ResearchLogbookController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ResearchLogbook::class);

        $entries = ResearchLogbook::query()
            ->whereIn('student_id', Student::query()->visibleTo($request->user())->select('id'))
            ->orderByDesc('log_date')
            ->orderByDesc('id')
            ->get();

        $latest = $entries->first();

        return Inertia::render('thesis/logbooks/index', [
            'entries' => LogbookEntryResource::collection($entries),
            'stats' => [
                'latest_progress' => $latest->progress ?? 0,
                'total' => $entries->count(),
                'last_date' => $latest === null ? '—' : $latest->log_date->isoFormat('D MMM YYYY'),
            ],
            'canCreate' => Gate::allows('create', ResearchLogbook::class),
            // Explains the empty, read-only state to a student who has not
            // reached the research phase yet.
            'lockReason' => $this->lockReason($request),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', ResearchLogbook::class);

        return Inertia::render('thesis/logbooks/create', [
            'today' => now()->toDateString(),
        ]);
    }

    public function store(StoreResearchLogbookRequest $request, StoreThesisDocument $storeDocument): RedirectResponse
    {
        Gate::authorize('create', ResearchLogbook::class);

        $student = $request->user()->student;

        $attachment = $request->hasFile('attachment')
            ? $storeDocument($request->file('attachment'), $student, 'logbook')
            : null;

        ResearchLogbook::create([
            ...$request->safe()->except('attachment'),
            'student_id' => $student->id,
            'attachment_path' => $attachment['path'] ?? null,
            'attachment_name' => $attachment['name'] ?? null,
        ]);

        return to_route('research-logbooks.index')->with('success', 'Catatan logbook tersimpan.');
    }

    /**
     * Why this student cannot add entries yet; null when they can, or when the
     * viewer is not a student at all.
     */
    private function lockReason(Request $request): ?string
    {
        $student = $request->user()->student;

        return $student === null ? null : ThesisPhaseAccess::forStudent($student)->researchPhaseReason();
    }
}
