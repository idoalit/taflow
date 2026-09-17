<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a pembimbing marks: the supervision process itself, on its own form.
     *
     * Kept per program studi and seedable, like the exam rubric, so a faculty
     * whose form differs is not forced into someone else's indicators.
     */
    public function up(): void
    {
        Schema::create('supervision_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('code', 8);
            $table->string('label');
            $table->unsignedTinyInteger('max_score')->default(20);
            $table->unsignedTinyInteger('order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['department_id', 'code']);
            $table->index(['department_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervision_indicators');
    }
};
