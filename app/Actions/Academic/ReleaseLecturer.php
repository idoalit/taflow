<?php

namespace App\Actions\Academic;

use App\Enums\AuditAction;
use App\Enums\NotificationCategory;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Lecturer;
use App\Models\User;
use App\Notifications\ThesisNotification;
use Illuminate\Http\Request;

/**
 * Takes a dosen out of a program studi they were added to. Whether that is
 * allowed is the policy's question; this only does it, and leaves a record.
 */
final readonly class ReleaseLecturer
{
    public function handle(Lecturer $lecturer, Department $department, User $actor, ?Request $request = null): void
    {
        $lecturer->departments()->detach($department->id);
        $lecturer->loadMissing('user');

        AuditLog::record(
            $lecturer->user,
            AuditAction::LecturerReleased,
            $actor,
            changes: [
                'name' => $lecturer->user->name,
                'lecturer_number' => $lecturer->lecturer_number,
                'department' => $department->name,
            ],
            request: $request,
        );

        $lecturer->user->notify(new ThesisNotification(
            NotificationCategory::Announcement,
            "Anda tidak lagi tercatat di {$department->name}",
            "{$actor->name} melepas Anda dari daftar dosen {$department->name}. "
            .'Workspace program studi itu tidak lagi tersedia bagi Anda.',
        ));
    }
}
