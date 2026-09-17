<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scoring and releasing are different moments.
     *
     * An exam whose panel has finished marking is `scored`: the numbers exist
     * but the student cannot see them. `released` is the program studi's act of
     * publishing, and it locks everything behind it.
     *
     * What used to be called `completed` was always the first of those.
     */
    public function up(): void
    {
        foreach (['proposal_exams', 'research_exams'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dateTime('released_at')->nullable()->after('completed_at');
                $blueprint->foreignId('released_by')->nullable()->after('released_at')
                    ->constrained('users')->nullOnDelete();
            });

            DB::table($table)->where('status', 'completed')->update(['status' => 'scored']);
        }
    }

    public function down(): void
    {
        foreach (['proposal_exams', 'research_exams'] as $table) {
            DB::table($table)->whereIn('status', ['scored', 'released'])
                ->update(['status' => 'completed']);

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('released_by');
                $blueprint->dropColumn('released_at');
            });
        }
    }
};
