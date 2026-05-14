<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $duplicateExists = DB::table('course_offerings')
            ->select('course_id', 'academic_period_id', DB::raw('COUNT(*) as aggregate'))
            ->whereNotNull('course_id')
            ->whereNotNull('academic_period_id')
            ->groupBy('course_id', 'academic_period_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateExists) {
            throw new RuntimeException('Duplicate course_offerings found for the same course_id and academic_period_id. Clean the data before running this migration.');
        }

        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropColumn([
                'start_at',
                'end_at',
                'enrollment_open_at',
                'enrollment_close_at',
            ]);
        });

        Schema::table('course_offerings', function (Blueprint $table) {
            $table->unique(['course_id', 'academic_period_id'], 'course_offerings_course_period_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropUnique('course_offerings_course_period_unique');
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->timestamp('enrollment_open_at')->nullable();
            $table->timestamp('enrollment_close_at')->nullable();
        });
    }
};
