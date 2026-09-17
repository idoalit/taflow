<?php

namespace App\Models;

use App\Enums\GuidanceDisplayStatus;
use App\Enums\GuidanceStage;
use App\Enums\GuidanceStatus;
use App\Enums\GuidanceTargetType;
use App\Enums\GuidanceType;
use App\Enums\MeetingType;
use Carbon\CarbonInterface;
use Database\Factories\GuidanceSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $student_id
 * @property int $created_by
 * @property string $title
 * @property GuidanceStage $stage
 * @property GuidanceTargetType $target_type
 * @property GuidanceType $guidance_type
 * @property MeetingType|null $meeting_type
 * @property string|null $meeting_link
 * @property string|null $meeting_location
 * @property CarbonInterface|null $meeting_scheduled_at
 * @property string|null $document_path
 * @property string|null $document_name
 * @property GuidanceStatus $status
 * @property string|null $notes
 * @property string|null $feedback
 * @property string|null $completion_notes
 * @property string|null $cancellation_reason
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $cancelled_at
 */
#[Fillable([
    'student_id', 'created_by', 'title', 'stage', 'target_type', 'guidance_type',
    'meeting_type', 'meeting_link', 'meeting_location', 'meeting_scheduled_at',
    'document_path', 'document_name', 'status', 'notes', 'feedback',
    'completion_notes', 'cancellation_reason', 'completed_at', 'cancelled_at',
])]
class GuidanceSession extends Model
{
    /** @use HasFactory<GuidanceSessionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => GuidanceStage::class,
            'target_type' => GuidanceTargetType::class,
            'guidance_type' => GuidanceType::class,
            'meeting_type' => MeetingType::class,
            'status' => GuidanceStatus::class,
            'meeting_scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<GuidanceMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(GuidanceMessage::class)->oldest();
    }

    public function isAsync(): bool
    {
        return $this->guidance_type === GuidanceType::OnlineAsync;
    }

    public function isOngoing(): bool
    {
        return $this->status === GuidanceStatus::Ongoing;
    }

    /**
     * The supervisors this session actually involves, which is not the same as
     * the student's whole supervisory team: a session targeted at "Pembimbing
     * Utama" only ever involves the first supervisor, while "Semua Pembimbing"
     * involves everyone assigned.
     *
     * @return Collection<int, Lecturer>
     */
    public function participatingSupervisors(): Collection
    {
        $supervisors = $this->student->supervisors;

        return $this->target_type === GuidanceTargetType::Both
            ? $supervisors
            : $supervisors->take(1)->values();
    }

    /**
     * A face-to-face session is only "Berlangsung" once its slot has arrived;
     * before that it is merely booked. An async discussion has no slot, so it
     * is live from the moment it opens.
     */
    public function displayStatus(): GuidanceDisplayStatus
    {
        return match ($this->status) {
            GuidanceStatus::Cancelled => GuidanceDisplayStatus::Cancelled,
            GuidanceStatus::Completed => GuidanceDisplayStatus::Completed,
            GuidanceStatus::Ongoing => $this->isAsync() || $this->meeting_scheduled_at === null
                || $this->meeting_scheduled_at->isPast()
                    ? GuidanceDisplayStatus::Ongoing
                    : GuidanceDisplayStatus::Scheduled,
        };
    }

    /**
     * Narrow a query to one of the derived states, so the worklist tabs can be
     * counted and paged in the database rather than in PHP.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeDisplayStatus(Builder $query, GuidanceDisplayStatus $status): Builder
    {
        return match ($status) {
            GuidanceDisplayStatus::Cancelled => $query->where('status', GuidanceStatus::Cancelled),
            GuidanceDisplayStatus::Completed => $query->where('status', GuidanceStatus::Completed),
            GuidanceDisplayStatus::Ongoing => $query
                ->where('status', GuidanceStatus::Ongoing)
                ->where(fn (Builder $live) => $live
                    ->where('guidance_type', GuidanceType::OnlineAsync)
                    ->orWhereNull('meeting_scheduled_at')
                    ->orWhere('meeting_scheduled_at', '<=', now())),
            GuidanceDisplayStatus::Scheduled => $query
                ->where('status', GuidanceStatus::Ongoing)
                ->where('guidance_type', GuidanceType::FaceToFace)
                ->where('meeting_scheduled_at', '>', now()),
        };
    }
}
