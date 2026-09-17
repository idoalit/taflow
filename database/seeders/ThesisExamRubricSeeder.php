<?php

namespace Database\Seeders;

use App\Enums\RubricStatus;
use App\Enums\ScoringScheme;
use App\Models\Department;
use App\Models\Rubric;
use App\Models\RubricCriterion;
use Database\Seeders\Support\ThesisExamRubricBlueprint as Blueprint;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds the faculty's Form Penilaian Ujian Tugas Akhir for a program studi.
 *
 * Re-running is safe: the rubric and its criteria are matched by name and key.
 * A rubric that has already been used to mark an exam is left untouched, since
 * its criteria are part of verdicts that have been given.
 */
class ThesisExamRubricSeeder extends Seeder
{
    public function run(?Department $department = null): void
    {
        $departments = $department !== null
            ? collect([$department])
            : Department::query()->where('is_active', true)->get();

        if (Blueprint::totalMaxScore() !== 100) {
            throw new RuntimeException(
                'Rubrik ujian tugas akhir harus berjumlah 100, saat ini '.Blueprint::totalMaxScore().'.',
            );
        }

        foreach ($departments as $target) {
            $this->seedFor($target);
        }
    }

    private function seedFor(Department $department): void
    {
        $rubric = Rubric::firstOrCreate(
            ['department_id' => $department->id, 'name' => Blueprint::NAME],
            [
                'description' => 'Rubrik resmi ujian tugas akhir, sesuai formulir fakultas.',
                'scoring_scheme' => ScoringScheme::BlockAdditive,
                'status' => RubricStatus::Published,
                'published_at' => now(),
            ],
        );

        if ($rubric->hasBeenUsed()) {
            return;
        }

        $keys = RubricCriterion::keysFor(array_column(Blueprint::criteria(), 'label'));

        foreach (Blueprint::criteria() as $order => $criterion) {
            $rubric->criteria()->updateOrCreate(
                ['key' => $keys[$order]],
                [...$criterion, 'weight' => null, 'order' => $order + 1],
            );
        }
    }
}
