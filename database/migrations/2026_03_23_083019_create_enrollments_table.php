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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('course_offering_id')->nullable()->constrained('course_offerings')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('last_lesson_id')->nullable()->constrained('lessons')->onDelete('set null');
            $table->integer('progress')->nullable()->default(0);
            $table->string('status')->nullable()->default('pending'); // pending, active, completed, expired
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('ended_at');
            $table->index('course_offering_id');
        });

        DB::statement('CREATE UNIQUE INDEX enrollments_user_offering_unique ON enrollments (user_id, course_offering_id) WHERE course_offering_id IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
