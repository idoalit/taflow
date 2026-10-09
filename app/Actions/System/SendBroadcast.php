<?php

namespace App\Actions\System;

use App\Enums\NotificationCategory;
use App\Enums\UserRole;
use App\Models\Broadcast;
use App\Models\Department;
use App\Models\User;
use App\Notifications\ThesisNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Puts one announcement in the inbox of everybody in an audience, and keeps a
 * record that it was sent.
 *
 * An audience is a role, a program studi, both, or neither. Membership of a
 * prodi means something different for each role — a mahasiswa belongs to one,
 * a dosen teaches in several, an admin runs several — so the three are spelled
 * out rather than guessed from a single column.
 */
final readonly class SendBroadcast
{
    /** The roles a broadcast can be addressed to; a superadmin is not an audience. */
    public const AUDIENCE_ROLES = [UserRole::Student, UserRole::Lecturer, UserRole::Admin];

    private const CHUNK = 500;

    /**
     * @param  array{title: string, message: string, url?: string|null}  $content
     */
    public function handle(User $sender, array $content, ?UserRole $role, ?Department $department): Broadcast
    {
        $notification = new ThesisNotification(
            NotificationCategory::Announcement,
            $content['title'],
            $content['message'],
            $content['url'] ?? null,
        );

        $sent = 0;

        $this->recipients($sender, $role, $department)->chunkById(
            self::CHUNK,
            function (Collection $users) use ($notification, &$sent): void {
                Notification::send($users, $notification);
                $sent += $users->count();
            },
        );

        return Broadcast::create([
            'user_id' => $sender->id,
            'title' => $content['title'],
            'message' => $content['message'],
            'url' => $content['url'] ?? null,
            'audience_role' => $role,
            'department_id' => $department?->id,
            'recipients_count' => $sent,
        ]);
    }

    /**
     * Everybody the audience names, except whoever is sending.
     *
     * @return Builder<User>
     */
    public function recipients(User $sender, ?UserRole $role, ?Department $department): Builder
    {
        $query = User::query()->whereKeyNot($sender->id);

        // No narrowing at all means every account that can sign in to anything,
        // fellow superadmins included.
        if ($role === null && $department === null) {
            return $query->whereHas('roles');
        }

        $roles = $role === null ? self::AUDIENCE_ROLES : [$role];

        return $query->where(function (Builder $query) use ($roles, $department): void {
            foreach ($roles as $audience) {
                $query->orWhere(fn (Builder $query) => $this->constrain($query, $audience, $department));
            }
        });
    }

    /**
     * @param  Builder<User>  $query
     */
    private function constrain(Builder $query, UserRole $role, ?Department $department): void
    {
        match ($role) {
            UserRole::Student => $query->whereHas('student', fn (Builder $student) => $student
                ->when($department, fn (Builder $scoped, Department $in) => $scoped->where('department_id', $in->id))),
            UserRole::Lecturer => $query->whereHas('lecturer', fn (Builder $lecturer) => $lecturer
                ->when($department, fn (Builder $scoped, Department $in) => $scoped
                    ->whereHas('departments', fn (Builder $departments) => $departments->whereKey($in->id)))),
            UserRole::Admin => $query
                ->whereHas('roles', fn (Builder $roles) => $roles->where('name', UserRole::Admin->value))
                ->when($department, fn (Builder $scoped, Department $in) => $scoped
                    ->whereHas('departments', fn (Builder $departments) => $departments->whereKey($in->id))),
            UserRole::SuperAdmin => $query
                ->whereHas('roles', fn (Builder $roles) => $roles->where('name', UserRole::SuperAdmin->value)),
        };
    }
}
