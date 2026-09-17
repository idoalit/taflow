<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A pembimbing's mark on one indicator for one sitting.
     *
     * The label and maximum are copied alongside the score, for the same reason
     * exam scores copy theirs: editing the master list later must not rewrite a
     * mark that has already been given.
     */
    public function up(): void
    {
        Schema::create('supervision_scores', function (Blueprint $table) {
            $table->id();
            $table->string('exam_type');
            $table->unsignedBigInteger('exam_id');
            $table->foreignId('lecturer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supervision_indicator_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->string('indicator_code', 8);
            $table->string('indicator_label');
            $table->unsignedTinyInteger('max_score');
            $table->decimal('score', 5, 2);
            $table->text('notes')->nullable();
            $table->dateTime('scored_at');
            $table->timestamps();

            $table->unique(
                ['exam_type', 'exam_id', 'lecturer_id', 'indicator_code'],
                'supervision_scores_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervision_scores');
    }
};
