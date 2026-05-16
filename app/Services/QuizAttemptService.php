<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizAttemptService
{
    private const RETAKE_COOLDOWN_MINUTES = 5;

    protected EnrollmentService $enrollmentService;

    public function __construct(EnrollmentService $enrollmentService)
    {
        $this->enrollmentService = $enrollmentService;
    }

    public function getAttemptsByQuizForUser(int $userId, int $enrollmentId, int $quizId)
    {
        $enrollment = $this->findEnrollmentForUser($userId, $enrollmentId);
        $this->enrollmentService->assertCanReadMaterial($enrollment);
        $this->findQuizForEnrollment($enrollmentId, $quizId, true);

        return QuizAttempt::where('enrollment_id', $enrollmentId)
            ->where('quiz_id', $quizId)
            ->latest()
            ->paginate(10);
    }

    public function startAttemptForUser(int $userId, int $enrollmentId, int $quizId): QuizAttempt
    {
        $enrollment = $this->findEnrollmentForUser($userId, $enrollmentId);
        $this->enrollmentService->assertCanWriteLearning($enrollment);
        $quiz = $this->findQuizForEnrollment($enrollmentId, $quizId, false);
        $this->assertQuizIsOpenForAttempt($quiz);

        $inProgress = QuizAttempt::where('enrollment_id', $enrollmentId)
            ->where('quiz_id', $quizId)
            ->where('status', 'in_progress')
            ->exists();

        if ($inProgress) {
            throw ValidationException::withMessages([
                'quiz_id' => ['There is already an in-progress attempt for this quiz'],
            ]);
        }

        $latestCompletedAttempt = QuizAttempt::where('enrollment_id', $enrollmentId)
            ->where('quiz_id', $quizId)
            ->whereIn('status', ['submitted', 'graded'])
            ->latest('submitted_at')
            ->latest('id')
            ->first();

        $this->assertRetakeCooldownHasPassed($latestCompletedAttempt);

        $attemptCount = QuizAttempt::where('enrollment_id', $enrollmentId)
            ->where('quiz_id', $quizId)
            ->count();

        if ((int) $quiz->max_attempts > 0 && $attemptCount >= (int) $quiz->max_attempts) {
            throw ValidationException::withMessages([
                'quiz_id' => ['Maximum attempt limit reached for this quiz'],
            ]);
        }

        return QuizAttempt::create([
            'enrollment_id' => $enrollment->id,
            'quiz_id' => $quiz->id,
            'status' => 'in_progress',
            'total_score' => 0,
            'started_at' => now(),
        ]);
    }

    public function findAttemptForUser(int $userId, int $enrollmentId, int $quizId, int $attemptId): QuizAttempt
    {
        $enrollment = $this->findEnrollmentForUser($userId, $enrollmentId);
        $this->enrollmentService->assertCanReadMaterial($enrollment);
        $this->findQuizForEnrollment($enrollmentId, $quizId, true);

        return QuizAttempt::with('answers')
            ->where('id', $attemptId)
            ->where('enrollment_id', $enrollmentId)
            ->where('quiz_id', $quizId)
            ->firstOrFail();
    }

    public function upsertAnswerForUser(
        int $userId,
        int $enrollmentId,
        int $quizId,
        int $attemptId,
        int $questionId,
        array $data
    ): QuizAnswer {
        $enrollment = $this->findEnrollmentForUser($userId, $enrollmentId);
        $this->enrollmentService->assertCanWriteLearning($enrollment);
        $quiz = $this->findQuizForEnrollment($enrollmentId, $quizId, true);
        $this->assertQuizIsOpenForAttempt($quiz);
        $attempt = $this->findAttemptForUser($userId, $enrollmentId, $quizId, $attemptId);

        if ($attempt->status !== 'in_progress') {
            throw ValidationException::withMessages([
                'attempt_id' => ['Only in-progress attempt can be answered'],
            ]);
        }

        $this->assertAttemptWithinDuration($attempt, $quiz);

        return $this->persistAnswer($attempt, $quizId, $questionId, $data);
    }

    public function submitAttemptForUser(int $userId, int $enrollmentId, int $quizId, int $attemptId): QuizAttempt
    {
        $enrollment = $this->findEnrollmentForUser($userId, $enrollmentId);
        $this->enrollmentService->assertCanWriteLearning($enrollment);
        $quiz = $this->findQuizForEnrollment($enrollmentId, $quizId, true);
        $this->assertQuizIsOpenForAttempt($quiz);
        $attempt = $this->findAttemptForUser($userId, $enrollmentId, $quizId, $attemptId);

        if ($attempt->status !== 'in_progress') {
            throw ValidationException::withMessages([
                'attempt_id' => ['Attempt is already submitted'],
            ]);
        }

        $answerCount = QuizAnswer::where('attempt_id', $attempt->id)->count();
        if ($answerCount === 0) {
            throw ValidationException::withMessages([
                'attempt_id' => ['Attempt cannot be submitted without answers'],
            ]);
        }

        return DB::transaction(function () use ($attempt) {
            $totalScore = (int) QuizAnswer::where('attempt_id', $attempt->id)->sum('score');
            $hasManualReview = QuizAnswer::query()
                ->join('questions', 'questions.id', '=', 'quiz_answers.question_id')
                ->where('quiz_answers.attempt_id', $attempt->id)
                ->whereIn('questions.type', ['short_answer', 'essay'])
                ->exists();

            $attempt->update([
                'total_score' => $totalScore,
                'status' => $hasManualReview ? 'submitted' : 'graded',
                'submitted_at' => now(),
            ]);

            return $attempt->fresh('answers');
        });
    }

    public function getAttemptsByQuizForAdmin(int $quizId)
    {
        Quiz::findOrFail($quizId);

        return QuizAttempt::where('quiz_id', $quizId)
            ->latest()
            ->paginate(10);
    }

    public function findAttemptForAdmin(int $quizId, int $attemptId): QuizAttempt
    {
        Quiz::findOrFail($quizId);

        return QuizAttempt::with('answers')
            ->where('id', $attemptId)
            ->where('quiz_id', $quizId)
            ->firstOrFail();
    }

    public function gradeAnswerForAdmin(int $quizId, int $attemptId, int $questionId, array $data): QuizAnswer
    {
        $attempt = $this->findAttemptForAdmin($quizId, $attemptId);

        if ($attempt->status === 'in_progress') {
            throw ValidationException::withMessages([
                'attempt_id' => ['Cannot grade an in-progress attempt'],
            ]);
        }

        $question = Question::where('id', $questionId)
            ->where('quiz_id', $quizId)
            ->firstOrFail();

        if (! in_array((string) $question->type, ['short_answer', 'essay'], true)) {
            throw ValidationException::withMessages([
                'question_id' => ['Manual grading is only for short_answer or essay question'],
            ]);
        }

        return DB::transaction(function () use ($attempt, $question, $data) {
            $answer = QuizAnswer::where('attempt_id', $attempt->id)
                ->where('question_id', $question->id)
                ->firstOrFail();

            $maxScore = (int) $question->score;
            $score = min((int) $data['score'], $maxScore);

            $answer->update([
                'is_correct' => (bool) $data['is_correct'],
                'score' => $score,
            ]);

            $totalScore = (int) QuizAnswer::where('attempt_id', $attempt->id)->sum('score');
            $pendingManualReview = QuizAnswer::query()
                ->join('questions', 'questions.id', '=', 'quiz_answers.question_id')
                ->where('quiz_answers.attempt_id', $attempt->id)
                ->whereIn('questions.type', ['short_answer', 'essay'])
                ->whereNull('quiz_answers.is_correct')
                ->exists();

            $attempt->update([
                'total_score' => $totalScore,
                'status' => $pendingManualReview ? 'submitted' : 'graded',
            ]);

            return $answer->fresh();
        });
    }

    private function persistAnswer(QuizAttempt $attempt, int $quizId, int $questionId, array $data): QuizAnswer
    {
        $question = Question::where('id', $questionId)
            ->where('quiz_id', $quizId)
            ->where('is_active', true)
            ->firstOrFail();

        return DB::transaction(function () use ($attempt, $question, $data) {
            $selectedOptionId = $data['selected_option_id'] ?? null;
            $answerText = $data['answer_text'] ?? null;
            $isCorrect = null;
            $score = 0;

            if ($selectedOptionId) {
                $option = Option::where('id', $selectedOptionId)
                    ->where('question_id', $question->id)
                    ->firstOrFail();

                $isCorrect = (bool) $option->is_correct;
                $score = $isCorrect ? (int) $question->score : 0;
            }

            if ($answerText && ! in_array((string) $question->type, ['short_answer', 'essay'], true)) {
                $isCorrect = false;
                $score = 0;
            }

            return QuizAnswer::updateOrCreate(
                [
                    'attempt_id' => $attempt->id,
                    'question_id' => $question->id,
                ],
                [
                    'selected_option_id' => $selectedOptionId,
                    'answer_text' => $answerText,
                    'is_correct' => $isCorrect,
                    'score' => $score,
                ]
            );
        });
    }

    private function findEnrollmentForUser(int $userId, int $enrollmentId): Enrollment
    {
        return Enrollment::where('id', $enrollmentId)
            ->where('user_id', $userId)
            ->firstOrFail();
    }

    private function findQuizForEnrollment(int $enrollmentId, int $quizId, bool $allowInactive): Quiz
    {
        $enrollment = Enrollment::with('courseOffering')->findOrFail($enrollmentId);
        $courseId = $enrollment->courseOffering?->course_id;
        if (! $courseId) {
            throw ValidationException::withMessages([
                'course_offering_id' => ['Enrollment is missing a valid course offering reference.'],
            ]);
        }

        $query = Quiz::where('id', $quizId)
            ->where('course_id', $courseId);

        if (! $allowInactive) {
            $query->where('is_active', true);
        }

        return $query->firstOrFail();
    }

    private function assertQuizIsOpenForAttempt(Quiz $quiz): void
    {
        $now = now();

        if ($quiz->open_at && $now->lt($quiz->open_at)) {
            throw ValidationException::withMessages([
                'quiz_id' => ['Quiz is not open yet. Please wait until the quiz open time.'],
            ]);
        }

        if ($quiz->close_at && $now->gt($quiz->close_at)) {
            throw ValidationException::withMessages([
                'quiz_id' => ['Quiz has closed. New attempts or submissions are no longer allowed.'],
            ]);
        }
    }

    private function assertAttemptWithinDuration(QuizAttempt $attempt, Quiz $quiz): void
    {
        $duration = (int) ($quiz->duration ?? 0);
        if ($duration <= 0 || ! $attempt->started_at) {
            return;
        }

        $deadline = $attempt->started_at->copy()->addMinutes($duration);
        if (now()->lte($deadline)) {
            return;
        }

        throw ValidationException::withMessages([
            'attempt_id' => ['Quiz time is over. Answers can no longer be changed.'],
        ]);
    }

    private function assertRetakeCooldownHasPassed(?QuizAttempt $attempt): void
    {
        if (! $attempt) {
            return;
        }

        $submittedAt = $attempt->submitted_at ?? $attempt->updated_at;
        if (! $submittedAt) {
            return;
        }

        $availableAt = $submittedAt->copy()->addMinutes(self::RETAKE_COOLDOWN_MINUTES);
        if (now()->gte($availableAt)) {
            return;
        }

        throw ValidationException::withMessages([
            'quiz_id' => [
                sprintf(
                    'Please wait until %s before starting a new attempt.',
                    $availableAt->utc()->format('Y-m-d\TH:i:s\Z')
                ),
            ],
        ]);
    }
}
