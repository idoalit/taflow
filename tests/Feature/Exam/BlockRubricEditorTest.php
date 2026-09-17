<?php

use App\Enums\ScoringScheme;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Rubric;
use App\Models\User;
use App\Support\Workspace;
use Database\Seeders\ThesisExamRubricSeeder;

beforeEach(function (): void {
    seedRoles();
});

/**
 * @return array{0: User, 1: Department}
 */
function rubricAdmin(): array
{
    $department = makeDepartment();
    $admin = makeAdmin($department);
    $admin->useWorkspace(new Workspace(UserRole::Admin, $department));

    return [$admin, $department];
}

/**
 * A two-block rubric whose maxima add up to 100.
 *
 * @return array<int, array<string, mixed>>
 */
function blockPayload(int $firstItemMax = 15): array
{
    return [
        [
            'label' => 'TATA PENULISAN',
            'subblocks' => [[
                'label' => '',
                'items' => [['label' => 'Sistematika penulisan', 'max_score' => $firstItemMax]],
            ]],
        ],
        [
            'label' => 'ISI KARYA TULIS',
            'subblocks' => [
                [
                    'label' => 'Pendahuluan',
                    'items' => [
                        ['label' => 'Intisari', 'max_score' => 40],
                        ['label' => 'Latar belakang', 'max_score' => 25],
                    ],
                ],
                [
                    'label' => 'Analisis',
                    'items' => [['label' => 'Ketepatan metode', 'max_score' => 20]],
                ],
            ],
        ],
    ];
}

it('creates a block rubric from the nested form', function (): void {
    [$admin] = rubricAdmin();

    $this->actingAs($admin)
        ->post(route('admin.rubrics.store'), [
            'name' => 'Rubrik Prodi Sendiri',
            'scoring_scheme' => ScoringScheme::BlockAdditive->value,
            'blocks' => blockPayload(),
        ])
        ->assertRedirect();

    $rubric = Rubric::sole()->load('criteria');

    expect($rubric->scoring_scheme)->toBe(ScoringScheme::BlockAdditive)
        ->and($rubric->criteria)->toHaveCount(4)
        ->and($rubric->totalMaxScore())->toBe(100)
        ->and($rubric->isBalanced())->toBeTrue()
        // Weight means nothing under this scheme and must not be invented.
        ->and($rubric->criteria->pluck('weight')->unique()->all())->toBe([null])
        ->and($rubric->blocks()->pluck('max', 'code')->all())->toBe(['A' => 15, 'B' => 85]);

    $b = $rubric->blocks()->firstWhere('code', 'B');

    expect($b['subblocks']->pluck('max', 'code')->all())->toBe(['I' => 65, 'II' => 20])
        // Items are numbered within their own sub-block, as the form prints them.
        ->and($b['subblocks']->firstWhere('code', 'I')['items']->pluck('item_number')->all())
        ->toBe([1, 2]);
});

it('refuses a block rubric whose maxima do not reach 100', function (): void {
    [$admin] = rubricAdmin();

    $this->actingAs($admin)
        ->post(route('admin.rubrics.store'), [
            'name' => 'Rubrik Timpang',
            'scoring_scheme' => ScoringScheme::BlockAdditive->value,
            'blocks' => blockPayload(firstItemMax: 10),
        ])
        ->assertSessionHasErrors('blocks');

    expect(Rubric::count())->toBe(0);
});

it('lets one program studi differ from another', function (): void {
    [$admin, $department] = rubricAdmin();
    $other = makeDepartment();

    app(ThesisExamRubricSeeder::class)->run($other);

    $this->actingAs($admin)->post(route('admin.rubrics.store'), [
        'name' => 'Rubrik Prodi Sendiri',
        'scoring_scheme' => ScoringScheme::BlockAdditive->value,
        'blocks' => blockPayload(),
    ]);

    $mine = Rubric::where('department_id', $department->id)->sole()->load('criteria');
    $theirs = Rubric::where('department_id', $other->id)->sole()->load('criteria');

    // Same scheme, entirely different structure — which is the point.
    expect($mine->criteria)->toHaveCount(4)
        ->and($theirs->criteria)->toHaveCount(28)
        ->and($mine->blocks()->pluck('code')->all())->toBe(['A', 'B'])
        ->and($theirs->blocks()->pluck('code')->all())->toBe(['A', 'B', 'C', 'D'])
        ->and($mine->totalMaxScore())->toBe(100)
        ->and($theirs->totalMaxScore())->toBe(100);
});

it('edits a block rubric and keeps its nesting', function (): void {
    [$admin] = rubricAdmin();

    $this->actingAs($admin)->post(route('admin.rubrics.store'), [
        'name' => 'Rubrik Awal',
        'scoring_scheme' => ScoringScheme::BlockAdditive->value,
        'blocks' => blockPayload(),
    ]);

    $rubric = Rubric::sole();

    $this->actingAs($admin)
        ->get(route('admin.rubrics.edit', $rubric))
        ->assertInertia(fn ($page) => $page
            ->where('rubric.scoring_scheme', 'block_additive')
            ->has('rubric.blocks', 2)
            // The editor holds labels and nesting; codes follow position and
            // are regenerated on save, so they are not round-tripped.
            ->where('rubric.blocks.1.subblocks.0.label', 'Pendahuluan')
            ->has('rubric.blocks.1.subblocks.0.items', 2));

    $this->actingAs($admin)
        ->put(route('admin.rubrics.update', $rubric), [
            'name' => 'Rubrik Diubah',
            'scoring_scheme' => ScoringScheme::BlockAdditive->value,
            'blocks' => [[
                'label' => 'SATU BLOK SAJA',
                'subblocks' => [[
                    'label' => '',
                    'items' => [['label' => 'Seluruhnya', 'max_score' => 100]],
                ]],
            ]],
        ])
        ->assertRedirect();

    $rubric->refresh()->load('criteria');

    expect($rubric->name)->toBe('Rubrik Diubah')
        ->and($rubric->criteria)->toHaveCount(1)
        ->and($rubric->blocks()->pluck('code')->all())->toBe(['A']);
});

it('can switch a draft between the two schemes', function (): void {
    [$admin] = rubricAdmin();

    $this->actingAs($admin)->post(route('admin.rubrics.store'), [
        'name' => 'Mula-mula Berbobot',
        'scoring_scheme' => ScoringScheme::Weighted->value,
        'criteria' => [
            ['label' => 'Metodologi', 'weight' => 60, 'max_score' => 10],
            ['label' => 'Presentasi', 'weight' => 40, 'max_score' => 5],
        ],
    ]);

    $rubric = Rubric::sole();
    expect($rubric->scoring_scheme)->toBe(ScoringScheme::Weighted);

    $this->actingAs($admin)
        ->put(route('admin.rubrics.update', $rubric), [
            'name' => 'Kini Aditif',
            'scoring_scheme' => ScoringScheme::BlockAdditive->value,
            'blocks' => blockPayload(),
        ])
        ->assertRedirect();

    $rubric->refresh()->load('criteria');

    expect($rubric->scoring_scheme)->toBe(ScoringScheme::BlockAdditive)
        ->and($rubric->criteria->pluck('weight')->unique()->all())->toBe([null])
        ->and($rubric->totalMaxScore())->toBe(100);
});

it('derives block and sub-block codes from their position', function (): void {
    [$admin] = rubricAdmin();

    $this->actingAs($admin)->post(route('admin.rubrics.store'), [
        'name' => 'Rubrik Urutan',
        'scoring_scheme' => ScoringScheme::BlockAdditive->value,
        // Whatever a client sends as a code is ignored.
        'blocks' => [
            [
                'code' => 'ZZ',
                'label' => 'PERTAMA',
                'subblocks' => [[
                    'code' => 'XX',
                    'label' => 'Satu',
                    'items' => [['label' => 'Item satu', 'max_score' => 30]],
                ], [
                    'label' => 'Dua',
                    'items' => [['label' => 'Item dua', 'max_score' => 20]],
                ]],
            ],
            [
                'label' => 'KEDUA',
                'subblocks' => [[
                    'label' => '',
                    'items' => [['label' => 'Item tiga', 'max_score' => 50]],
                ]],
            ],
        ],
    ])->assertRedirect();

    $rubric = Rubric::sole()->load('criteria');

    expect($rubric->blocks()->pluck('code')->all())->toBe(['A', 'B'])
        ->and($rubric->blocks()->firstWhere('code', 'A')['subblocks']->pluck('code')->all())
        ->toBe(['I', 'II'])
        // A block with a single unnamed group prints no sub-heading at all.
        ->and($rubric->blocks()->firstWhere('code', 'B')['subblocks']->pluck('code')->all())
        ->toBe([null]);
});

it('renumbers the codes when blocks are reordered', function (): void {
    [$admin] = rubricAdmin();

    $payload = fn (string $first, string $second): array => [
        'name' => 'Rubrik Urutan',
        'scoring_scheme' => ScoringScheme::BlockAdditive->value,
        'blocks' => [
            [
                'label' => $first,
                'subblocks' => [[
                    'label' => '',
                    'items' => [['label' => "Item {$first}", 'max_score' => 40]],
                ]],
            ],
            [
                'label' => $second,
                'subblocks' => [[
                    'label' => '',
                    'items' => [['label' => "Item {$second}", 'max_score' => 60]],
                ]],
            ],
        ],
    ];

    $this->actingAs($admin)->post(route('admin.rubrics.store'), $payload('PENYAJIAN', 'TANYA JAWAB'));
    $rubric = Rubric::sole();

    expect($rubric->load('criteria')->blocks()->pluck('label', 'code')->all())
        ->toBe(['A' => 'PENYAJIAN', 'B' => 'TANYA JAWAB']);

    // Swapped round, the codes follow the new order rather than the labels.
    $this->actingAs($admin)->put(
        route('admin.rubrics.update', $rubric),
        $payload('TANYA JAWAB', 'PENYAJIAN'),
    )->assertRedirect();

    expect($rubric->refresh()->load('criteria')->blocks()->pluck('label', 'code')->all())
        ->toBe(['A' => 'TANYA JAWAB', 'B' => 'PENYAJIAN']);
});
