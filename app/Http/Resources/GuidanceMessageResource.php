<?php

namespace App\Http\Resources;

use App\Concerns\FormatsIndonesianDates;
use App\Models\GuidanceMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GuidanceMessage
 */
class GuidanceMessageResource extends JsonResource
{
    use FormatsIndonesianDates;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->message,
            'author_name' => $this->user_id === $request->user()?->id ? 'Anda' : $this->author->name,
            'sent_at' => $this->messageStamp($this->created_at),
            'is_mine' => $this->user_id === $request->user()?->id,
            'attachment' => DocumentResource::fromColumns(
                $this->attachment_path,
                $this->attachment_name,
                null,
                $this->created_at,
            ),
        ];
    }
}
