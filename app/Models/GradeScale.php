<?php

namespace App\Models;

use Database\Factories\GradeScaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * One band of a program studi's number-to-letter conversion.
 *
 * @property int $id
 * @property int $department_id
 * @property string $letter
 * @property string $min_score
 * @property string $max_score
 * @property int $order
 */
#[Fillable(['department_id', 'letter', 'min_score', 'max_score', 'order'])]
class GradeScale extends Model
{
    /** @use HasFactory<GradeScaleFactory> */
    use HasFactory;

    /**
     * What most Indonesian campuses use, applied to a program studi that has
     * not defined its own bands.
     *
     * @var array<int, array{letter: string, min: float, max: float}>
     */
    public const DEFAULTS = [
        ['letter' => 'A', 'min' => 85.0, 'max' => 100.0],
        ['letter' => 'AB', 'min' => 80.0, 'max' => 84.99],
        ['letter' => 'B', 'min' => 70.0, 'max' => 79.99],
        ['letter' => 'BC', 'min' => 65.0, 'max' => 69.99],
        ['letter' => 'C', 'min' => 60.0, 'max' => 64.99],
        ['letter' => 'D', 'min' => 50.0, 'max' => 59.99],
        ['letter' => 'E', 'min' => 0.0, 'max' => 49.99],
    ];

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The letter a score earns in this program studi, or null when no band
     * covers it — which is a gap in the configuration, not a pass or a fail.
     */
    public static function letterFor(Department $department, ?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        $bands = static::bandsFor($department);

        foreach ($bands as $band) {
            if ($score >= (float) $band['min'] && $score <= (float) $band['max']) {
                return $band['letter'];
            }
        }

        return null;
    }

    /**
     * The program studi's own bands, falling back to the defaults.
     *
     * @return Collection<int, array{letter: string, min: float, max: float}>
     */
    public static function bandsFor(Department $department): Collection
    {
        $own = static::query()
            ->where('department_id', $department->id)
            ->orderBy('order')
            ->get()
            ->map(fn (self $scale): array => [
                'letter' => $scale->letter,
                'min' => (float) $scale->min_score,
                'max' => (float) $scale->max_score,
            ]);

        return $own->isNotEmpty() ? $own : collect(static::DEFAULTS);
    }
}
