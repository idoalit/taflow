<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_exam_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('thesis_proposal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('research_exam_id')->nullable()->constrained()->nullOnDelete();
            $table->text('student_notes')->nullable();
            $table->json('documents')->nullable();
            $table->string('status')->default('pending');
            $table->string('supervisor_status')->default('pending');
            $table->foreignId('supervisor_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('supervisor_verified_at')->nullable();
            $table->text('supervisor_notes')->nullable();
            $table->string('admin_status')->default('pending');
            $table->foreignId('admin_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('admin_verified_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['supervisor_status', 'admin_status'], 'research_exam_requests_approval_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_exam_requests');
    }
};
