<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which rubric a program studi uses for an exam stage, and the score a
     * student must reach to pass it. Only one assignment per stage may be
     * active at a time; superseded ones are kept for the exams they governed.
     */
    public function up(): void
    {
        Schema::create('department_rubrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('exam_stage');
            $table->foreignId('rubric_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('pass_threshold')->default(65);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'exam_stage', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_rubrics');
    }
};
