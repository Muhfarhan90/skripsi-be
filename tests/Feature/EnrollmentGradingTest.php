<?php

use App\Models\AcademicPeriod;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Option;
use App\Models\Question;
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
    ]);

    $assignment = Assignment::create([
        'course_id' => $course->id,
        'section_id' => $section->id,
        'created_by' => $instructor->id,
        'title' => 'Mastery Assignment',
        'description' => null,
        'instructions' => null,
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
    $seededAnswers = \App\Models\QuizAnswer::where('attempt_id', $attempt->id)
        ->orderBy('id')
        ->get();
    expect($seededAnswers->count())->toBe(2)
        ->and($seededAnswers->pluck('question_snapshot.score')->all())->toBe([50, 50]);

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

it('keeps enrollment quiz snapshot stable after quiz master is updated', function () {
    $context = createEnrollmentCompletionContext();
    $quiz = $context['quiz'];

    Question::create([
        'quiz_id' => $quiz->id,
        'question_text' => 'Snapshot Question 1',
        'type' => 'multiple_choice',
        'score' => 50,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    Question::create([
        'quiz_id' => $quiz->id,
        'question_text' => 'Snapshot Question 2',
        'type' => 'multiple_choice',
        'score' => 50,
        'sort_order' => 2,
        'is_active' => true,
    ]);

    app(EnrollmentService::class)->progressSummary($context['student']->id, $context['enrollment']->id);

    $quiz->update([
        'title' => 'Updated Master Quiz',
        'passing_score' => 90,
        'is_random' => true,
        'question_limit' => 1,
    ]);

    $firstQuestion = Question::query()->where('quiz_id', $quiz->id)->orderBy('id')->firstOrFail();
    $firstQuestion->update([
        'question_text' => 'Updated Master Question 1',
    ]);

    Question::create([
        'quiz_id' => $quiz->id,
        'question_text' => 'New Master Question 3',
        'type' => 'multiple_choice',
        'score' => 34,
        'sort_order' => 3,
        'is_active' => true,
    ]);

    $detail = app(EnrollmentService::class)->findQuizDetailForUser(
        $context['student']->id,
        $context['enrollment']->id,
        $quiz->id,
    );

    expect($detail['quiz']->title)->toBe('Mastery Quiz')
        ->and((int) $detail['quiz']->passing_score)->toBe(60)
        ->and($detail['quiz']->questions->pluck('question_text')->all())->toBe([
            'Snapshot Question 1',
            'Snapshot Question 2',
        ]);

    $attempt = app(QuizAttemptService::class)->startAttemptForUser(
        $context['student']->id,
        $context['enrollment']->id,
        $quiz->id,
    );

    $seededAnswers = \App\Models\QuizAnswer::where('attempt_id', $attempt->id)
        ->orderBy('id')
        ->get();

    expect($seededAnswers->count())->toBe(2)
        ->and($seededAnswers->pluck('question_snapshot.score')->all())->toBe([50, 50])
        ->and($seededAnswers->pluck('question_snapshot.question_text')->all())->toBe([
            'Snapshot Question 1',
            'Snapshot Question 2',
        ]);
});

it('uses stored question snapshots when grading attempts after master questions change', function () {
    $context = createEnrollmentCompletionContext();
    $quiz = $context['quiz'];

    $question = Question::create([
        'quiz_id' => $quiz->id,
        'question_text' => 'Original Question',
        'type' => 'multiple_choice',
        'score' => 100,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $correctOption = Option::create([
        'question_id' => $question->id,
        'option_text' => 'Correct Snapshot Option',
        'is_correct' => true,
    ]);

    $wrongOption = Option::create([
        'question_id' => $question->id,
        'option_text' => 'Wrong Snapshot Option',
        'is_correct' => false,
    ]);

    $attempt = app(QuizAttemptService::class)->startAttemptForUser(
        $context['student']->id,
        $context['enrollment']->id,
        $quiz->id,
    );

    $question->update([
        'question_text' => 'Updated Master Question',
    ]);
    $correctOption->update(['is_correct' => false]);
    $wrongOption->update(['is_correct' => true]);

    $answer = app(QuizAttemptService::class)->upsertAnswerForUser(
        $context['student']->id,
        $context['enrollment']->id,
        $quiz->id,
        $attempt->id,
        $question->id,
        [
            'selected_option_id' => $correctOption->id,
        ],
    );

    $detail = app(EnrollmentService::class)->findQuizDetailForUser(
        $context['student']->id,
        $context['enrollment']->id,
        $quiz->id,
    );

    expect($answer->is_correct)->toBeTrue()
        ->and((int) $answer->score)->toBe(100)
        ->and($detail['quiz']->questions->pluck('question_text')->all())->toBe(['Original Question'])
        ->and($detail['quiz']->questions[0]->options->pluck('option_text')->all())->toBe([
            'Correct Snapshot Option',
            'Wrong Snapshot Option',
        ]);
});

it('uses assignment snapshot rules after assignment master changes', function () {
    $context = createEnrollmentCompletionContext();

    QuizAttempt::create([
        'enrollment_id' => $context['enrollment']->id,
        'quiz_id' => $context['quiz']->id,
        'total_score' => 80,
        'status' => 'graded',
        'started_at' => now()->subHours(2),
        'submitted_at' => now()->subHour(),
    ]);

    app(EnrollmentService::class)->progressSummary($context['student']->id, $context['enrollment']->id);

    $context['assignment']->update([
        'title' => 'Updated Master Assignment',
        'allow_resubmission' => false,
        'max_attempts' => 1,
    ]);

    $firstSubmission = app(AssignmentService::class)->submitForEnrollment(
        $context['student']->id,
        $context['enrollment']->id,
        $context['assignment']->id,
        [
            'submission_text' => 'Draft 1',
        ],
    );

    $firstSubmission->update([
        'status' => 'revision_required',
    ]);

    $secondSubmission = app(AssignmentService::class)->submitForEnrollment(
        $context['student']->id,
        $context['enrollment']->id,
        $context['assignment']->id,
        [
            'submission_text' => 'Draft 2',
        ],
    );

    $detail = app(AssignmentService::class)->getAssignmentDetailForEnrollment(
        $context['student']->id,
        $context['enrollment']->id,
        $context['assignment']->id,
    );

    expect($secondSubmission->attempt_no)->toBe(2)
        ->and($detail['assignment']->title)->toBe('Mastery Assignment')
        ->and($detail['assignment']->allow_resubmission)->toBeTrue()
        ->and((int) $detail['assignment']->max_attempts)->toBe(3);
});

it('serves counted lessons from live master with snapshot fallback and keeps supplemental lessons out of progress', function () {
    $context = createEnrollmentCompletionContext();

    $countedLesson = Lesson::create([
        'section_id' => $context['section']->id,
        'title' => 'Intro Snapshot Lesson',
        'description' => 'Snapshot lesson body',
        'type' => 'video',
        'lesson_url' => 'https://example.com/intro',
        'duration' => 10,
        'sort_order' => 1,
        'is_preview' => false,
        'status' => 'published',
    ]);

    app(EnrollmentService::class)->progressSummary($context['student']->id, $context['enrollment']->id);

    $supplementalLesson = Lesson::create([
        'section_id' => $context['section']->id,
        'title' => 'Supplemental Lesson',
        'description' => 'Tambahan materi baru',
        'type' => 'video',
        'lesson_url' => 'https://example.com/supplemental',
        'duration' => 12,
        'sort_order' => 2,
        'is_preview' => false,
        'status' => 'published',
    ]);

    $countedLesson->update([
        'title' => 'Intro Live Lesson',
        'description' => 'Lesson body terbaru',
    ]);

    $curriculum = app(EnrollmentService::class)->getCurriculumCourseForUser(
        $context['student']->id,
        $context['enrollment']->id
    );
    $curriculumLessons = $curriculum->sections->flatMap(fn ($section) => $section->lessons);
    $visibleCountedLesson = $curriculumLessons->firstWhere('id', $countedLesson->id);
    $visibleSupplementalLesson = $curriculumLessons->firstWhere('id', $supplementalLesson->id);
    $summary = app(EnrollmentService::class)->progressSummary($context['student']->id, $context['enrollment']->id);

    expect($visibleCountedLesson)->not->toBeNull()
        ->and($visibleCountedLesson->title)->toBe('Intro Live Lesson')
        ->and($visibleCountedLesson->is_supplemental)->toBeFalse()
        ->and($visibleCountedLesson->is_new)->toBeFalse()
        ->and($visibleCountedLesson->source)->toBe('live')
        ->and($visibleSupplementalLesson)->not->toBeNull()
        ->and($visibleSupplementalLesson->is_supplemental)->toBeTrue()
        ->and($visibleSupplementalLesson->is_new)->toBeTrue()
        ->and($visibleSupplementalLesson->counts_toward_progress)->toBeFalse()
        ->and((int) $summary['total_lessons'])->toBe(1);

    $countedLesson->delete();

    $fallbackDetail = app(EnrollmentService::class)->findLessonDetailForUser(
        $context['student']->id,
        $context['enrollment']->id,
        $countedLesson->id,
    );

    expect($fallbackDetail['lesson']->title)->toBe('Intro Snapshot Lesson')
        ->and($fallbackDetail['lesson']->source)->toBe('snapshot_fallback');
});

it('shows supplemental quizzes for old enrollments, snapshots attempts, and keeps progress contract unchanged', function () {
    $context = createEnrollmentCompletionContext();

    app(EnrollmentService::class)->progressSummary($context['student']->id, $context['enrollment']->id);

    $supplementalQuiz = Quiz::create([
        'course_id' => $context['course']->id,
        'section_id' => $context['section']->id,
        'title' => 'Supplemental Quiz',
        'description' => 'Quiz tambahan untuk siswa lama',
        'duration' => 20,
        'passing_score' => 70,
        'weight' => 100,
        'is_active' => true,
        'is_random' => false,
        'question_limit' => 1,
        'max_attempts' => 2,
    ]);

    $question = Question::create([
        'quiz_id' => $supplementalQuiz->id,
        'question_text' => 'Supplemental question',
        'type' => 'multiple_choice',
        'score' => 100,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $correctOption = Option::create([
        'question_id' => $question->id,
        'option_text' => 'Benar',
        'is_correct' => true,
    ]);

    Option::create([
        'question_id' => $question->id,
        'option_text' => 'Salah',
        'is_correct' => false,
    ]);

    $curriculum = app(EnrollmentService::class)->getCurriculumCourseForUser(
        $context['student']->id,
        $context['enrollment']->id
    );
    $visibleQuiz = $curriculum->sections->flatMap(fn ($section) => $section->quizzes)->firstWhere('id', $supplementalQuiz->id);

    expect($visibleQuiz)->not->toBeNull()
        ->and($visibleQuiz->is_supplemental)->toBeTrue()
        ->and($visibleQuiz->is_new)->toBeTrue()
        ->and($visibleQuiz->counts_toward_progress)->toBeFalse()
        ->and($visibleQuiz->source)->toBe('live');

    $attempt = app(QuizAttemptService::class)->startAttemptForUser(
        $context['student']->id,
        $context['enrollment']->id,
        $supplementalQuiz->id,
    );

    $answer = app(QuizAttemptService::class)->upsertAnswerForUser(
        $context['student']->id,
        $context['enrollment']->id,
        $supplementalQuiz->id,
        $attempt->id,
        $question->id,
        [
            'selected_option_id' => $correctOption->id,
        ],
    );

    app(QuizAttemptService::class)->submitAttemptForUser(
        $context['student']->id,
        $context['enrollment']->id,
        $supplementalQuiz->id,
        $attempt->id,
    );

    $updatedCurriculum = app(EnrollmentService::class)->getCurriculumCourseForUser(
        $context['student']->id,
        $context['enrollment']->id
    );
    $updatedVisibleQuiz = $updatedCurriculum->sections
        ->flatMap(fn ($section) => $section->quizzes)
        ->firstWhere('id', $supplementalQuiz->id);
    $summary = app(EnrollmentService::class)->progressSummary($context['student']->id, $context['enrollment']->id);

    expect($answer->question_snapshot['question_text'] ?? null)->toBe('Supplemental question')
        ->and($updatedVisibleQuiz)->not->toBeNull()
        ->and($updatedVisibleQuiz->is_new)->toBeFalse()
        ->and((int) $summary['total_quizzes'])->toBe(1)
        ->and($summary['passed_quiz_ids'])->not->toContain($supplementalQuiz->id)
        ->and((int) $summary['progress'])->toBe(0);
});

it('shows supplemental assignments for old enrollments, snapshots submissions, and keeps certificate requirements unchanged', function () {
    $context = createEnrollmentCompletionContext();

    app(EnrollmentService::class)->progressSummary($context['student']->id, $context['enrollment']->id);

    $supplementalAssignment = Assignment::create([
        'course_id' => $context['course']->id,
        'section_id' => $context['section']->id,
        'created_by' => $context['course']->instructor_id,
        'title' => 'Supplemental Assignment',
        'description' => 'Assignment tambahan',
        'instructions' => 'Kerjakan jika ingin latihan ekstra.',
        'is_required_for_certificate' => true,
        'allow_resubmission' => true,
        'max_attempts' => 2,
        'status' => 'published',
    ]);

    $detail = app(AssignmentService::class)->getAssignmentDetailForEnrollment(
        $context['student']->id,
        $context['enrollment']->id,
        $supplementalAssignment->id,
    );

    expect($detail['assignment']->is_supplemental)->toBeTrue()
        ->and($detail['assignment']->is_new)->toBeTrue()
        ->and($detail['assignment']->counts_toward_progress)->toBeFalse()
        ->and($detail['assignment']->counts_toward_certificate)->toBeFalse()
        ->and($detail['assignment']->source)->toBe('live');

    $submission = app(AssignmentService::class)->submitForEnrollment(
        $context['student']->id,
        $context['enrollment']->id,
        $supplementalAssignment->id,
        [
            'submission_text' => 'Jawaban tambahan',
        ],
    );

    $updatedDetail = app(AssignmentService::class)->getAssignmentDetailForEnrollment(
        $context['student']->id,
        $context['enrollment']->id,
        $supplementalAssignment->id,
    );
    $summary = app(EnrollmentService::class)->progressSummary($context['student']->id, $context['enrollment']->id);

    expect($submission->assignment_snapshot['title'] ?? null)->toBe('Supplemental Assignment')
        ->and($updatedDetail['assignment']->is_new)->toBeFalse()
        ->and((int) ($summary['assignment_requirement']['required_assignments'] ?? 0))->toBe(1)
        ->and((int) ($summary['assignment_requirement']['approved_assignments'] ?? 0))->toBe(0)
        ->and((int) $summary['progress'])->toBe(0);
});
