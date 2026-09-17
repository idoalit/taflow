<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weight is meaningless under the block-additive scheme, where criteria
     * add up rather than being weighted. The column was left NOT NULL when the
     * second scheme arrived, so a block-rubric verdict could not record its
     * criterion scores at all.
     */
    public function up(): void
    {
        Schema::table('exam_evaluation_scores', function (Blueprint $table): void {
            $table->unsignedTinyInteger('weight')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('exam_evaluation_scores', function (Blueprint $table): void {
            $table->unsignedTinyInteger('weight')->nullable(false)->default(0)->change();
        });
    }
};
