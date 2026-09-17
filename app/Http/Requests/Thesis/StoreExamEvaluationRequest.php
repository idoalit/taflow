<?php

namespace App\Http\Requests\Thesis;

use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Models\Rubric;
use App\Models\RubricCriterion;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExamEvaluationRequest extends FormRequest
{
    private ?Rubric $rubric = null;

    private bool $rubricResolved = false;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'scores' => ['required', 'array'],
            // The range is the rubric's own scale, checked in after() so it
            // reports alongside everything else rather than a round trip later.
            'scores.*' => ['required', 'numeric'],
            'result' => ['required', Rule::enum(ExamResult::class)],
            'feedback' => ['nullable', 'string', 'max:2000'],
            'revision_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Every criterion the rubric defines must carry a mark within its scale.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $rubric = $this->rubric();

            if ($rubric === null) {
                return;
            }

            foreach ($rubric->criteria as $criterion) {
                $this->checkCriterion($validator, $criterion);
            }
        }];
    }

    /**
     * The rubric the exam being marked is governed by, or null when the exam
     * cannot be resolved — the controller refuses those on its own.
     */
    public function rubric(): ?Rubric
    {
        if ($this->rubricResolved) {
            return $this->rubric;
        }

        $this->rubricResolved = true;

        $stage = $this->route('stage');
        $stage = $stage instanceof ExamStage ? $stage : ExamStage::tryFrom((string) $stage);

        if ($stage === null) {
            return null;
        }

        $exam = $stage->modelClass()::query()->where('id', $this->route('exam'))->first();

        return $this->rubric = $exam?->effectiveRubric();
    }

    private function checkCriterion(Validator $validator, RubricCriterion $criterion): void
    {
        $key = "scores.{$criterion->key}";
        $value = $this->input($key);

        if ($value === null || $value === '') {
            $validator->errors()->add($key, "Skor {$criterion->label} wajib diisi.");

            return;
        }

        if (! is_numeric($value) || $value < 0 || $value > $criterion->max_score) {
            $validator->errors()->add(
                $key,
                "Skor {$criterion->label} harus antara 0 dan {$criterion->max_score}.",
            );
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'scores' => 'skor penilaian',
            'scores.*' => 'skor kriteria',
            'result' => 'hasil akhir',
            'feedback' => 'umpan balik',
            'revision_notes' => 'daftar revisi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scores.required' => 'Isi skor untuk setiap kriteria penilaian.',
        ];
    }
}
