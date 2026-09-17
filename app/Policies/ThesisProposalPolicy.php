<?php

namespace App\Policies;

use App\Enums\ProposalStatus;
use App\Enums\UserRole;
use App\Models\ThesisProposal;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ThesisProposalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('proposal.view');
    }

    public function view(User $user, ThesisProposal $proposal): bool
    {
        return $user->can('proposal.view') && $proposal->student->isVisibleTo($user);
    }

    /**
     * A student may only have one proposal in flight at a time. The denial
     * explains which of those conditions failed, so a user who lands here from
     * a stale link is told what to do rather than just refused.
     */
    public function create(User $user): Response
    {
        if (! $user->can('proposal.create')) {
            return Response::deny('Hanya mahasiswa yang dapat mengajukan proposal tugas akhir.');
        }

        if ($user->student === null) {
            return Response::deny('Akun Anda belum terhubung dengan data mahasiswa. Hubungi admin program studi.');
        }

        $active = $user->student->activeProposal();

        if ($active !== null) {
            return Response::deny(
                "Anda sudah memiliki proposal aktif berjudul \"{$active->title}\" dengan status "
                .strtolower($active->status->label()).'. Setiap mahasiswa hanya boleh memiliki satu proposal '
                .'aktif, jadi lanjutkan proposal tersebut atau tunggu hasilnya sebelum mengajukan yang baru.'
            );
        }

        return Response::allow();
    }

    public function update(User $user, ThesisProposal $proposal): bool
    {
        return $user->student?->id === $proposal->student_id
            && in_array($proposal->status, [ProposalStatus::Submitted, ProposalStatus::NeedsRevision], true);
    }

    public function review(User $user, ThesisProposal $proposal): bool
    {
        return $user->can('proposal.review') && $proposal->student->isSupervisedBy($user);
    }

    public function delete(User $user, ThesisProposal $proposal): bool
    {
        return $user->primaryRole() === UserRole::SuperAdmin;
    }
}
