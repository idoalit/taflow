<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The capability a document's QR code carries.
     *
     * Minted when a result is released and never before, so a token cannot
     * exist for something that has not been published.
     */
    public function up(): void
    {
        foreach (['proposal_exams', 'research_exams'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->uuid('verification_token')->nullable()->unique()->after('released_by');
            });
        }
    }

    public function down(): void
    {
        foreach (['proposal_exams', 'research_exams'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('verification_token');
            });
        }
    }
};
