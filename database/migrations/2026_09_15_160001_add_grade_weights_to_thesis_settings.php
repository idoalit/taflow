<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How the two marks combine into the final grade.
     *
     * Configuration rather than constants in the calculation: 0.9 and 0.1 are
     * this faculty's weights today, not an arithmetic truth.
     */
    public function up(): void
    {
        Schema::table('department_thesis_settings', function (Blueprint $table): void {
            $table->decimal('exam_score_weight', 4, 3)->default(0.900)->after('min_examiners');
            $table->decimal('supervision_score_weight', 4, 3)->default(0.100)
                ->after('exam_score_weight');
        });
    }

    public function down(): void
    {
        Schema::table('department_thesis_settings', function (Blueprint $table): void {
            $table->dropColumn(['exam_score_weight', 'supervision_score_weight']);
        });
    }
};
