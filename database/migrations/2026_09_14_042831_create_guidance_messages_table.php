<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guidance_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guidance_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->index(['guidance_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guidance_messages');
    }
};
