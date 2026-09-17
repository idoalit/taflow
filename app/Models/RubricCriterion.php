<?php

namespace App\Models;

use Database\Factories\RubricCriterionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $rubric_id
 * @property string $label
 * @property string $key
 * @property string|null $block_code
 * @property string|null $block_label
 * @property string|null $subblock_code
 * @property string|null $subblock_label
 * @property int|null $item_number
 * @property int|null $weight
 * @property int $max_score
 * @property int $order
 */
#[Fillable([
    'rubric_id', 'block_code', 'block_label', 'subblock_code', 'subblock_label',
    'item_number', 'label', 'key', 'weight', 'max_score', 'order',
])]
class RubricCriterion extends Model
{
    /** @use HasFactory<RubricCriterionFactory> */
    use HasFactory;

    /** @return BelongsTo<Rubric, $this> */
    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class);
    }

    /**
     * The letter a block wears, from its position: A, B, C … then AA, AB.
     *
     * Positional rather than typed, for the same reason keys are: a code is a
     * label the system owes the reader, not a field a person should have to
     * keep consistent while reordering.
     */
    public static function blockCodeFor(int $index): string
    {
        $code = '';

        for ($n = $index; $n >= 0; $n = intdiv($n, 26) - 1) {
            $code = chr(65 + $n % 26).$code;
        }

        return $code;
    }

    /**
     * The numeral a sub-block wears, from its position: I, II, III, IV …
     */
    public static function subBlockCodeFor(int $index): string
    {
        $numerals = [
            1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
            100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
            10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I',
        ];

        $remaining = $index + 1;
        $code = '';

        foreach ($numerals as $value => $numeral) {
            while ($remaining >= $value) {
                $code .= $numeral;
                $remaining -= $value;
            }
        }

        return $code;
    }

    /**
     * Derive one key per label, unique within the set.
     *
     * The key is a machine identifier — it names the column a verdict is filed
     * under — so it is generated rather than typed. Two criteria whose labels
     * reduce to the same slug ("Metodologi" and "Metodologi!") would otherwise
     * collide, and a rubric can no longer be edited once it has been used to
     * mark an exam, which makes such a collision expensive to discover late.
     *
     * @param  array<int, string>  $labels
     * @return array<int, string>
     */
    public static function keysFor(array $labels): array
    {
        $keys = [];

        foreach (array_values($labels) as $index => $label) {
            $base = Str::of($label)
                ->ascii()
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', '_')
                ->trim('_')
                ->limit(50, '')
                ->toString();

            // A label of punctuation or non-latin script leaves nothing usable,
            // and a key has to start with a letter.
            if ($base === '' || preg_match('/^[a-z]/', $base) !== 1) {
                $base = 'kriteria_'.($index + 1);
            }

            $key = $base;
            $suffix = 2;

            while (in_array($key, $keys, true)) {
                $key = $base.'_'.$suffix++;
            }

            $keys[] = $key;
        }

        return $keys;
    }
}
