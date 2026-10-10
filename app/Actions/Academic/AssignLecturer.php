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
use Illuminate\Support\Facades\Notification;

/**
 * Puts a dosen who already exists into one more program studi, to supervise
 * or examine there. Their homebase stays where it was.
 *
 * A prodi may do this on its own word, so it is never done quietly: the dosen
 * and the admins of their homebase are told, and the audit trail keeps who
 * did it.
 */
final readonly class AssignLecturer
{
    /**
     * @return bool False when the dosen was a member already and nothing changed.
     */
    public function handle(Lecturer $lecturer, Department $department, User $actor, ?Request $request = null): bool
    {
        if ($lecturer->departments()->whereKey($department->id)->exists()) {
            return false;
        }

        $lecturer->departments()->attach($department->id, ['is_primary' => false]);
        $lecturer->load(['user', 'departments']);

        $homebase = $lecturer->homebase();

        AuditLog::record(
            $lecturer->user,
            AuditAction::LecturerAssigned,
            $actor,
            changes: [
                'name' => $lecturer->user->name,
                'lecturer_number' => $lecturer->lecturer_number,
                'department' => $department->name,
                'homebase' => $homebase?->name,
            ],
            request: $request,
        );

        $lecturer->user->notify(new ThesisNotification(
            NotificationCategory::Announcement,
            "Anda ditambahkan ke {$department->name}",
            "{$actor->name} menambahkan Anda sebagai dosen di {$department->name}. "
            .'Program studi itu kini tersedia di pemilih workspace Anda.',
        ));

        if ($homebase !== null) {
            Notification::send(
                $homebase->admins()->whereKeyNot($actor->id)->get(),
                new ThesisNotification(
                    NotificationCategory::Announcement,
                    "{$lecturer->user->name} ditambahkan ke {$department->name}",
                    "{$actor->name} menambahkan {$lecturer->user->name} (NIP {$lecturer->lecturer_number}), "
                    ."dosen homebase {$homebase->name}, sebagai dosen di {$department->name}.",
                ),
            );
        }

        return true;
    }
}
