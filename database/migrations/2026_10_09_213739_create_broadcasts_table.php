<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What was announced, by whom, and to which audience. The notifications
     * themselves live in each recipient's inbox; this is the sender's record
     * that the announcement was ever made.
     */
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('url')->nullable();
            // Null on either column means "everyone" along that axis.
            $table->string('audience_role')->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
