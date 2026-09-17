<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guidance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('stage')->default('proposal');
            $table->string('target_type')->default('single');
            $table->string('guidance_type')->default('face_to_face');
            $table->string('meeting_type')->nullable();
            $table->string('meeting_link')->nullable();
            $table->string('meeting_location')->nullable();
            $table->dateTime('meeting_scheduled_at')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->string('status')->default('ongoing');
            $table->text('notes')->nullable();
            $table->text('feedback')->nullable();
            $table->text('completion_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['student_id', 'stage', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guidance_sessions');
    }
};
