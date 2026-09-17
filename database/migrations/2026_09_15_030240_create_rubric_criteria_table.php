<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One weighted line of a rubric. Weights are whole percents and are
     * expected to total 100 before a rubric may be published.
     */
    public function up(): void
    {
        Schema::create('rubric_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubric_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('key');
            $table->unsignedTinyInteger('weight');
            $table->unsignedTinyInteger('order')->default(1);
            $table->timestamps();

            $table->unique(['rubric_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rubric_criteria');
    }
};
