<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_supervisors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->unsignedTinyInteger('order')->default(1);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'lecturer_id', 'role']);
            $table->index(['lecturer_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_supervisors');
    }
};
