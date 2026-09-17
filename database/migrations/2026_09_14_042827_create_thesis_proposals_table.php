<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thesis_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('background');
            $table->text('problem_formulation');
            $table->text('objectives');
            $table->text('scope')->nullable();
            $table->text('methodology')->nullable();
            $table->text('roadmap_alignment')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->unsignedInteger('document_size')->nullable();
            $table->string('status')->default('submitted');
            $table->foreignId('approved_by')->nullable()->constrained('lecturers')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->dateTime('agreed_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['department_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thesis_proposals');
    }
};
