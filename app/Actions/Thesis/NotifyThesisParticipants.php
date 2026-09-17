<?php

namespace App\Actions\Thesis;

use App\Enums\NotificationCategory;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ThesisNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Raises in-app notifications for the people on either side of a thesis: the
 * student, or the supervisors assigned to them. Keeps the recipient lookup out
 * of the controllers.
 */
final readonly class NotifyThesisParticipants
{
    public function student(
        Student $student,
        NotificationCategory $category,
        string $title,
        string $message,
        ?string $url = null,
    ): void {
        $student->user->notify(new ThesisNotification($category, $title, $message, $url));
    }

    /**
     * Every supervisor assigned to the student, optionally skipping whoever
     * triggered the event so nobody is told about their own action.
     */
    public function supervisors(
        Student $student,
        NotificationCategory $category,
        string $title,
        string $message,
        ?string $url = null,
        ?User $except = null,
    ): void {
        $recipients = $student->supervisors
            ->map(fn ($lecturer): ?User => $lecturer->user)
            ->filter()
            ->reject(fn (User $user): bool => $except !== null && $user->is($except));

        Notification::send($recipients, new ThesisNotification($category, $title, $message, $url));
    }

    /**
     * The counterpart in a guidance conversation: the supervisors when the
     * student speaks, the student when a supervisor does.
     */
    public function counterpart(
        Student $student,
        User $actor,
        NotificationCategory $category,
        string $title,
        string $message,
        ?string $url = null,
    ): void {
        if ($student->user_id === $actor->id) {
            $this->supervisors($student, $category, $title, $message, $url, $actor);

            return;
        }

        $this->student($student, $category, $title, $message, $url);
    }
}
