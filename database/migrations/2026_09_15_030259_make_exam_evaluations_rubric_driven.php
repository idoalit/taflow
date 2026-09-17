<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A verdict used to be two fixed numbers. It becomes one row per rubric
     * criterion, so a program studi decides what is being marked.
     *
     * The existing pair is carried across as two criteria weighted 50/50 —
     * which is exactly how they were combined before — with the label and
     * weight copied onto each score, so later edits to a rubric can never
     * rewrite a verdict that has already been given.
     */
    public function up(): void
    {
        Schema::create('exam_evaluation_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_evaluation_id')->constrained()->cascadeOnDelete();
            $table->string('criterion_key');
            $table->string('criterion_label');
            $table->unsignedTinyInteger('weight');
            $table->decimal('score', 5, 2);
            $table->timestamps();

            $table->unique(['exam_evaluation_id', 'criterion_key']);
        });

        Schema::table('exam_evaluations', function (Blueprint $table): void {
            $table->foreignId('rubric_id')->nullable()->after('lecturer_id')->constrained()->nullOnDelete();
        });

        $legacy = [
            ['key' => 'presentasi', 'label' => 'Presentasi', 'column' => 'presentation_score'],
            ['key' => 'naskah', 'label' => 'Naskah', 'column' => 'document_score'],
        ];

        foreach (DB::table('exam_evaluations')->get() as $evaluation) {
            foreach ($legacy as $criterion) {
                DB::table('exam_evaluation_scores')->insert([
                    'exam_evaluation_id' => $evaluation->id,
                    'criterion_key' => $criterion['key'],
                    'criterion_label' => $criterion['label'],
                    'weight' => 50,
                    'score' => $evaluation->{$criterion['column']},
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('exam_evaluations', function (Blueprint $table): void {
            $table->dropColumn(['presentation_score', 'document_score']);
        });
    }

    public function down(): void
    {
        Schema::table('exam_evaluations', function (Blueprint $table): void {
            $table->decimal('presentation_score', 5, 2)->default(0)->after('rubric_id');
            $table->decimal('document_score', 5, 2)->default(0)->after('presentation_score');
        });

        // Only the two criteria this migration created can go back into
        // columns; any other criterion a program studi defined is dropped.
        foreach (['presentasi' => 'presentation_score', 'naskah' => 'document_score'] as $key => $column) {
            foreach (DB::table('exam_evaluation_scores')->where('criterion_key', $key)->get() as $score) {
                DB::table('exam_evaluations')
                    ->where('id', $score->exam_evaluation_id)
                    ->update([$column => $score->score]);
            }
        }

        Schema::table('exam_evaluations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('rubric_id');
        });

        Schema::dropIfExists('exam_evaluation_scores');
    }
};
