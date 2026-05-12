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
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')
                ->nullable()
                ->constrained('courses')
                ->nullOnDelete();
            $table->foreignId('section_id')
                ->nullable()
                ->constrained('sections')
                ->nullOnDelete();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->boolean('is_required_for_certificate')->nullable()->default(true);
            $table->boolean('allow_resubmission')->nullable()->default(true);
            $table->unsignedInteger('max_attempts')->nullable();
            $table->string('status')->nullable()->default('published'); // draft, published, archived
            $table->timestamps();

            $table->index('course_id');
            $table->index('section_id');
            $table->index('status');
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')
                ->nullable()
                ->constrained('assignments')
                ->nullOnDelete();
            $table->foreignId('enrollment_id')
                ->nullable()
                ->constrained('enrollments')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->unsignedInteger('attempt_no')->nullable()->default(1);
            $table->longText('submission_text')->nullable();
            $table->string('attachment_url')->nullable();
            $table->string('status')->nullable()->default('submitted'); // submitted, revision_required, approved
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('assignment_id');
            $table->index('enrollment_id');
            $table->index('status');
            $table->unique(['assignment_id', 'enrollment_id', 'attempt_no'], 'assignment_submission_attempt_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
    }
};
