<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The design asks who the second party on a persamaan persepsi is, which a
     * yes/no flag cannot answer. The old flag maps onto the two modes that mean
     * the same thing, so no program studi loses its choice.
     */
    public function up(): void
    {
        Schema::table('department_thesis_settings', function (Blueprint $table): void {
            $table->string('perception_alignment_mode')->default('auto')->after('requires_proposal_exam');
        });

        DB::table('department_thesis_settings')
            ->where('requires_perception_alignment', false)
            ->update(['perception_alignment_mode' => 'skip']);

        Schema::table('department_thesis_settings', function (Blueprint $table): void {
            $table->dropColumn('requires_perception_alignment');
        });
    }

    public function down(): void
    {
        Schema::table('department_thesis_settings', function (Blueprint $table): void {
            $table->boolean('requires_perception_alignment')->default(true)->after('requires_proposal_exam');
        });

        DB::table('department_thesis_settings')
            ->where('perception_alignment_mode', 'skip')
            ->update(['requires_perception_alignment' => false]);

        Schema::table('department_thesis_settings', function (Blueprint $table): void {
            $table->dropColumn('perception_alignment_mode');
        });
    }
};
