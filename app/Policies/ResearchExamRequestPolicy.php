<?php

namespace App\Policies;

use App\Enums\ApprovalStatus;
use App\Models\ResearchExamRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ResearchExamRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('exam.view');
    }

    public function view(User $user, ResearchExamRequest $request): bool
    {
        return $user->can('exam.view') && $request->student->isVisibleTo($user);
    }

    /**
     * Students submit their own requests, and only one may be outstanding.
     */
    public function create(User $user): Response
    {
        if (! $user->can('exam.request') || $user->student === null) {
            return Response::deny('Hanya mahasiswa yang dapat mengajukan ujian hasil penelitian.');
        }

        $pending = $user->student->researchExamRequests()
            ->where('status', ApprovalStatus::Pending)
            ->first();

        if ($pending !== null) {
            return Response::deny(
                'Pengajuan ujian hasil penelitian Anda tanggal '
                .$pending->created_at->isoFormat('D MMMM YYYY').' masih diproses — '
                .strtolower($pending->awaitingLabel()).'. Tunggu hasilnya sebelum mengajukan lagi.'
            );
        }

        return Response::allow();
    }

    public function verifyAsSupervisor(User $user, ResearchExamRequest $request): bool
    {
        return $user->can('exam.verify')
            && $request->supervisor_status === ApprovalStatus::Pending
            && $request->student->isSupervisedBy($user);
    }

    public function verifyAsAdmin(User $user, ResearchExamRequest $request): bool
    {
        return $user->can('exam.verify')
            && $request->supervisor_status === ApprovalStatus::Approved
            && $request->admin_status === ApprovalStatus::Pending
            && $request->student->isVisibleTo($user);
    }
}
