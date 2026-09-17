<?php

namespace App\Models;

use Database\Factories\SupervisionIndicatorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a program studi's supervision form.
 *
 * @property int $id
 * @property int $department_id
 * @property string $code
 * @property string $label
 * @property int $max_score
 * @property int $order
 * @property bool $is_active
 */
#[Fillable(['department_id', 'code', 'label', 'max_score', 'order', 'is_active'])]
class SupervisionIndicator extends Model
{
    /** @use HasFactory<SupervisionIndicatorFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return Collection<int, static>
     */
    public static function activeFor(Department $department): Collection
    {
        return static::query()
            ->where('department_id', $department->id)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    }

    /**
     * The word the printed form puts beside a mark.
     *
     * The faculty's form reads Baik 14-20, Cukup 7-13, Kurang 0-6 out of 20.
     * Expressed as proportions so a program studi marking out of something
     * else still gets the same three bands rather than nonsense.
     */
    public static function descriptorFor(float $score, int $maxScore): string
    {
        if ($maxScore <= 0) {
            return '—';
        }

        $ratio = $score / $maxScore;

        return match (true) {
            $ratio >= 0.7 => 'Baik',
            $ratio >= 0.35 => 'Cukup',
            default => 'Kurang',
        };
    }
}
