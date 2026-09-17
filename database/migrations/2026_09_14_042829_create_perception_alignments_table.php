<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perception_alignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thesis_proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('supervisor_order')->default(1);
            $table->string('status')->default('pending');
            $table->text('feedback')->nullable();
            $table->text('preparation_requirements')->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['thesis_proposal_id', 'lecturer_id'], 'perception_alignments_proposal_lecturer_unique');
            $table->index(['lecturer_id', 'status']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perception_alignments');
    }
};
