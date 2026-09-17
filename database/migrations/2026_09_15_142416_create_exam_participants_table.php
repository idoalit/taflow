<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who sat at one exam, and in what capacity.
     *
     * Until now a panel was derived from the student's standing roster, which
     * cannot say who chaired a particular sitting, who actually attended, or
     * who signed. A roster is a relationship; this is an event.
     */
    public function up(): void
    {
        Schema::create('exam_participants', function (Blueprint $table) {
            $table->id();
            $table->string('exam_type');
            $table->unsignedBigInteger('exam_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecturer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role');
            $table->boolean('attended')->default(false);
            $table->dateTime('attended_at')->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->timestamps();

            // One seat per person per exam: a supervisor named as an examiner
            // takes the examiner's seat rather than gaining a second one.
            $table->unique(['exam_type', 'exam_id', 'user_id']);
            $table->index(['exam_type', 'exam_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_participants');
    }
};
