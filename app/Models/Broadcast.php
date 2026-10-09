<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Policies\BroadcastPolicy;
use Carbon\CarbonInterface;
use Database\Factories\BroadcastFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One announcement the superadmin sent, and the audience it went to.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $title
 * @property string $message
 * @property string|null $url
 * @property UserRole|null $audience_role
 * @property int|null $department_id
 * @property int $recipients_count
 * @property CarbonInterface|null $created_at
 * @property-read User|null $sender
 * @property-read Department|null $department
 */
#[Fillable(['user_id', 'title', 'message', 'url', 'audience_role', 'department_id', 'recipients_count'])]
#[UsePolicy(BroadcastPolicy::class)]
class Broadcast extends Model
{
    /** @use HasFactory<BroadcastFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['audience_role' => UserRole::class];
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * "Dosen · Teknik Informatika", "Semua Pengguna" — who this reached, as the
     * history table reads it.
     */
    public function audienceLabel(): string
    {
        $role = $this->audience_role?->label() ?? 'Semua Pengguna';

        return $this->department === null ? $role : $role.' · '.$this->department->name;
    }
}
