<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\GuidanceMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $guidance_session_id
 * @property int $user_id
 * @property string $message
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 * @property CarbonInterface|null $read_at
 */
#[Fillable(['guidance_session_id', 'user_id', 'message', 'attachment_path', 'attachment_name', 'read_at'])]
class GuidanceMessage extends Model
{
    /** @use HasFactory<GuidanceMessageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    /** @return BelongsTo<GuidanceSession, $this> */
    public function guidanceSession(): BelongsTo
    {
        return $this->belongsTo(GuidanceSession::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
