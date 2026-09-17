<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which signature was applied when this seat signed. Held rather than
     * looked up, so replacing a signature later does not restate what was on a
     * document that has already been issued.
     */
    public function up(): void
    {
        Schema::table('exam_participants', function (Blueprint $table): void {
            $table->foreignId('signature_id')->nullable()->after('signed_at')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exam_participants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('signature_id');
        });
    }
};
