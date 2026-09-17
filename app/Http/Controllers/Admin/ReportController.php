<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\ExamStatus;
use App\Enums\GuidanceStatus;
use App\Enums\ProposalStatus;
use App\Enums\StudentStatus;
use App\Http\Controllers\Admin\Concerns\ScopesToDepartment;
use App\Http\Controllers\Controller;
use App\Models\GuidanceSession;
use App\Models\Student;
use App\Models\ThesisExam;
use App\Models\ThesisProposal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Laporan & Rekap: the counts a program studi is asked for, all derived rather
 * than stored, so a report can never disagree with the screens it summarises.
 */
class ReportController extends Controller
{
    use ScopesToDepartment;

    public function index(Request $request): Response
    {
        Gate::authorize('viewReports', Student::class);

        $department = $this->department($request);
        $students = Student::query()->where('department_id', $department->id);

        return Inertia::render('admin/reports/index', [
            'department' => $department->name,
            'students' => [
                'total' => (clone $students)->count(),
                'by_status' => array_map(fn (StudentStatus $status): array => [
                    'label' => $status->label(),
                    'count' => (clone $students)->where('status', $status)->count(),
                ], StudentStatus::cases()),
                'without_supervisor' => (clone $students)
                    ->whereDoesntHave('supervisorAssignments')
                    ->count(),
            ],
            'proposals' => array_map(fn (ProposalStatus $status): array => [
                'label' => $status->label(),
                'count' => ThesisProposal::query()
                    ->where('department_id', $department->id)
                    ->where('status', $status)
                    ->count(),
            ], ProposalStatus::cases()),
            'guidance' => [
                'total' => $this->sessions($department->id)->count(),
                'completed' => $this->sessions($department->id)
                    ->where('status', GuidanceStatus::Completed)
                    ->count(),
            ],
            'exams' => array_map(fn (ExamStage $stage): array => [
                'stage_label' => $stage->title(),
                'scheduled' => $this->exams($stage, $department->id)
                    ->where('status', ExamStatus::Scheduled)
                    ->count(),
                'completed' => $this->exams($stage, $department->id)
                    ->whereIn('status', ExamStatus::decided())
                    ->count(),
                'passed' => $this->exams($stage, $department->id)
                    ->whereIn('result', [ExamResult::Passed, ExamResult::ConditionalPass])
                    ->count(),
                'failed' => $this->exams($stage, $department->id)
                    ->where('result', ExamResult::Failed)
                    ->count(),
                'pending_requests' => $stage->requestModelClass()::query()
                    ->whereHas('student', fn (Builder $query) => $query->where('department_id', $department->id))
                    ->where('status', ApprovalStatus::Pending)
                    ->count(),
            ], ExamStage::cases()),
        ]);
    }

    /**
     * @return Builder<GuidanceSession>
     */
    private function sessions(int $departmentId): Builder
    {
        return GuidanceSession::query()
            ->whereHas('student', fn (Builder $query) => $query->where('department_id', $departmentId));
    }

    /**
     * @return Builder<ThesisExam>
     */
    private function exams(ExamStage $stage, int $departmentId): Builder
    {
        return $stage->modelClass()::query()
            ->whereHas('student', fn (Builder $query) => $query->where('department_id', $departmentId));
    }
}
