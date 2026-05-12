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
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->onDelete('cascade');
            $table->foreignId('section_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('duration')->nullable()->default(0); // in seconds
            $table->integer('passing_score')->nullable()->default(0);
            $table->integer('weight')->nullable()->default(0);
            $table->boolean('is_active')->nullable()->default(true);
            $table->boolean('is_random')->nullable()->default(false);
            $table->integer('max_attempts')->nullable()->default(0); // 0 for unlimited
            $table->timestamp('open_at')->nullable();
            $table->timestamp('close_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
