<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per panel member per exam. The exam's own result is decided from
     * these once every member has submitted.
     */
    public function up(): void
    {
        Schema::create('exam_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained()->cascadeOnDelete();
            $table->string('panel_role');
            $table->decimal('presentation_score', 5, 2);
            $table->decimal('proposal_score', 5, 2);
            $table->decimal('final_score', 5, 2);
            $table->string('result');
            $table->text('feedback')->nullable();
            $table->text('revision_notes')->nullable();
            $table->dateTime('evaluated_at');
            $table->timestamps();

            $table->unique(['proposal_exam_id', 'lecturer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_evaluations');
    }
};
