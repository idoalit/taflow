<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One scale per rubric was too coarse: a program studi may well mark
     * presentation out of 5 and the written work out of 100 in the same rubric.
     * The scale moves onto each criterion, and onto each recorded mark.
     *
     * With per-criterion scales a raw weighted mean is meaningless — 4/5 and
     * 40/100 are not comparable — so a verdict's final_score becomes the
     * weighted percentage. Every existing criterion was marked out of 100,
     * where that percentage equals the old number, so no score shifts.
     */
    public function up(): void
    {
        Schema::table('rubric_criteria', function (Blueprint $table): void {
            $table->unsignedTinyInteger('max_score')->default(100)->after('weight');
        });

        Schema::table('exam_evaluation_scores', function (Blueprint $table): void {
            $table->unsignedTinyInteger('max_score')->default(100)->after('weight');
        });

        foreach (DB::table('rubrics')->get() as $rubric) {
            DB::table('rubric_criteria')
                ->where('rubric_id', $rubric->id)
                ->update(['max_score' => $rubric->max_score]);
        }

        foreach (DB::table('exam_evaluations')->get() as $evaluation) {
            DB::table('exam_evaluation_scores')
                ->where('exam_evaluation_id', $evaluation->id)
                ->update(['max_score' => $evaluation->max_score]);
        }

        Schema::table('rubrics', function (Blueprint $table): void {
            $table->dropColumn('max_score');
        });

        Schema::table('exam_evaluations', function (Blueprint $table): void {
            $table->dropColumn('max_score');
        });
    }

    public function down(): void
    {
        Schema::table('rubrics', function (Blueprint $table): void {
            $table->unsignedTinyInteger('max_score')->default(100)->after('description');
        });

        Schema::table('exam_evaluations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('max_score')->default(100)->after('rubric_id');
        });

        // A rubric whose criteria disagree cannot be expressed as one scale;
        // the highest is the only lossless choice for the rows that remain.
        foreach (DB::table('rubrics')->get() as $rubric) {
            $max = DB::table('rubric_criteria')->where('rubric_id', $rubric->id)->max('max_score');
            DB::table('rubrics')->where('id', $rubric->id)->update(['max_score' => $max ?? 100]);
        }

        foreach (DB::table('exam_evaluations')->get() as $evaluation) {
            $max = DB::table('exam_evaluation_scores')
                ->where('exam_evaluation_id', $evaluation->id)
                ->max('max_score');
            DB::table('exam_evaluations')->where('id', $evaluation->id)->update(['max_score' => $max ?? 100]);
        }

        Schema::table('rubric_criteria', function (Blueprint $table): void {
            $table->dropColumn('max_score');
        });

        Schema::table('exam_evaluation_scores', function (Blueprint $table): void {
            $table->dropColumn('max_score');
        });
    }
};
