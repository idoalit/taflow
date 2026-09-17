<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The rubric an exam is marked against is fixed when it is scheduled, so
     * changing the program studi's assignment later cannot move the goalposts
     * for an exam that is already on the calendar.
     */
    public function up(): void
    {
        foreach (['proposal_exams', 'research_exams'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('rubric_id')->nullable()->after('scheduled_by')->constrained()->nullOnDelete();
                $blueprint->unsignedTinyInteger('pass_threshold')->nullable()->after('rubric_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['proposal_exams', 'research_exams'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('rubric_id');
                $blueprint->dropColumn('pass_threshold');
            });
        }
    }
};
