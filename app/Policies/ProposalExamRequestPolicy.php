<?php

namespace App\Policies;

use App\Actions\Thesis\ExamEligibility;
use App\Enums\ApprovalStatus;
use App\Models\ProposalExamRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProposalExamRequestPolicy
{
    public function viewAny(User $user): Response
    {
        if (! $user->can('exam.view')) {
            return Response::deny('Anda tidak memiliki akses ke pengajuan ujian proposal.');
        }

        // Students of a program studi that runs no proposal exam have no
        // business on this screen, so the menu item is hidden and the route
        // itself refuses rather than showing an empty page.
        if ($user->student !== null
            && ! $user->student->department->activeThesisSetting()->requires_proposal_exam) {
            return Response::deny(ExamEligibility::forProposalExam($user->student)->summary);
        }

        return Response::allow();
    }

    public function view(User $user, ProposalExamRequest $request): bool
    {
        return $user->can('exam.view') && $request->student->isVisibleTo($user);
    }

    /**
     * Students submit their own requests, and only one may be outstanding.
     */
    public function create(User $user): Response
    {
        if (! $user->can('exam.request') || $user->student === null) {
            return Response::deny('Hanya mahasiswa yang dapat mengajukan ujian proposal.');
        }

        // Nothing to request where the program studi runs no proposal exam.
        if (! $user->student->department->activeThesisSetting()->requires_proposal_exam) {
            return Response::deny(ExamEligibility::forProposalExam($user->student)->summary);
        }

        $pending = $user->student->proposalExamRequests()
            ->where('status', ApprovalStatus::Pending)
            ->first();

        if ($pending !== null) {
            return Response::deny(
                'Pengajuan ujian proposal Anda tanggal '
                .$pending->created_at->isoFormat('D MMMM YYYY').' masih diproses — '
                .strtolower($pending->awaitingLabel()).'. Tunggu hasilnya sebelum mengajukan lagi.'
            );
        }

        return Response::allow();
    }

    public function verifyAsSupervisor(User $user, ProposalExamRequest $request): bool
    {
        return $user->can('exam.verify')
            && $request->supervisor_status === ApprovalStatus::Pending
            && $request->student->isSupervisedBy($user);
    }

    public function verifyAsAdmin(User $user, ProposalExamRequest $request): bool
    {
        return $user->can('exam.verify')
            && $request->supervisor_status === ApprovalStatus::Approved
            && $request->admin_status === ApprovalStatus::Pending
            && $request->student->isVisibleTo($user);
    }
}
