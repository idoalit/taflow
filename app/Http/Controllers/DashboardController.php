<?php

namespace App\Http\Controllers;

use App\Concerns\FormatsIndonesianDates;
use App\Enums\ApprovalStatus;
use App\Enums\ExamStatus;
use App\Enums\GuidanceStage;
use App\Enums\GuidanceStatus;
use App\Enums\LecturerStatus;
use App\Enums\ProposalStatus;
use App\Enums\RubricStatus;
use App\Enums\SupervisorRole;
use App\Enums\UserRole;
use App\Http\Resources\GuidanceSessionSummaryResource;
use App\Http\Resources\ProposalSummaryResource;
use App\Models\Lecturer;
use App\Models\ProposalExam;
use App\Models\ProposalExamRequest;
use App\Models\Rubric;
use App\Models\Student;
use App\Models\ThesisProposal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use FormatsIndonesianDates;

    /**
     * One entry point, four dashboards. Each role gets the summary its design
     * calls for, built from the same underlying models.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // A role with no seat behind it — a dosen account never linked to a
        // dosen record, an admin never assigned a prodi — cannot be served any
        // dashboard, and saying so beats an empty one.
        abort_if(
            $user->activeWorkspace() === null && $user->roles()->exists(),
            403,
            'Akun Anda memiliki peran '.$user->roles()->pluck('name')->implode(', ')
            .' tetapi belum terhubung dengan data mahasiswa, dosen, atau program studi mana pun. '
            .'Hubungi admin program studi Anda.',
        );

        return match ($user->primaryRole()) {
            UserRole::Student => $this->studentDashboard($request),
            UserRole::Lecturer => $this->lecturerDashboard($request),
            UserRole::Admin => $this->adminDashboard($request),
            default => Inertia::render('dashboard', [
                'greeting' => $this->greeting($request),
            ]),
        };
    }

    /**
     * What a program studi needs to see first: the size of the cohort, and the
     * two things that stall it — students with nobody assigned, and dosen who
     * have no room left to take anyone.
     */
    private function adminDashboard(Request $request): Response
    {
        $department = $request->user()->activeWorkspace()->department;

        abort_if($department === null, 403, 'Workspace ini tidak terhubung dengan program studi mana pun.');

        $students = Student::query()->where('department_id', $department->id);
        $lecturers = Lecturer::query()
            ->whereHas('departments', fn (Builder $query) => $query->whereKey($department->id));

        $withoutSupervisor = (clone $students)->whereDoesntHave('supervisorAssignments')->count();

        $atCapacity = $lecturers->clone()
            ->where('status', LecturerStatus::Active)
            ->withCount(['supervisorAssignments as supervision_count' => fn (Builder $query) => $query
                ->where('role', SupervisorRole::Supervisor)])
            ->get()
            ->filter(fn (Lecturer $lecturer): bool => $lecturer->supervision_count >= $lecturer->max_supervisions)
            ->count();

        $pendingProposals = ThesisProposal::query()
            ->where('department_id', $department->id)
            ->where('status', ProposalStatus::Submitted)
            ->count();

        $scheduledExams = ProposalExam::query()
            ->whereHas('student', fn (Builder $query) => $query->where('department_id', $department->id))
            ->where('status', ExamStatus::Scheduled)
            ->count();

        $alerts = [];

        if ($withoutSupervisor > 0) {
            $alerts[] = [
                'text' => "{$withoutSupervisor} mahasiswa belum memiliki dosen pembimbing",
                'href' => route('admin.students.index'),
            ];
        }

        if ($atCapacity > 0) {
            $alerts[] = [
                'text' => "{$atCapacity} dosen sudah mencapai kuota maksimal bimbingan",
                'href' => route('admin.lecturers.index'),
            ];
        }

        return Inertia::render('admin/dashboard', [
            'greeting' => $this->greeting($request),
            'department' => $department->name,
            'kpis' => [
                ['label' => 'Total Mahasiswa', 'value' => (string) $students->count()],
                ['label' => 'Total Dosen', 'value' => (string) $lecturers->clone()->count()],
                ['label' => 'Belum Ada Pembimbing', 'value' => (string) $withoutSupervisor],
                ['label' => 'Proposal Menunggu', 'value' => (string) $pendingProposals],
                ['label' => 'Ujian Terjadwal', 'value' => (string) $scheduledExams],
                ['label' => 'Rubrik Terbit', 'value' => (string) Rubric::query()
                    ->where('department_id', $department->id)
                    ->where('status', RubricStatus::Published)
                    ->count()],
            ],
            'alerts' => $alerts,
        ]);
    }

    private function studentDashboard(Request $request): Response
    {
        $student = $request->user()->student;

        abort_if($student === null, 403, 'Akun Anda belum terhubung dengan data mahasiswa.');

        $student->load(['department', 'supervisors.user']);
        $proposal = $student->activeProposal();

        $upcoming = $student->guidanceSessions()
            ->where('status', GuidanceStatus::Ongoing)
            ->orderByRaw('meeting_scheduled_at is null')
            ->orderBy('meeting_scheduled_at')
            ->first();

        return Inertia::render('thesis/dashboard/student', [
            'greeting' => $this->greeting($request),
            'proposal' => $proposal === null ? null : new ProposalSummaryResource($proposal),
            'canCreateProposal' => Gate::allows('create', ThesisProposal::class),
            'primarySupervisor' => $student->supervisors->first()?->user->name,
            'upcomingSession' => $upcoming === null
                ? null
                : new GuidanceSessionSummaryResource($upcoming),
            'activity' => $this->recentActivity($student),
        ]);
    }

    /**
     * What is waiting on this lecturer, and the students behind it.
     */
    private function lecturerDashboard(Request $request): Response
    {
        $lecturer = $request->user()->lecturer;

        abort_if($lecturer === null, 403, 'Akun Anda belum terhubung dengan data dosen.');

        $students = Student::query()
            ->visibleTo($request->user())
            ->with(['user', 'department', 'supervisorAssignments', 'thesisProposals'])
            ->get();

        $awaitingReview = ThesisProposal::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->whereIn('status', [ProposalStatus::Submitted, ProposalStatus::UnderReview])
            ->count();

        $awaitingVerification = ProposalExamRequest::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->where('supervisor_status', ApprovalStatus::Pending)
            ->count();

        $pendingEvaluations = ProposalExam::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->whereNot('status', ExamStatus::Cancelled)
            ->whereDoesntHave(
                'evaluations',
                fn (Builder $query) => $query->where('lecturer_id', $lecturer->id)
            )
            ->count();

        return Inertia::render('thesis/dashboard/lecturer', [
            'greeting' => $this->greeting($request),
            'kpis' => [
                ['label' => 'Mahasiswa Bimbingan', 'value' => $students->count()],
                ['label' => 'Proposal Menunggu Review', 'value' => $awaitingReview],
                ['label' => 'Pengajuan Ujian Menunggu', 'value' => $awaitingVerification],
                ['label' => 'Evaluasi Ujian Belum Diisi', 'value' => $pendingEvaluations],
            ],
            'students' => $students->map(function (Student $student) use ($lecturer): array {
                $proposal = $student->activeProposal();
                $assignment = $student->supervisorAssignments->firstWhere('lecturer_id', $lecturer->id);
                $settings = $student->department->activeThesisSetting();

                return [
                    'id' => $student->id,
                    'name' => $student->user->name,
                    'student_number' => $student->student_number,
                    'title' => $proposal?->title,
                    'proposal_id' => $proposal?->id,
                    'my_role' => $assignment?->positionLabel() ?? 'Penguji',
                    'status_label' => $proposal?->status->label() ?? 'Belum Ada Proposal',
                    'sessions_completed' => $student->completedGuidanceCount(GuidanceStage::Proposal),
                    'sessions_required' => $settings->min_proposal_guidance_sessions,
                ];
            })->values(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function greeting(Request $request): array
    {
        $hour = (int) now()->format('H');

        return [
            'salutation' => match (true) {
                $hour < 11 => 'Selamat pagi',
                $hour < 15 => 'Selamat siang',
                $hour < 19 => 'Selamat sore',
                default => 'Selamat malam',
            },
            'name' => str($request->user()->name)->explode(' ')->first(),
        ];
    }

    /**
     * The activity feed pulls the handful of things that most recently changed
     * on this student's thesis.
     *
     * @return array<int, array{text: string, time: string}>
     */
    private function recentActivity(Student $student): array
    {
        $entries = collect();

        foreach ($student->thesisProposals()->latest('updated_at')->limit(3)->get() as $proposal) {
            $entries->push([
                'text' => "Proposal \"{$proposal->title}\" — {$proposal->status->label()}",
                'at' => $proposal->updated_at,
            ]);
        }

        foreach ($student->guidanceSessions()->latest('updated_at')->limit(3)->get() as $session) {
            $entries->push([
                'text' => "Sesi bimbingan \"{$session->title}\" — {$session->status->label()}",
                'at' => $session->updated_at,
            ]);
        }

        foreach ($student->researchLogbooks()->latest('updated_at')->limit(2)->get() as $logbook) {
            $entries->push([
                'text' => "Catatan logbook ditambahkan — progres {$logbook->progress}%",
                'at' => $logbook->updated_at,
            ]);
        }

        return $entries
            ->sortByDesc('at')
            ->take(5)
            ->map(fn (array $entry): array => [
                'text' => $entry['text'],
                'time' => $this->relative($entry['at']) ?? '',
            ])
            ->values()
            ->all();
    }
}
