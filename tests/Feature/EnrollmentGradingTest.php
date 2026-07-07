<?php

use App\Models\AcademicPeriod;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\EnrollmentService;
use App\Services\QuizAttemptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function createEnrollmentCompletionContext(array $courseOverrides = []): array
{
    $role = Role::unguarded(fn () => Role::query()->updateOrCreate(['id' => 1], ['name' => 'admin']));
    $instructor = User::factory()->create([
        'role_id' => $role->id,
        'email' => 'mastery-instructor@example.com',
    ]);
    $student = User::factory()->create([
        'role_id' => $role->id,
        'email' => 'mastery-student@example.com',
    ]);

    $category = Category::create([
        'name' => 'Mastery Category',
        'slug' => Str::slug('mastery-category'),
        'description' => null,
    ]);

    $course = Course::create(array_merge([
        'title' => 'Mastery Course',
        'slug' => Str::slug('mastery-course'),
        'description' => 'Course description',
        'category_id' => $category->id,
        'instructor_id' => $instructor->id,
        'thumbnail' => null,
        'total_duration' => 0,
        'requirements' => null,
        'outcomes' => null,
    ], $courseOverrides));

    $section = Section::create([
        'course_id' => $course->id,
        'title' => 'Section 1',
        'sort_order' => 1,
    ]);

    $period = AcademicPeriod::create([
        'code' => 'WEIGHT-2026',
        'name' => 'Weight Period 2026',
        'start_at' => now()->subDays(10),
        'end_at' => now()->addDays(30),
        'enrollment_open_at' => now()->subDays(20),
        'enrollment_close_at' => now()->addDays(5),
        'is_active' => true,
    ]);

    $offering = CourseOffering::create([
        'course_id' => $course->id,
        'academic_period_id' => $period->id,
        'capacity' => 30,
        'price' => 300000,
        'discount_price' => null,
        'is_active' => true,
    ]);

    $enrollment = Enrollment::create([
        'user_id' => $student->id,
        'course_offering_id' => $offering->id,
        'order_id' => null,
        'last_lesson_id' => null,
        'progress' => 0,
        'status' => 'active',
        'started_at' => now()->subDay(),
        'ended_at' => now()->addDays(7),
        'expired_at' => now()->addDays(7),
        'completed_at' => null,
        'completion_snapshot' => null,
    ]);

    $quiz = Quiz::create([
        'course_id' => $course->id,
        'section_id' => $section->id,
        'title' => 'Mastery Quiz',
        'description' => null,
        'duration' => 30,
        'passing_score' => 60,
        'weight' => 100,
        'is_active' => true,
        'is_random' => false,
        'max_attempts' => 3,
        'open_at' => null,
        'close_at' => null,
    ]);

    $assignment = Assignment::create([
        'course_id' => $course->id,
        'section_id' => $section->id,
        'created_by' => $instructor->id,
        'title' => 'Mastery Assignment',
        'description' => null,
        'instructions' => null,
        'due_at' => now()->addDays(3),
        'is_required_for_certificate' => true,
        'allow_resubmission' => true,
        'max_attempts' => 3,
        'status' => 'published',
    ]);

    return compact('course', 'section', 'offering', 'enrollment', 'quiz', 'assignment', 'student');
}

it('completes enrollment when best graded quiz attempt passes and required assignment is approved', function () {
    $context = createEnrollmentCompletionContext();

    QuizAttempt::create([
        'enrollment_id' => $context['enrollment']->id,
        'quiz_id' => $context['quiz']->id,
        'total_score' => 72,
        'status' => 'graded',
        'started_at' => now()->subHours(4),
        'submitted_at' => now()->subHours(3),
    ]);

    QuizAttempt::create([
        'enrollment_id' => $context['enrollment']->id,
        'quiz_id' => $context['quiz']->id,
        'total_score' => 90,
        'status' => 'graded',
        'started_at' => now()->subHours(2),
        'submitted_at' => now()->subHour(),
    ]);

    QuizAttempt::create([
        'enrollment_id' => $context['enrollment']->id,
        'quiz_id' => $context['quiz']->id,
        'total_score' => 100,
        'status' => 'submitted',
        'started_at' => now()->subMinutes(50),
        'submitted_at' => now()->subMinutes(30),
    ]);

    AssignmentSubmission::create([
        'assignment_id' => $context['assignment']->id,
        'enrollment_id' => $context['enrollment']->id,
        'user_id' => $context['student']->id,
        'attempt_no' => 1,
        'submission_text' => 'Done',
        'attachment_url' => null,
        'status' => 'approved',
        'review_notes' => 'Good',
        'reviewed_by' => $context['student']->id,
        'submitted_at' => now()->subHour(),
        'reviewed_at' => now()->subMinutes(20),
    ]);

    $enrollment = app(EnrollmentService::class)->syncProgress($context['enrollment']->id)->fresh();

    expect((string) $enrollment->status)->toBe('completed')
        ->and((int) $enrollment->progress)->toBe(100)
        ->and($enrollment->completion_snapshot['quiz_grade_items'][0]['quiz_id'] ?? null)->toBe($context['quiz']->id)
        ->and($enrollment->completion_snapshot['assignment_grade_items'][0]['assignment_id'] ?? null)->toBe($context['assignment']->id)
        ->and($enrollment->completed_at)->not->toBeNull();
});

it('keeps enrollment active when no graded quiz attempt reaches passing score', function () {
    $context = createEnrollmentCompletionContext();

    QuizAttempt::create([
        'enrollment_id' => $context['enrollment']->id,
        'quiz_id' => $context['quiz']->id,
        'total_score' => 55,
        'status' => 'graded',
        'started_at' => now()->subHours(2),
        'submitted_at' => now()->subHour(),
    ]);

    AssignmentSubmission::create([
        'assignment_id' => $context['assignment']->id,
        'enrollment_id' => $context['enrollment']->id,
        'user_id' => $context['student']->id,
        'attempt_no' => 1,
        'submission_text' => 'Done',
        'attachment_url' => null,
        'status' => 'approved',
        'review_notes' => 'Needs more depth',
        'reviewed_by' => $context['student']->id,
        'submitted_at' => now()->subHour(),
        'reviewed_at' => now()->subMinutes(20),
    ]);

    $enrollment = app(EnrollmentService::class)->syncProgress($context['enrollment']->id)->fresh();

    expect((int) $enrollment->progress)->toBe(50)
        ->and((string) $enrollment->status)->toBe('active')
        ->and($enrollment->completed_at)->toBeNull();
});

it('stores uploaded assignment files for student submissions', function () {
    Storage::fake('public');
    $context = createEnrollmentCompletionContext();

    // Complete the preceding quiz to unlock the assignment
    QuizAttempt::create([
        'enrollment_id' => $context['enrollment']->id,
        'quiz_id' => $context['quiz']->id,
        'total_score' => 80,
        'status' => 'graded',
        'started_at' => now()->subHours(2),
        'submitted_at' => now()->subHour(),
    ]);

    $submission = app(AssignmentService::class)->submitForEnrollment(
        $context['student']->id,
        $context['enrollment']->id,
        $context['assignment']->id,
        [
            'attachment_file' => UploadedFile::fake()->create('jawaban.pdf', 256, 'application/pdf'),
        ],
    );

    expect($submission->attachment_url)->toStartWith('/storage/assignment-submissions/');

    Storage::disk('public')->assertExists(str_replace('/storage/', '', (string) $submission->attachment_url));
});

it('prevents starting a new quiz attempt after the student has passed the quiz', function () {
    $context = createEnrollmentCompletionContext();

    QuizAttempt::create([
        'enrollment_id' => $context['enrollment']->id,
        'quiz_id' => $context['quiz']->id,
        'total_score' => 80,
        'status' => 'graded',
        'started_at' => now()->subHours(2),
        'submitted_at' => now()->subHour(),
    ]);

    try {
        app(QuizAttemptService::class)->startAttemptForUser(
            $context['student']->id,
            $context['enrollment']->id,
            $context['quiz']->id,
        );

        $this->fail('Expected passed quiz to reject new attempts.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['quiz_id'][0] ?? null)->toBe('Quiz is already passed for this enrollment.');
    }
});

it('randomizes and limits quiz questions per attempt', function () {
    $context = createEnrollmentCompletionContext();
    $quiz = $context['quiz'];

    // Update quiz to be randomized and limited to 2 questions
    $quiz->update([
        'is_random' => true,
        'question_limit' => 2,
    ]);

    // Create 5 questions for this quiz
    for ($i = 1; $i <= 5; $i++) {
        \App\Models\Question::create([
            'quiz_id' => $quiz->id,
            'question_text' => "Question {$i}",
            'type' => 'multiple_choice',
            'score' => 20,
            'sort_order' => $i,
            'is_active' => true,
        ]);
    }

    // Start an attempt
    $attempt = app(QuizAttemptService::class)->startAttemptForUser(
        $context['student']->id,
        $context['enrollment']->id,
        $quiz->id
    );

    // Verify that exactly 2 quiz answers were seeded (display limit = 2)
    $seededAnswers = \App\Models\QuizAnswer::where('attempt_id', $attempt->id)->get();
    expect($seededAnswers->count())->toBe(2);

    // Verify that the questions fetched via student quiz detail matches the seeded questions and order
    $detail = app(EnrollmentService::class)->findQuizDetailForUser(
        $context['student']->id,
        $context['enrollment']->id,
        $quiz->id
    );

    $loadedQuestions = $detail['quiz']->questions;
    expect($loadedQuestions->count())->toBe(2);

    $seededQuestionIds = $seededAnswers->pluck('question_id')->all();
    $loadedQuestionIds = $loadedQuestions->pluck('id')->all();

    // Verify that the order matches the answers table exactly
    expect($loadedQuestionIds)->toBe($seededQuestionIds);
});
