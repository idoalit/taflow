<?php

namespace App\Http\Resources;

use App\Concerns\FormatsIndonesianDates;
use App\Enums\NotificationCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    use FormatsIndonesianDates;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $category = NotificationCategory::tryFrom($this->data['category'] ?? '')
            ?? NotificationCategory::Document;

        return [
            'id' => $this->id,
            'category' => $category->value,
            'category_label' => $category->label(),
            'tone' => $category->tone(),
            'title' => $this->data['title'] ?? '',
            'message' => $this->data['message'] ?? '',
            'url' => $this->data['url'] ?? null,
            'time' => $this->relative($this->created_at),
            'sent_at' => $this->shortDateTime($this->created_at),
            'unread' => $this->read_at === null,
        ];
    }
}
