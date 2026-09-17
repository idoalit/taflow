<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_logbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('log_date');
            $table->text('description');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_logbooks');
    }
};
