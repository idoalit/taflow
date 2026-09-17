<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A program studi's own number-to-letter conversion. Ranges are inclusive
     * and are expected to tile 0-100 without gaps or overlaps.
     */
    public function up(): void
    {
        Schema::create('grade_scales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('letter', 4);
            $table->decimal('min_score', 5, 2);
            $table->decimal('max_score', 5, 2);
            $table->unsignedTinyInteger('order')->default(1);
            $table->timestamps();

            $table->unique(['department_id', 'letter']);
            $table->index(['department_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_scales');
    }
};
