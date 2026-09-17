<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_thesis_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('min_proposal_guidance_sessions')->default(4);
            $table->unsignedSmallInteger('min_research_guidance_sessions')->default(2);
            $table->unsignedTinyInteger('min_logbook_progress')->default(100);
            $table->boolean('requires_proposal_exam')->default(true);
            $table->boolean('requires_perception_alignment')->default(true);
            $table->unsignedSmallInteger('max_supervisors_per_student')->default(2);
            $table->boolean('is_active')->default(true);
            $table->dateTime('effective_since');
            $table->dateTime('effective_until')->nullable();
            $table->timestamps();

            $table->unique(['department_id', 'effective_since'], 'dept_thesis_settings_unique');
            $table->index(['department_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_thesis_settings');
    }
};
