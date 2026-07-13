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
    protected CourseSnapshotService $courseSnapshotService;

    public function __construct(EnrollmentService $enrollmentService, CourseSnapshotService $courseSnapshotService)
    {
        $this->enrollmentService = $enrollmentService;
        $this->courseSnapshotService = $courseSnapshotService;
    }

    public function getAttemptsByQuizForUser(int $userId, int $enrollmentId, int $quizId)
    {
        $enrollment = $this->findEnrollmentForUser($userId, $enrollmentId);
        $this->enrollmentService->assertCanReadMaterial($enrollment);
        $this->enrollmentService->assertQuizUnlockedForEnrollment($enrollment, $quizId);
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
        $this->enrollmentService->assertQuizUnlockedForEnrollment($enrollment, $quizId);
        $quiz = $this->findQuizForEnrollment($enrollmentId, $quizId, false);
        $this->assertQuizIsNotPassed($enrollment, $quiz);

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

        $attempt = QuizAttempt::create([
            'enrollment_id' => $enrollment->id,
            'quiz_id' => $quiz->id,
            'status' => 'in_progress',
            'total_score' => 0,
            'started_at' => now(),
        ]);

        $quizSnapshot = $this->requireQuizSnapshotForEnrollment($enrollment, $quizId);
        $questions = collect($quizSnapshot['questions'] ?? [])
            ->filter(fn ($question) => is_array($question) && (bool) ($question['is_active'] ?? false))
            ->sort(function (array $left, array $right): int {
                $sortOrderComparison = ((int) ($left['sort_order'] ?? 0)) <=> ((int) ($right['sort_order'] ?? 0));

                if ($sortOrderComparison !== 0) {
                    return $sortOrderComparison;
                }

                return ((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0));
            })
            ->values();

        if ($quiz->is_random) {
            $questions = $questions->shuffle();
        }

        if ($quiz->question_limit && $quiz->question_limit > 0) {
            $questions = $questions->take($quiz->question_limit);
        }

        $questions = $this->normalizeQuestionScoresForAttempt($questions);

        foreach ($questions as $question) {
            QuizAnswer::create([
                'attempt_id' => $attempt->id,
                'question_id' => (int) $question['id'],
                'selected_option_id' => null,
                'answer_text' => null,
                'is_correct' => null,
                'score' => 0,
                'question_snapshot' => $question,
            ]);
        }

        return $attempt;
    }

    public function findAttemptForUser(int $userId, int $enrollmentId, int $quizId, int $attemptId): QuizAttempt
    {
        $enrollment = $this->findEnrollmentForUser($userId, $enrollmentId);
        $this->enrollmentService->assertCanReadMaterial($enrollment);
        $this->enrollmentService->assertQuizUnlockedForEnrollment($enrollment, $quizId);
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

        $submittedAttempt = DB::transaction(function () use ($attempt) {
            $answers = QuizAnswer::where('attempt_id', $attempt->id)->get();
            $totalScore = (int) $answers->sum('score');
            $hasManualReview = $answers->contains(function (QuizAnswer $answer): bool {
                $questionSnapshot = $this->resolveQuestionSnapshotForAnswer($answer, null);
                $questionType = (string) ($questionSnapshot['type'] ?? '');

                return in_array($questionType, ['short_answer', 'essay'], true);
            });

            $attempt->update([
                'total_score' => $totalScore,
                'status' => $hasManualReview ? 'submitted' : 'graded',
                'submitted_at' => now(),
            ]);

            return $attempt->fresh('answers');
        });

        $this->enrollmentService->syncProgress($enrollment->id);

        return $submittedAttempt->fresh('answers');
    }

    public function getAttemptsByQuizForAdmin(int $quizId)
    {
        Quiz::withTrashed()->findOrFail($quizId);

        return QuizAttempt::where('quiz_id', $quizId)
            ->latest()
            ->paginate(10);
    }

    public function findAttemptForAdmin(int $quizId, int $attemptId): QuizAttempt
    {
        Quiz::withTrashed()->findOrFail($quizId);

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

        $answer = QuizAnswer::where('attempt_id', $attempt->id)
            ->where('question_id', $questionId)
            ->firstOrFail();
        $questionSnapshot = $this->resolveQuestionSnapshotForAnswer($answer, null);
        $questionType = (string) ($questionSnapshot['type'] ?? '');

        if (! in_array($questionType, ['short_answer', 'essay'], true)) {
            throw ValidationException::withMessages([
                'question_id' => ['Manual grading is only for short_answer or essay question'],
            ]);
        }

        $answer = DB::transaction(function () use ($attempt, $answer, $questionSnapshot, $data) {
            $maxScore = (int) ($questionSnapshot['score'] ?? 0);
            $score = min((int) $data['score'], $maxScore);

            $answer->update([
                'is_correct' => (bool) $data['is_correct'],
                'score' => $score,
            ]);

            $answers = QuizAnswer::where('attempt_id', $attempt->id)->get();
            $totalScore = (int) $answers->sum('score');
            $pendingManualReview = $answers->contains(function (QuizAnswer $item): bool {
                $questionSnapshot = $this->resolveQuestionSnapshotForAnswer($item, null);
                $questionType = (string) ($questionSnapshot['type'] ?? '');

                return in_array($questionType, ['short_answer', 'essay'], true)
                    && $item->is_correct === null;
            });

            $attempt->update([
                'total_score' => $totalScore,
                'status' => $pendingManualReview ? 'submitted' : 'graded',
            ]);

            return $answer->fresh();
        });

        if ($attempt->enrollment_id) {
            $this->enrollmentService->syncProgress((int) $attempt->enrollment_id);
        }

        return $answer;
    }

    private function persistAnswer(QuizAttempt $attempt, int $quizId, int $questionId, array $data): QuizAnswer
    {
        $answer = QuizAnswer::where('attempt_id', $attempt->id)
            ->where('question_id', $questionId)
            ->firstOrFail();
        $questionSnapshot = $this->resolveQuestionSnapshotForAnswer($answer, null);

        return DB::transaction(function () use ($answer, $questionSnapshot, $data) {
            $selectedOptionId = $data['selected_option_id'] ?? null;
            $answerText = $data['answer_text'] ?? null;
            $isCorrect = null;
            $score = 0;

            if ($selectedOptionId) {
                $option = $this->courseSnapshotService->findOptionSnapshot($questionSnapshot, (int) $selectedOptionId);
                if (! $option) {
                    throw ValidationException::withMessages([
                        'selected_option_id' => ['Selected option does not belong to this question.'],
                    ]);
                }

                $isCorrect = (bool) ($option['is_correct'] ?? false);
                $score = $isCorrect ? (int) ($questionSnapshot['score'] ?? 0) : 0;
            }

            if ($answerText && ! in_array((string) ($questionSnapshot['type'] ?? ''), ['short_answer', 'essay'], true)) {
                $isCorrect = false;
                $score = 0;
            }

            $answer->update([
                'selected_option_id' => $selectedOptionId,
                'answer_text' => $answerText,
                'is_correct' => $isCorrect,
                'score' => $score,
            ]);

            return $answer->fresh();
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
        $enrollment = Enrollment::findOrFail($enrollmentId);
        $quiz = $this->enrollmentService->findVisibleQuizForEnrollment($enrollment, $quizId, true, $allowInactive);
        if (! $quiz) {
            throw ValidationException::withMessages([
                'quiz_id' => ['Quiz tidak tersedia untuk enrollment ini.'],
            ]);
        }

        Quiz::withTrashed()->findOrFail($quizId);

        return $quiz;
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

    private function assertQuizIsNotPassed(Enrollment $enrollment, Quiz $quiz): void
    {
        $attemptQuery = QuizAttempt::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('quiz_id', $quiz->id)
            ->where('status', 'graded');

        if ($quiz->passing_score !== null) {
            $attemptQuery->where('total_score', '>=', (int) $quiz->passing_score);
        }

        if (! $attemptQuery->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'quiz_id' => ['Quiz is already passed for this enrollment.'],
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

    /**
     * @return array<string, mixed>
     */
    private function requireQuizSnapshotForEnrollment(Enrollment $enrollment, int $quizId): array
    {
        $quizSnapshot = $this->enrollmentService->findQuizSnapshotForEnrollment($enrollment, $quizId);

        if ($quizSnapshot !== null) {
            return $quizSnapshot;
        }

        $quiz = $this->enrollmentService->findVisibleQuizForEnrollment($enrollment, $quizId, true, true);
        if (! $quiz) {
            throw ValidationException::withMessages([
                'quiz_id' => ['Quiz tidak tersedia untuk enrollment ini.'],
            ]);
        }

        return $this->courseSnapshotService->makeQuizSnapshotFromModel($quiz);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveQuestionSnapshotForAnswer(QuizAnswer $answer, ?array $quizSnapshot = null): array
    {
        if (is_array($answer->question_snapshot)) {
            return $answer->question_snapshot;
        }

        if ($quizSnapshot !== null) {
            $questionSnapshot = $this->courseSnapshotService->findQuestionSnapshot($quizSnapshot, (int) $answer->question_id);
            if ($questionSnapshot !== null) {
                return $questionSnapshot;
            }
        }

        $question = Question::withTrashed()
            ->with(['options' => fn ($query) => $query->withTrashed()->orderBy('id')])
            ->findOrFail((int) $answer->question_id);

        return [
            'id' => (int) $question->id,
            'quiz_id' => (int) $question->quiz_id,
            'question_text' => $question->question_text,
            'image_url' => $question->image_url,
            'type' => $question->type,
            'score' => (int) ($question->score ?? 0),
            'sort_order' => $question->sort_order !== null ? (int) $question->sort_order : null,
            'is_active' => (bool) $question->is_active,
            'options' => $question->options
                ->map(fn (Option $option) => [
                    'id' => (int) $option->id,
                    'question_id' => (int) $option->question_id,
                    'option_text' => $option->option_text,
                    'image_url' => $option->image_url,
                    'is_correct' => (bool) $option->is_correct,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $questions
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function normalizeQuestionScoresForAttempt(\Illuminate\Support\Collection $questions): \Illuminate\Support\Collection
    {
        $totalQuestions = $questions->count();
        if ($totalQuestions === 0) {
            return $questions->values();
        }

        $baseScore = intdiv(100, $totalQuestions);
        $remainder = 100 % $totalQuestions;

        return $questions
            ->values()
            ->map(function (array $question, int $index) use ($baseScore, $remainder): array {
                $question['score'] = $baseScore + ($index < $remainder ? 1 : 0);

                return $question;
            });
    }
}
