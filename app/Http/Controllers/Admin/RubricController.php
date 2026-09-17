<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExamStage;
use App\Enums\RubricStatus;
use App\Enums\ScoringScheme;
use App\Http\Controllers\Admin\Concerns\ScopesToDepartment;
use App\Http\Controllers\Controller;
use App\Models\DepartmentRubric;
use App\Models\Rubric;
use App\Models\RubricCriterion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RubricController extends Controller
{
    use ScopesToDepartment;

    public function index(Request $request): Response
    {
        Gate::authorize('manage', Rubric::class);

        $department = $this->department($request);

        $rubrics = Rubric::query()
            ->where('department_id', $department->id)
            ->with('criteria')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/rubrics/index', [
            'department' => $department->name,
            'rubrics' => $rubrics->map(fn (Rubric $rubric): array => $this->row($rubric))->values(),
            'assignments' => $this->assignments($request),
            'stages' => array_map(fn (ExamStage $stage): array => [
                'value' => $stage->value,
                'label' => $stage->title(),
            ], ExamStage::cases()),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('manage', Rubric::class);

        return Inertia::render('admin/rubrics/form', [
            'department' => $this->department($request)->name,
            'rubric' => null,
            'schemes' => $this->schemeOptions(),
        ]);
    }

    /**
     * Drafts only. A published rubric may already govern a scheduled exam, so
     * changing it would move the goalposts after the fact.
     */
    public function edit(Request $request, Rubric $rubric): Response
    {
        Gate::authorize('manage', Rubric::class);
        $this->guardOwnership($request, $rubric);
        $this->guardEditable($rubric);

        $rubric->load('criteria');

        return Inertia::render('admin/rubrics/form', [
            'department' => $this->department($request)->name,
            'schemes' => $this->schemeOptions(),
            'rubric' => [
                'id' => $rubric->id,
                'name' => $rubric->name,
                'description' => $rubric->description,
                'scoring_scheme' => $rubric->scoring_scheme->value,
                'criteria' => $rubric->criteria->map(fn (RubricCriterion $criterion): array => [
                    'label' => $criterion->label,
                    'weight' => $criterion->weight,
                    'max_score' => $criterion->max_score,
                ])->values(),
                'blocks' => $this->nestedBlocks($rubric),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Rubric::class);

        $department = $this->department($request);
        $validated = $this->validateRubric($request);

        $rubric = DB::transaction(function () use ($department, $request, $validated): Rubric {
            $rubric = Rubric::create([
                'department_id' => $department->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'scoring_scheme' => $validated['scoring_scheme'],
                'status' => RubricStatus::Draft,
                'created_by' => $request->user()->id,
            ]);

            $this->syncCriteria($rubric, $validated['criteria']);

            return $rubric;
        });

        return to_route('admin.rubrics.show', $rubric)->with('success', 'Rubrik tersimpan sebagai draft.');
    }

    public function update(Request $request, Rubric $rubric): RedirectResponse
    {
        Gate::authorize('manage', Rubric::class);
        $this->guardOwnership($request, $rubric);
        $this->guardEditable($rubric);

        $validated = $this->validateRubric($request);

        DB::transaction(function () use ($rubric, $validated): void {
            $rubric->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'scoring_scheme' => $validated['scoring_scheme'],
            ]);

            // Criteria are replaced wholesale: a draft has no verdicts behind
            // it, so nothing references the rows being removed.
            $rubric->criteria()->delete();
            $this->syncCriteria($rubric, $validated['criteria']);
        });

        return to_route('admin.rubrics.show', $rubric)->with('success', 'Perubahan rubrik tersimpan.');
    }

    /**
     * Drafts only, for the same reason they are the only ones editable. The
     * criteria go with it; nothing else references a draft.
     */
    public function destroy(Request $request, Rubric $rubric): RedirectResponse
    {
        Gate::authorize('manage', Rubric::class);
        $this->guardOwnership($request, $rubric);
        $this->guardEditable($rubric);

        $name = $rubric->name;
        $rubric->delete();

        return to_route('admin.rubrics.index')->with('success', "Rubrik \"{$name}\" dihapus.");
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     */
    private function syncCriteria(Rubric $rubric, array $criteria): void
    {
        $keys = RubricCriterion::keysFor(array_column($criteria, 'label'));

        foreach (array_values($criteria) as $order => $criterion) {
            $rubric->criteria()->create([
                ...$criterion,
                'key' => $keys[$order],
                'order' => $order + 1,
            ]);
        }
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    private function schemeOptions(): array
    {
        return array_map(fn (ScoringScheme $scheme): array => [
            'value' => $scheme->value,
            'label' => $scheme->label(),
            'description' => $scheme->description(),
        ], ScoringScheme::cases());
    }

    /**
     * The rubric as the block editor holds it: blocks, sub-blocks, items.
     *
     * @return array<int, array<string, mixed>>
     */
    private function nestedBlocks(Rubric $rubric): array
    {
        return $rubric->blocks()
            ->map(fn (array $block): array => [
                'label' => $block['label'] ?? '',
                'subblocks' => $block['subblocks']->map(fn (array $sub): array => [
                    'label' => $sub['label'] ?? '',
                    'items' => $sub['items']->map(fn (RubricCriterion $item): array => [
                        'label' => $item->label,
                        'max_score' => $item->max_score,
                    ])->values()->all(),
                ])->values()->all(),
            ])
            ->all();
    }

    private function guardEditable(Rubric $rubric): void
    {
        abort_unless(
            $rubric->isEditable(),
            403,
            'Rubrik yang sudah diterbitkan tidak dapat diubah. '
            .'Buat rubrik baru bila kriterianya perlu berganti.',
        );
    }

    public function show(Request $request, Rubric $rubric): Response
    {
        Gate::authorize('manage', Rubric::class);
        $this->guardOwnership($request, $rubric);

        $rubric->load('criteria');

        return Inertia::render('admin/rubrics/show', [
            'rubric' => [
                ...$this->row($rubric),
                'criteria' => $rubric->criteria->map(fn (RubricCriterion $criterion): array => [
                    'label' => $criterion->label,
                    'key' => $criterion->key,
                    'weight' => $criterion->weight,
                    'max_score' => $criterion->max_score,
                    'block_code' => $criterion->block_code,
                    'block_label' => $criterion->block_label,
                    'subblock_code' => $criterion->subblock_code,
                    'subblock_label' => $criterion->subblock_label,
                    'item_number' => $criterion->item_number,
                ])->values(),
                'block_summary' => $rubric->blocks()->map(fn (array $block): array => [
                    'code' => $block['code'],
                    'label' => $block['label'],
                    'max' => $block['max'],
                ])->values(),
            ],
        ]);
    }

    /**
     * Publishing is the moment a rubric becomes usable, so it is also the
     * moment its weights have to add up.
     */
    public function publish(Request $request, Rubric $rubric): RedirectResponse
    {
        Gate::authorize('manage', Rubric::class);
        $this->guardOwnership($request, $rubric);

        $rubric->load('criteria');

        if (! $rubric->isBalanced()) {
            throw ValidationException::withMessages([
                'criteria' => "Total bobot kriteria harus tepat 100% (saat ini {$rubric->totalWeight()}%).",
            ]);
        }

        $rubric->update(['status' => RubricStatus::Published, 'published_at' => now()]);

        return back()->with('success', 'Rubrik diterbitkan dan siap diterapkan.');
    }

    /**
     * Apply a published rubric to one exam stage. The previous one is retired
     * rather than deleted, so exams it governed keep their history.
     */
    public function assign(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Rubric::class);

        $department = $this->department($request);

        $validated = $request->validate([
            'exam_stage' => ['required', Rule::enum(ExamStage::class)],
            'rubric_id' => ['required', Rule::in(
                Rubric::query()
                    ->where('department_id', $department->id)
                    ->where('status', RubricStatus::Published)
                    ->pluck('id'),
            )],
            'pass_threshold' => ['required', 'integer', 'min:0', 'max:100'],
        ], [
            'rubric_id.in' => 'Hanya rubrik yang sudah diterbitkan dapat diterapkan.',
        ], [
            'exam_stage' => 'tahap ujian',
            'rubric_id' => 'rubrik',
            'pass_threshold' => 'ambang kelulusan',
        ]);

        DB::transaction(function () use ($department, $validated): void {
            DepartmentRubric::query()
                ->where('department_id', $department->id)
                ->where('exam_stage', $validated['exam_stage'])
                ->where('is_active', true)
                ->update(['is_active' => false, 'effective_until' => now()->toDateString()]);

            DepartmentRubric::create([
                'department_id' => $department->id,
                'exam_stage' => $validated['exam_stage'],
                'rubric_id' => $validated['rubric_id'],
                'pass_threshold' => $validated['pass_threshold'],
                'is_active' => true,
                'effective_from' => now()->toDateString(),
            ]);
        });

        return back()->with('success', 'Rubrik diterapkan pada tahap ujian.');
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Both schemes arrive here; each has its own shape.
     *
     * Weighted rubrics are a flat list of criteria carrying weights. Block
     * rubrics arrive nested — blocks, optional sub-blocks, items — which is the
     * shape the printed form has, and the shape the seeder blueprint uses.
     *
     * @return array{name: string, description: string|null, scoring_scheme: ScoringScheme, criteria: array<int, array<string, mixed>>}
     */
    private function validateRubric(Request $request): array
    {
        $base = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'scoring_scheme' => ['required', Rule::enum(ScoringScheme::class)],
        ], [], [
            'name' => 'nama rubrik',
            'description' => 'deskripsi',
            'scoring_scheme' => 'skema penilaian',
        ]);

        $scheme = ScoringScheme::from($base['scoring_scheme']);

        $criteria = $scheme === ScoringScheme::BlockAdditive
            ? $this->validateBlocks($request)
            : $this->validateWeightedCriteria($request);

        $this->guardDistinctLabels($criteria);

        return [
            'name' => $base['name'],
            'description' => $base['description'] ?? null,
            'scoring_scheme' => $scheme,
            'criteria' => $criteria,
        ];
    }

    /**
     * Indonesian names for each criterion field, numbered the way the rows are
     * numbered on screen.
     *
     * @return array<string, string>
     */
    private function criteriaAttributeNames(Request $request): array
    {
        $names = [];
        $rows = $request->array('criteria');

        foreach (array_keys($rows) as $index) {
            $row = (int) $index + 1;

            $names["criteria.{$index}.label"] = "nama kriteria baris {$row}";
            $names["criteria.{$index}.weight"] = "bobot kriteria baris {$row}";
            $names["criteria.{$index}.max_score"] = "nilai maksimal kriteria baris {$row}";
        }

        return $names;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function validateWeightedCriteria(Request $request): array
    {
        $validated = $request->validate([
            'criteria' => ['required', 'array', 'min:1'],
            'criteria.*.label' => ['required', 'string', 'max:120'],
            'criteria.*.weight' => ['required', 'integer', 'min:1', 'max:100'],
            'criteria.*.max_score' => ['required', 'integer', 'min:1', 'max:100'],
        ], [
            'criteria.required' => 'Tambahkan sedikitnya satu kriteria penilaian.',
        ], [
            'criteria' => 'kriteria penilaian',
            // Without these the message reads "Kolom criteria.1.label wajib
            // diisi", which names a payload path rather than the row the
            // person is looking at.
            ...$this->criteriaAttributeNames($request),
        ]);

        $total = array_sum(array_column($validated['criteria'], 'weight'));

        if ($total !== 100) {
            throw ValidationException::withMessages([
                'criteria' => "Total bobot kriteria harus tepat 100% (saat ini {$total}%).",
            ]);
        }

        return array_map(fn (array $row): array => [
            'label' => $row['label'],
            'weight' => $row['weight'],
            'max_score' => $row['max_score'],
            'block_code' => null,
            'block_label' => null,
            'subblock_code' => null,
            'subblock_label' => null,
            'item_number' => null,
        ], $validated['criteria']);
    }

    /**
     * Flattens the nested block form into criterion rows, in printing order.
     *
     * @return array<int, array<string, mixed>>
     */
    private function validateBlocks(Request $request): array
    {
        $validated = $request->validate([
            // Codes are derived from position, never submitted: see
            // RubricCriterion::blockCodeFor() and subBlockCodeFor().
            'blocks' => ['required', 'array', 'min:1'],
            'blocks.*.label' => ['required', 'string', 'max:120'],
            'blocks.*.subblocks' => ['required', 'array', 'min:1'],
            'blocks.*.subblocks.*.label' => ['nullable', 'string', 'max:120'],
            'blocks.*.subblocks.*.items' => ['required', 'array', 'min:1'],
            'blocks.*.subblocks.*.items.*.label' => ['required', 'string', 'max:255'],
            'blocks.*.subblocks.*.items.*.max_score' => ['required', 'integer', 'min:1', 'max:100'],
        ], [
            'blocks.required' => 'Tambahkan sedikitnya satu blok penilaian.',
            'blocks.*.subblocks.*.items.required' => 'Setiap blok harus memiliki sedikitnya satu item.',
        ], [
            'blocks' => 'blok penilaian',
        ]);

        $rows = [];
        $total = 0;

        foreach (array_values($validated['blocks']) as $blockIndex => $block) {
            $subblocks = array_values($block['subblocks']);

            // A block holding one unnamed group is simply its items; giving
            // that group a numeral would print a heading nobody asked for.
            $named = count($subblocks) > 1
                || filled($subblocks[0]['label'] ?? null);

            foreach ($subblocks as $subIndex => $subblock) {
                foreach (array_values($subblock['items']) as $index => $item) {
                    $rows[] = [
                        'block_code' => RubricCriterion::blockCodeFor($blockIndex),
                        'block_label' => $block['label'],
                        'subblock_code' => $named
                            ? RubricCriterion::subBlockCodeFor($subIndex)
                            : null,
                        'subblock_label' => $named ? ($subblock['label'] ?? null) : null,
                        'item_number' => $index + 1,
                        'label' => $item['label'],
                        'max_score' => $item['max_score'],
                        'weight' => null,
                    ];
                    $total += $item['max_score'];
                }
            }
        }

        if ($total !== 100) {
            throw ValidationException::withMessages([
                'blocks' => "Jumlah skor maksimal seluruh item harus tepat 100 (saat ini {$total}).",
            ]);
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $criteria
     */
    private function guardDistinctLabels(array $criteria): void
    {
        $labels = array_map(
            fn (array $row): string => mb_strtolower(trim((string) $row['label'])),
            $criteria,
        );

        // Two criteria with the same name would be indistinguishable on the
        // penilaian form, whatever their keys ended up being.
        if (count($labels) !== count(array_unique($labels))) {
            throw ValidationException::withMessages([
                'criteria' => 'Setiap kriteria harus memiliki label yang berbeda.',
                'blocks' => 'Setiap kriteria harus memiliki label yang berbeda.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Rubric $rubric): array
    {
        return [
            'id' => $rubric->id,
            'name' => $rubric->name,
            'description' => $rubric->description,
            'status' => $rubric->status->value,
            'status_label' => $rubric->status->label(),
            'published_at' => $rubric->published_at?->translatedFormat('d M Y'),
            'criteria_count' => $rubric->criteria->count(),
            'scale_label' => $this->scaleLabel($rubric),
            'scoring_scheme' => $rubric->scoring_scheme->value,
            'scheme_label' => $rubric->scoring_scheme->label(),
            'total_weight' => $rubric->totalWeight(),
            // What "complete" reads as depends on the scheme: weights summing
            // to 100, or maximum scores doing so.
            'total_label' => $rubric->isBlockAdditive()
                ? $rubric->totalMaxScore().' dari 100'
                : $rubric->totalWeight().'%',
            'balanced' => $rubric->isBalanced(),
            'editable' => $rubric->isEditable(),
            'used' => $rubric->hasBeenUsed(),
            'show_url' => route('admin.rubrics.show', $rubric),
            'edit_url' => route('admin.rubrics.edit', $rubric),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function assignments(Request $request): array
    {
        $department = $this->department($request);

        return array_map(function (ExamStage $stage) use ($department): array {
            $assignment = DepartmentRubric::activeFor($department, $stage);

            return [
                'stage' => $stage->value,
                'stage_label' => $stage->title(),
                'rubric_id' => $assignment?->rubric_id,
                'rubric_name' => $assignment?->rubric->name,
                'pass_threshold' => $assignment?->pass_threshold,
                'effective_from' => $assignment?->effective_from->translatedFormat('d M Y'),
            ];
        }, ExamStage::cases());
    }

    /**
     * How the rubric's criteria are marked: one scale when they agree, a range
     * when they do not.
     */
    private function scaleLabel(Rubric $rubric): string
    {
        $scales = $rubric->criteria->pluck('max_score')->unique()->sort()->values();

        return match ($scales->count()) {
            0 => '—',
            1 => "0–{$scales->first()}",
            default => "0–{$scales->first()} s.d. 0–{$scales->last()}",
        };
    }

    private function guardOwnership(Request $request, Rubric $rubric): void
    {
        abort_unless($rubric->department_id === $this->department($request)->id, 404);
    }
}
