<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The second score marks the written work, which is a proposal at one stage
     * and a research report at the other. A rename keeps every existing score;
     * only the column's name changes.
     */
    public function up(): void
    {
        Schema::table('exam_evaluations', function (Blueprint $table): void {
            $table->renameColumn('proposal_score', 'document_score');
        });
    }

    public function down(): void
    {
        Schema::table('exam_evaluations', function (Blueprint $table): void {
            $table->renameColumn('document_score', 'proposal_score');
        });
    }
};
