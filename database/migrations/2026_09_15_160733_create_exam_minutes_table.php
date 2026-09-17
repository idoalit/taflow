<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Berita Acara: the panel's decision, recorded once per sitting.
     *
     * The composed score is written here rather than read live, because the
     * minutes state what was decided on the day — later changes to a weight or
     * a rubric must not rewrite a decision already taken.
     */
    public function up(): void
    {
        Schema::create('exam_minutes', function (Blueprint $table) {
            $table->id();
            $table->string('exam_type');
            $table->unsignedBigInteger('exam_id');
            $table->foreignId('decided_by')->constrained('users')->cascadeOnDelete();
            $table->string('decision');
            $table->decimal('exam_score', 6, 2)->nullable();
            $table->decimal('supervision_score', 6, 2)->nullable();
            $table->decimal('final_score', 6, 2)->nullable();
            $table->string('letter', 4)->nullable();
            $table->unsignedSmallInteger('revision_days')->default(7);
            $table->text('notes')->nullable();
            $table->dateTime('decided_at');
            $table->timestamps();

            $table->unique(['exam_type', 'exam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_minutes');
    }
};
