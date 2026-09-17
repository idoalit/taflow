<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lembar Revisi: one numbered line of what an examiner wants changed.
     *
     * Numbered per examiner, because the printed sheet is per examiner; the
     * student sees them merged into one checklist.
     */
    public function up(): void
    {
        Schema::create('revision_notes', function (Blueprint $table) {
            $table->id();
            $table->string('exam_type');
            $table->unsignedBigInteger('exam_id');
            $table->foreignId('lecturer_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->text('body');
            $table->string('status')->default('open');
            $table->date('due_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['exam_type', 'exam_id', 'lecturer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revision_notes');
    }
};
