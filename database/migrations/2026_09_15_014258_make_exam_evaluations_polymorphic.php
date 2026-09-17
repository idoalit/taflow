<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Panel verdicts were tied to proposal exams by a hard foreign key, which
     * left the ujian hasil with nowhere to record them. The columns are widened
     * to a morph before the old one is dropped, so every existing evaluation
     * carries over.
     */
    public function up(): void
    {
        Schema::table('exam_evaluations', function (Blueprint $table): void {
            $table->string('exam_type')->nullable()->after('id');
            $table->unsignedBigInteger('exam_id')->nullable()->after('exam_type');
        });

        DB::table('exam_evaluations')->update([
            'exam_type' => 'proposal_exam',
            'exam_id' => DB::raw('proposal_exam_id'),
        ]);

        Schema::table('exam_evaluations', function (Blueprint $table): void {
            $table->dropUnique(['proposal_exam_id', 'lecturer_id']);
            $table->dropConstrainedForeignId('proposal_exam_id');
        });

        Schema::table('exam_evaluations', function (Blueprint $table): void {
            $table->string('exam_type')->nullable(false)->change();
            $table->unsignedBigInteger('exam_id')->nullable(false)->change();

            $table->unique(['exam_type', 'exam_id', 'lecturer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('exam_evaluations', function (Blueprint $table): void {
            $table->dropUnique(['exam_type', 'exam_id', 'lecturer_id']);
            $table->foreignId('proposal_exam_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // Research-exam verdicts have no column to go back to, so they are the
        // one thing this rollback cannot keep.
        DB::table('exam_evaluations')->where('exam_type', 'research_exam')->delete();

        DB::table('exam_evaluations')->update([
            'proposal_exam_id' => DB::raw('exam_id'),
        ]);

        Schema::table('exam_evaluations', function (Blueprint $table): void {
            $table->unsignedBigInteger('proposal_exam_id')->nullable(false)->change();
            $table->unique(['proposal_exam_id', 'lecturer_id']);
            $table->dropColumn(['exam_type', 'exam_id']);
        });
    }
};
