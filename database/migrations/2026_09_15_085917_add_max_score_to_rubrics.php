<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Not every program studi marks out of 100 — some score each criterion 1-5,
     * others 1-10. The scale belongs to the rubric, and is copied onto a
     * verdict so a later change of scale cannot re-read an old mark.
     *
     * Everything that exists was marked out of 100, which is the default.
     */
    public function up(): void
    {
        Schema::table('rubrics', function (Blueprint $table): void {
            $table->unsignedTinyInteger('max_score')->default(100)->after('description');
        });

        Schema::table('exam_evaluations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('max_score')->default(100)->after('rubric_id');
        });
    }

    public function down(): void
    {
        Schema::table('rubrics', function (Blueprint $table): void {
            $table->dropColumn('max_score');
        });

        Schema::table('exam_evaluations', function (Blueprint $table): void {
            $table->dropColumn('max_score');
        });
    }
};
