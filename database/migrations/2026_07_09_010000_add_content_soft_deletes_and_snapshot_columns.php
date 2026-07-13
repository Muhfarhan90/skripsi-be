<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('sections', 'deleted_at')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('lessons', 'deleted_at')) {
            Schema::table('lessons', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('assignments', 'deleted_at')) {
            Schema::table('assignments', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('options', 'deleted_at')) {
            Schema::table('options', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (! Schema::hasColumn('quiz_answers', 'question_snapshot')) {
            Schema::table('quiz_answers', function (Blueprint $table) {
                $table->json('question_snapshot')->nullable()->after('score');
            });
        }

        if (! Schema::hasColumn('assignment_submissions', 'assignment_snapshot')) {
            Schema::table('assignment_submissions', function (Blueprint $table) {
                $table->json('assignment_snapshot')->nullable()->after('attachment_url');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('assignment_submissions', 'assignment_snapshot')) {
            Schema::table('assignment_submissions', function (Blueprint $table) {
                $table->dropColumn('assignment_snapshot');
            });
        }

        if (Schema::hasColumn('quiz_answers', 'question_snapshot')) {
            Schema::table('quiz_answers', function (Blueprint $table) {
                $table->dropColumn('question_snapshot');
            });
        }

        if (Schema::hasColumn('options', 'deleted_at')) {
            Schema::table('options', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('assignments', 'deleted_at')) {
            Schema::table('assignments', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('lessons', 'deleted_at')) {
            Schema::table('lessons', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('sections', 'deleted_at')) {
            Schema::table('sections', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
