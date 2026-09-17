<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Official documents name the jurusan and the city they were signed in.
     * Neither is derivable from what a department already stores, and neither
     * may be hardcoded into a template.
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->string('faculty')->nullable()->after('name');
            $table->string('city')->nullable()->after('faculty');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->dropColumn(['faculty', 'city']);
        });
    }
};
