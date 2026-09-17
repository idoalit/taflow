<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two ways to mark an exam, and a program studi picks one per rubric.
     *
     * `weighted` — each criterion carries a weight and its own scale; the
     * verdict is the weighted percentage. `block_additive` — criteria are
     * grouped into blocks whose maximum scores add up to 100, and the verdict
     * is simply their sum, as the faculty's paper forms have always worked.
     *
     * Everything that exists is weighted, which is the default.
     */
    public function up(): void
    {
        Schema::table('rubrics', function (Blueprint $table): void {
            $table->string('scoring_scheme')->default('weighted')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('rubrics', function (Blueprint $table): void {
            $table->dropColumn('scoring_scheme');
        });
    }
};
