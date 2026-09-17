<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How long a face-to-face bimbingan slot occupies a supervisor's calendar.
     * Two students cannot book the same supervisor inside this window.
     */
    public function up(): void
    {
        Schema::table('department_thesis_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('guidance_slot_minutes')
                ->default(60)
                ->after('max_supervisors_per_student');
        });
    }

    public function down(): void
    {
        Schema::table('department_thesis_settings', function (Blueprint $table) {
            $table->dropColumn('guidance_slot_minutes');
        });
    }
};
