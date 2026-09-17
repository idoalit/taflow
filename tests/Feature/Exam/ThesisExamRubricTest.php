<?php

use App\Enums\RubricStatus;
use App\Enums\ScoringScheme;
use App\Models\ExamEvaluation;
use App\Models\GradeScale;
use App\Models\Rubric;
use Database\Seeders\Support\ThesisExamRubricBlueprint as Blueprint;
use Database\Seeders\ThesisExamRubricSeeder;

beforeEach(function (): void {
    seedRoles();
});

it('encodes the faculty form with the exact maxima it prescribes', function (): void {
    $byBlock = collect(Blueprint::criteria())->groupBy('block_code')->map->sum('max_score');
    $subB = collect(Blueprint::criteria())
        ->where('block_code', 'B')
        ->groupBy('subblock_code')
        ->map->sum('max_score');

    expect(Blueprint::totalMaxScore())->toBe(100)
        ->and($byBlock->all())->toBe(['A' => 15, 'B' => 40, 'C' => 15, 'D' => 30])
        ->and($subB->all())->toBe(['I' => 8, 'II' => 15, 'III' => 13, 'IV' => 4])
        ->and(Blueprint::criteria())->toHaveCount(28);
});

it('seeds the rubric for a program studi, and does so idempotently', function (): void {
    $department = makeDepartment();

    app(ThesisExamRubricSeeder::class)->run($department);
    app(ThesisExamRubricSeeder::class)->run($department);

    $rubric = Rubric::where('department_id', $department->id)->sole()->load('criteria');

    expect(Rubric::count())->toBe(1)
        ->and($rubric->criteria)->toHaveCount(28)
        ->and($rubric->scoring_scheme)->toBe(ScoringScheme::BlockAdditive)
        ->and($rubric->status)->toBe(RubricStatus::Published)
        ->and($rubric->isBalanced())->toBeTrue();
});

it('groups the criteria into blocks and sub-blocks with their own maxima', function (): void {
    $department = makeDepartment();
    app(ThesisExamRubricSeeder::class)->run($department);

    $blocks = Rubric::sole()->load('criteria')->blocks();
    $b = $blocks->firstWhere('code', 'B');

    expect($blocks->pluck('max', 'code')->all())->toBe(['A' => 15, 'B' => 40, 'C' => 15, 'D' => 30])
        ->and($b['label'])->toBe('ISI KARYA TULIS / TUGAS AKHIR')
        ->and($b['subblocks']->pluck('max', 'code')->all())
        ->toBe(['I' => 8, 'II' => 15, 'III' => 13, 'IV' => 4]);
});

it('sums a block-additive verdict instead of weighting it', function (): void {
    $department = makeDepartment();
    app(ThesisExamRubricSeeder::class)->run($department);
    $rubric = Rubric::sole()->load('criteria');

    $full = $rubric->criteria->mapWithKeys(fn ($c): array => [$c->key => $c->max_score])->all();
    $half = $rubric->criteria->mapWithKeys(fn ($c): array => [$c->key => 0])->all();

    expect(ExamEvaluation::scoreFor($rubric, $full))->toBe(100.0)
        ->and(ExamEvaluation::scoreFor($rubric, $half))->toBe(0.0);
});

it('reports each block subtotal beside its maximum', function (): void {
    $department = makeDepartment();
    app(ThesisExamRubricSeeder::class)->run($department);
    $rubric = Rubric::sole()->load('criteria');

    // Only block A marked, and only to half of each item.
    $marks = $rubric->criteria
        ->mapWithKeys(fn ($c): array => [$c->key => $c->block_code === 'A' ? 1 : 0])
        ->all();

    $subtotals = collect(ExamEvaluation::blockSubtotals($rubric, $marks));

    expect($subtotals->pluck('earned', 'code')->all())
        ->toBe(['A' => 5.0, 'B' => 0.0, 'C' => 0.0, 'D' => 0.0])
        ->and($subtotals->pluck('max', 'code')->all())
        ->toBe(['A' => 15, 'B' => 40, 'C' => 15, 'D' => 30]);
});

it('keeps the weighted scheme working alongside it', function (): void {
    $department = makeDepartment();
    $rubric = Rubric::factory()->for($department)->published()->create();
    $rubric->load('criteria');

    $marks = $rubric->criteria->mapWithKeys(fn ($c): array => [$c->key => 80])->all();

    expect($rubric->scoring_scheme)->toBe(ScoringScheme::Weighted)
        ->and(ExamEvaluation::scoreFor($rubric, $marks))->toBe(80.0);
});

it('converts a score to the program studi\'s own letter', function (): void {
    $department = makeDepartment();

    // Falls back to the common Indonesian bands until the prodi defines its own.
    expect(GradeScale::letterFor($department, 90))->toBe('A')
        ->and(GradeScale::letterFor($department, 83.3))->toBe('AB')
        ->and(GradeScale::letterFor($department, 45))->toBe('E')
        ->and(GradeScale::letterFor($department, null))->toBeNull();

    GradeScale::factory()->create([
        'department_id' => $department->id, 'letter' => 'A', 'min_score' => 90, 'max_score' => 100,
    ]);
    GradeScale::factory()->create([
        'department_id' => $department->id, 'letter' => 'B', 'min_score' => 0, 'max_score' => 89.99, 'order' => 2,
    ]);

    expect(GradeScale::letterFor($department->fresh(), 90))->toBe('A')
        ->and(GradeScale::letterFor($department->fresh(), 89))->toBe('B');
});
