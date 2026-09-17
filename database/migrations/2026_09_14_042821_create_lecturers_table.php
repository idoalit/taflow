<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lecturers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('lecturer_number')->unique();
            $table->string('phone')->nullable();
            $table->string('academic_position')->nullable();
            $table->string('status')->default('active');
            $table->unsignedSmallInteger('max_supervisions')->default(10);
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lecturers');
    }
};
