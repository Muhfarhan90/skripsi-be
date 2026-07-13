<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->dropColumn(['open_at', 'close_at']);
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('due_at');
        });
    }

    public function down(): void
    {
        Schema::table('quizzes', function (Blueprint $table) {
            $table->timestamp('open_at')->nullable()->after('max_attempts');
            $table->timestamp('close_at')->nullable()->after('open_at');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->timestamp('due_at')->nullable()->after('instructions');
        });
    }
};
