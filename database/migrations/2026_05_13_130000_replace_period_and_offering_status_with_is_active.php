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
        Schema::table('academic_periods', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('enrollment_close_at');
        });

        DB::table('academic_periods')->update([
            'is_active' => DB::raw("CASE WHEN LOWER(COALESCE(status, '')) = 'active' THEN TRUE ELSE FALSE END"),
        ]);

        Schema::table('academic_periods', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('course_offerings', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('discount_price');
        });

        DB::table('course_offerings')->update([
            'is_active' => DB::raw("CASE WHEN LOWER(COALESCE(status, '')) = 'published' THEN TRUE ELSE FALSE END"),
        ]);

        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('academic_periods', function (Blueprint $table) {
            $table->string('status')->nullable()->after('enrollment_close_at');
        });

        DB::table('academic_periods')->update([
            'status' => DB::raw("CASE WHEN is_active = TRUE THEN 'active' ELSE 'planned' END"),
        ]);

        Schema::table('academic_periods', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('course_offerings', function (Blueprint $table) {
            $table->string('status')->nullable()->default('draft')->after('discount_price');
        });

        DB::table('course_offerings')->update([
            'status' => DB::raw("CASE WHEN is_active = TRUE THEN 'published' ELSE 'draft' END"),
        ]);

        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
