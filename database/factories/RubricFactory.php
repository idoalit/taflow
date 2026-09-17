<?php

namespace Database\Factories;

use App\Enums\RubricStatus;
use App\Enums\ScoringScheme;
use App\Models\Department;
use App\Models\Rubric;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rubric>
 */
class RubricFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'name' => 'Rubrik Ujian '.fake()->word(),
            'description' => fake()->sentence(),
            'scoring_scheme' => ScoringScheme::Weighted,
            'status' => RubricStatus::Draft,
        ];
    }

    /**
     * A published rubric with the design's four criteria, weights totalling 100.
     */
    public function published(int $maxScore = 100): self
    {
        return $this->state(fn (): array => [
            'status' => RubricStatus::Published,
            'published_at' => now(),
        ])->afterCreating(function (Rubric $rubric) use ($maxScore): void {
            if ($rubric->criteria()->exists()) {
                return;
            }

            $criteria = [
                ['Kualitas Proposal', 'kualitas_proposal', 30],
                ['Metodologi', 'metodologi', 30],
                ['Presentasi', 'presentasi', 20],
                ['Penguasaan Materi', 'penguasaan_materi', 20],
            ];

            foreach ($criteria as $order => [$label, $key, $weight]) {
                $rubric->criteria()->create([
                    'label' => $label,
                    'key' => $key,
                    'weight' => $weight,
                    'max_score' => $maxScore,
                    'order' => $order + 1,
                ]);
            }
        });
    }
}
