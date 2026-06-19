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
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('course_title')->nullable()->after('course_offering_id');
            $table->string('course_slug')->nullable()->after('course_title');
            $table->string('period_code')->nullable()->after('course_slug');
            $table->string('period_name')->nullable()->after('period_code');
            $table->json('course_offering_snapshot')->nullable()->after('period_name');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->json('completion_snapshot')->nullable()->after('progress');
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->string('status')->nullable()->default('published')->after('is_preview');
            $table->index('status');
        });

        DB::table('lessons')
            ->whereNull('status')
            ->update(['status' => 'published']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn('completion_snapshot');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'course_title',
                'course_slug',
                'period_code',
                'period_name',
                'course_offering_snapshot',
            ]);
        });
    }
};
