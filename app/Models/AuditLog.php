<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

/**
 * Who changed what, and why.
 *
 * @property int $id
 * @property string $auditable_type
 * @property int $auditable_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $reason
 * @property array<string, mixed>|null $changes
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonInterface $created_at
 */
#[Fillable([
    'auditable_type', 'auditable_id', 'user_id', 'action',
    'reason', 'changes', 'ip_address', 'user_agent',
])]
class AuditLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    /** @return MorphTo<Model, $this> */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record one change against a model, with the request that made it.
     *
     * @param  array<string, mixed>  $changes
     */
    public static function record(
        Model $subject,
        string $action,
        ?User $user,
        ?string $reason = null,
        array $changes = [],
        ?Request $request = null,
    ): self {
        return static::create([
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->getKey(),
            'user_id' => $user?->id,
            'action' => $action,
            'reason' => $reason,
            'changes' => $changes === [] ? null : $changes,
            'ip_address' => $request?->ip(),
            'user_agent' => $request === null ? null : substr((string) $request->userAgent(), 0, 500),
        ]);
    }
}
