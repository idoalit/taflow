<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A block-additive rubric nests two levels deep — block A-D, and within B a
     * sub-block I-IV — and the printed form shows a subtotal for each. Those
     * subtotals are summed from the items rather than stored, so a rubric can
     * never disagree with itself.
     *
     * Weight becomes optional: it means nothing outside the weighted scheme.
     */
    public function up(): void
    {
        Schema::table('rubric_criteria', function (Blueprint $table): void {
            $table->string('block_code', 8)->nullable()->after('rubric_id');
            $table->string('block_label')->nullable()->after('block_code');
            $table->string('subblock_code', 8)->nullable()->after('block_label');
            $table->string('subblock_label')->nullable()->after('subblock_code');
            $table->unsignedTinyInteger('item_number')->nullable()->after('subblock_label');
            $table->unsignedTinyInteger('weight')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('rubric_criteria', function (Blueprint $table): void {
            $table->dropColumn([
                'block_code', 'block_label', 'subblock_code', 'subblock_label', 'item_number',
            ]);
        });
    }
};
