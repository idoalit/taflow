<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Some program studi forbid a supervisor from examining their own student;
     * others expect it, since nobody knows the work better. The rule is a
     * setting rather than a constant because both are legitimate.
     */
    public function up(): void
    {
        Schema::table('department_thesis_settings', function (Blueprint $table): void {
            $table->boolean('allows_supervisor_as_examiner')->default(true)
                ->after('perception_alignment_mode');
            $table->unsignedTinyInteger('min_examiners')->default(2)
                ->after('allows_supervisor_as_examiner');
        });
    }

    public function down(): void
    {
        Schema::table('department_thesis_settings', function (Blueprint $table): void {
            $table->dropColumn(['allows_supervisor_as_examiner', 'min_examiners']);
        });
    }
};
