<?php

namespace App\Http\Controllers;

use App\Actions\Thesis\NotifyThesisParticipants;
use App\Actions\Thesis\StoreThesisDocument;
use App\Actions\Thesis\ThesisPhaseAccess;
use App\Enums\GuidanceDisplayStatus;
use App\Enums\GuidanceStage;
use App\Enums\GuidanceStatus;
use App\Enums\GuidanceTargetType;
use App\Enums\GuidanceType;
use App\Enums\MeetingType;
use App\Enums\NotificationCategory;
use App\Http\Requests\Thesis\StoreGuidanceSessionRequest;
use App\Http\Resources\GuidanceSessionDetailResource;
use App\Http\Resources\GuidanceSessionSummaryResource;
use App\Models\GuidanceSession;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GuidanceSessionController extends Controller
{
    /**
     * Students see a short list of their own sessions; a supervisor sees every
     * session of every student they supervise, which is a worklist. It opens on
     * whatever actually needs attention: the live tab, or the next non-empty one.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', GuidanceSession::class);

        $visible = GuidanceSession::query()
            ->whereIn('student_id', Student::query()->visibleTo($request->user())->select('id'));

        $counts = [];

        foreach (GuidanceDisplayStatus::cases() as $case) {
            $counts[$case->value] = (clone $visible)->displayStatus($case)->count();
        }

        $tab = $this->resolveTab($request, $counts);
        $stage = $this->filterValue($request, 'stage', GuidanceStage::cases());

        $sessions = (clone $visible)
            ->displayStatus($tab)
            ->when($stage !== 'all', fn ($query) => $query->where('stage', $stage))
            ->with('student.user')
            ->orderByRaw('meeting_scheduled_at is null')
            ->orderBy('meeting_scheduled_at')
            ->latest('id')
            ->get();

        return Inertia::render('thesis/guidance-sessions/index', [
            'sessions' => GuidanceSessionSummaryResource::collection($sessions),
            'filters' => ['tab' => $tab->value, 'stage' => $stage],
            'tabs' => array_map(fn (GuidanceDisplayStatus $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'count' => $counts[$case->value],
            ], GuidanceDisplayStatus::cases()),
            'filterOptions' => [
                'stages' => $this->filterOptions('Semua Tahap', GuidanceStage::cases()),
            ],
            'canCreate' => Gate::allows('create', GuidanceSession::class),
        ]);
    }

    /**
     * Honour an explicit tab; otherwise open on the live one, falling back to
     * the next tab that actually has something in it.
     *
     * @param  array<string, int>  $counts
     */
    private function resolveTab(Request $request, array $counts): GuidanceDisplayStatus
    {
        $requested = GuidanceDisplayStatus::tryFrom($request->string('tab')->toString());

        if ($requested !== null) {
            return $requested;
        }

        foreach ([GuidanceDisplayStatus::Ongoing, GuidanceDisplayStatus::Scheduled, GuidanceDisplayStatus::Completed] as $candidate) {
            if ($counts[$candidate->value] > 0) {
                return $candidate;
            }
        }

        return GuidanceDisplayStatus::Ongoing;
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', GuidanceSession::class);

        $access = ThesisPhaseAccess::forStudent($request->user()->student);

        return Inertia::render('thesis/guidance-sessions/create', [
            'options' => [
                'stages' => $access->toOptions(),
                'targets' => $this->enumOptions(GuidanceTargetType::cases()),
                'guidanceTypes' => $this->enumOptions(GuidanceType::cases()),
                'meetingTypes' => $this->enumOptions(MeetingType::cases()),
            ],
        ]);
    }

    public function store(
        StoreGuidanceSessionRequest $request,
        StoreThesisDocument $storeDocument,
        NotifyThesisParticipants $notify,
    ): RedirectResponse {
        $student = $request->user()->student;
        $validated = $request->safe()->except('document');

        Gate::authorize('create', [GuidanceSession::class, GuidanceStage::from($validated['stage'])]);

        $document = $request->hasFile('document')
            ? $storeDocument($request->file('document'), $student, 'guidance')
            : null;

        // Async sessions are a running discussion; the meeting fields only
        // apply to face-to-face ones and are cleared here so a switched radio
        // does not leave stale values behind.
        if ($validated['guidance_type'] === GuidanceType::OnlineAsync->value) {
            $validated = [
                ...$validated,
                'meeting_type' => null,
                'meeting_location' => null,
                'meeting_link' => null,
                'meeting_scheduled_at' => null,
            ];
        }

        $session = GuidanceSession::create([
            ...$validated,
            'student_id' => $student->id,
            'created_by' => $request->user()->id,
            'status' => GuidanceStatus::Ongoing,
            'document_path' => $document['path'] ?? null,
            'document_name' => $document['name'] ?? null,
        ]);

        $notify->supervisors(
            $student,
            NotificationCategory::Schedule,
            'Sesi bimbingan baru dibuat',
            "{$student->user->name} membuat sesi \"{$session->title}\" ({$session->stage->label()}).",
            route('guidance-sessions.show', $session),
            $request->user(),
        );

        return to_route('guidance-sessions.show', $session)
            ->with('success', 'Sesi bimbingan berhasil dibuat.');
    }

    public function show(GuidanceSession $guidanceSession): Response
    {
        Gate::authorize('view', $guidanceSession);

        $guidanceSession->load([
            'student.user', 'student.department', 'student.supervisors.user',
            'messages.author',
        ]);

        return Inertia::render('thesis/guidance-sessions/show', [
            'session' => new GuidanceSessionDetailResource($guidanceSession),
            'can' => [
                'participate' => Gate::allows('participate', $guidanceSession),
                'cancel' => Gate::allows('cancel', $guidanceSession),
                'complete' => Gate::allows('complete', $guidanceSession),
            ],
        ]);
    }

    public function cancel(
        Request $request,
        GuidanceSession $guidanceSession,
        NotifyThesisParticipants $notify,
    ): RedirectResponse {
        Gate::authorize('cancel', $guidanceSession);

        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:1000'],
        ], [], ['cancellation_reason' => 'alasan pembatalan']);

        $guidanceSession->update([
            ...$validated,
            'status' => GuidanceStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $notify->counterpart(
            $guidanceSession->student,
            $request->user(),
            NotificationCategory::Schedule,
            'Sesi bimbingan dibatalkan',
            "Sesi \"{$guidanceSession->title}\" dibatalkan oleh {$request->user()->name}.",
            route('guidance-sessions.show', $guidanceSession),
        );

        return back()->with('success', 'Sesi bimbingan dibatalkan.');
    }

    /**
     * Every domain enum exposes value() and label(); the segmented controls in
     * the create form render straight from these pairs.
     *
     * @param  array<int, GuidanceStage|GuidanceTargetType|GuidanceType|MeetingType>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private function enumOptions(array $cases): array
    {
        return array_map(
            fn (GuidanceStage|GuidanceTargetType|GuidanceType|MeetingType $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            $cases,
        );
    }

    /**
     * Read a list filter, falling back to "all" for anything the enum does not
     * recognise.
     *
     * @param  array<int, \BackedEnum>  $cases
     */
    private function filterValue(Request $request, string $key, array $cases): string
    {
        $value = $request->string($key)->toString();
        $allowed = array_map(fn (\BackedEnum $case): string => (string) $case->value, $cases);

        return in_array($value, $allowed, true) ? $value : 'all';
    }

    /**
     * Filter options are served from the enums so the labels live in one place
     * rather than being restated in the page component.
     *
     * @param  array<int, GuidanceStage|GuidanceStatus>  $cases
     * @return array<int, array{value: string, label: string}>
     */
    private function filterOptions(string $allLabel, array $cases): array
    {
        return [
            ['value' => 'all', 'label' => $allLabel],
            ...array_map(fn (GuidanceStage|GuidanceStatus $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ], $cases),
        ];
    }
}
