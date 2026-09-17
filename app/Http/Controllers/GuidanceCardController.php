<?php

namespace App\Http\Controllers;

use App\Enums\GuidanceStage;
use App\Enums\GuidanceStatus;
use App\Models\GuidanceSession;
use App\Models\Student;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GuidanceCardController extends Controller
{
    /**
     * The card is printed per stage, so the student picks which one first.
     */
    public function index(Request $request): Response
    {
        // A student prints their own card; a supervisor or admin picks whose.
        $students = $request->user()->student !== null
            ? collect()
            : Student::query()
                ->visibleTo($request->user())
                ->with('user')
                ->join('users', 'users.id', '=', 'students.user_id')
                ->orderBy('users.name')
                ->select('students.*')
                ->get()
                ->map(fn (Student $student): array => [
                    'value' => $student->id,
                    'label' => $student->user->name,
                    'hint' => $student->student_number,
                ])
                ->values();

        return Inertia::render('thesis/guidance-cards/index', [
            'students' => $students,
            'stages' => [
                [
                    'value' => GuidanceStage::Proposal->value,
                    'title' => 'Kartu Bimbingan Proposal',
                    'description' => 'Riwayat bimbingan tahap proposal (Bab 1–3), untuk syarat pengajuan ujian proposal.',
                ],
                [
                    'value' => GuidanceStage::Research->value,
                    'title' => 'Kartu Bimbingan Hasil Penelitian',
                    'description' => 'Riwayat bimbingan tahap penelitian dan penulisan laporan, untuk syarat pengajuan ujian hasil penelitian.',
                ],
            ],
        ]);
    }

    /**
     * Printable A4 card: the guidance history plus signature blocks for each
     * supervisor and the program admin.
     */
    public function print(Request $request, GuidanceStage $stage, ?Student $student = null): View
    {
        $student ??= $request->user()->student;

        abort_if($student === null, 404);
        abort_unless($student->isVisibleTo($request->user()), 403);

        Gate::authorize('viewAny', GuidanceSession::class);

        $student->load(['user', 'department', 'supervisors.user']);

        $sessions = $student->guidanceSessions()
            ->where('stage', $stage)
            ->where('status', GuidanceStatus::Completed)
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get();

        return view('thesis.guidance-card', [
            'student' => $student,
            'stage' => $stage,
            'sessions' => $sessions,
            'proposalTitle' => $student->activeProposal()->title ?? '—',
            // The printed form always shows at least eight rows so there is
            // room to sign for sessions that have not happened yet.
            'blankRows' => max(0, 8 - $sessions->count()),
        ]);
    }
}
