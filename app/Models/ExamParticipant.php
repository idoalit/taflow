<?php

namespace App\Models;

use App\Enums\ExamParticipantRole;
use Carbon\CarbonInterface;
use Database\Factories\ExamParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One seat at one exam sitting.
 *
 * @property int $id
 * @property string $exam_type
 * @property int $exam_id
 * @property int $user_id
 * @property int|null $lecturer_id
 * @property ExamParticipantRole $role
 * @property bool $attended
 * @property CarbonInterface|null $attended_at
 * @property CarbonInterface|null $signed_at
 * @property int|null $signature_id
 * @property-read User $user
 */
#[Fillable([
    'exam_type', 'exam_id', 'user_id', 'lecturer_id', 'role',
    'attended', 'attended_at', 'signed_at', 'signature_id',
])]
class ExamParticipant extends Model
{
    /** @use HasFactory<ExamParticipantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ExamParticipantRole::class,
            'attended' => 'boolean',
            'attended_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function exam(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Lecturer, $this> */
    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    /** @return BelongsTo<Signature, $this> */
    public function signature(): BelongsTo
    {
        return $this->belongsTo(Signature::class);
    }

    public function hasSigned(): bool
    {
        return $this->signed_at !== null;
    }

    /**
     * The title printed beside a signature: the seat, numbered when a panel has
     * more than one member in it.
     */
    public function positionLabel(int $ordinal = 0): string
    {
        return $this->role === ExamParticipantRole::MemberExaminer && $ordinal > 0
            ? "Anggota Penguji {$ordinal}"
            : $this->role->label();
    }
}
