<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Every in-app notification FiPro raises shares one shape — a category, a
 * headline, a sentence of detail, and optionally somewhere to go. The bell and
 * the Notifikasi page render straight from it.
 */
class ThesisNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly NotificationCategory $category,
        private readonly string $title,
        private readonly string $message,
        private readonly ?string $url = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{category: string, title: string, message: string, url: string|null}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'category' => $this->category->value,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}
