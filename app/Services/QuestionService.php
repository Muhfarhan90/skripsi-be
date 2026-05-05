<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestionService
{
    public function getAll()
    {
        return Question::latest()->paginate(10);
    }

    public function getByQuiz(int $quizId)
    {
        Quiz::findOrFail($quizId);

        return Question::with('options')
            ->where('quiz_id', $quizId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function findById(int $id)
    {
        return Question::findOrFail($id);
    }

    public function findByIdInQuiz(int $quizId, int $id): Question
    {
        return Question::where('id', $id)
            ->where('quiz_id', $quizId)
            ->firstOrFail();
    }

    public function create(array $data)
    {
        $quizId = (int) ($data['quiz_id'] ?? 0);
        Quiz::findOrFail($quizId);

        if (!isset($data['sort_order']) || $data['sort_order'] == 0) {
            $data['sort_order'] = Question::where('quiz_id', $quizId)
                ->max('sort_order') + 1;
        }

        return DB::transaction(function () use ($data, $quizId) {
            $question = Question::create($data);
            $this->rebalanceScores($quizId);
            return $question->refresh();
        });
    }

    public function createForQuiz(int $quizId, array $data): Question
    {
        Quiz::findOrFail($quizId);

        return DB::transaction(function () use ($quizId, $data) {
            $payload = array_merge($data, [
                'quiz_id' => $quizId,
            ]);

            if (!isset($payload['sort_order']) || (int) $payload['sort_order'] <= 0) {
                $payload['sort_order'] = Question::where('quiz_id', $quizId)->max('sort_order') + 1;
            }

            $question = Question::create($payload);
            $this->rebalanceScores($quizId);

            return $question->refresh();
        });
    }

    public function update(int $id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $question = $this->findById($id);

            if (isset($data['sort_order']) && $data['sort_order'] != $question->sort_order) {
                $this->handleReorder($question, $data['sort_order']);
            }

            $question->update($data);
            $this->rebalanceScores((int) $question->quiz_id);

            return $question->refresh();
        });
    }

    public function updateForQuiz(int $quizId, int $questionId, array $data): Question
    {
        return DB::transaction(function () use ($quizId, $questionId, $data) {
            $question = $this->findByIdInQuiz($quizId, $questionId);

            if (array_key_exists('quiz_id', $data) && (int) $data['quiz_id'] !== $quizId) {
                throw ValidationException::withMessages([
                    'quiz_id' => ['Question must stay within the selected quiz'],
                ]);
            }

            if (isset($data['sort_order']) && (int) $data['sort_order'] !== (int) $question->sort_order) {
                $this->handleReorder($question, (int) $data['sort_order']);
            }

            $question->update($data);
            $this->rebalanceScores($quizId);

            return $question->refresh();
        });
    }

    public function reorderForQuiz(int $quizId, array $questionIds): void
    {
        Quiz::findOrFail($quizId);

        $normalizedIds = array_values(array_map('intval', $questionIds));
        if (count($normalizedIds) === 0) {
            throw ValidationException::withMessages([
                'question_ids' => ['Question ids are required'],
            ]);
        }

        $existingIds = Question::where('quiz_id', $quizId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        sort($existingIds);
        $submittedIds = $normalizedIds;
        sort($submittedIds);

        if ($existingIds !== $submittedIds) {
            throw ValidationException::withMessages([
                'question_ids' => ['Submitted question ids must match all questions in this quiz'],
            ]);
        }

        DB::transaction(function () use ($quizId, $normalizedIds) {
            foreach ($normalizedIds as $index => $questionId) {
                Question::where('id', $questionId)
                    ->where('quiz_id', $quizId)
                    ->update(['sort_order' => $index + 1]);
            }

            $this->rebalanceScores($quizId);
        });
    }

    private function handleReorder(Question $question, int $newOrder)
    {
        $oldOrder = $question->sort_order;
        $quizId = $question->quiz_id;

        if ($newOrder > $oldOrder) {
            Question::where('quiz_id', $quizId)
                ->whereBetween('sort_order', [$oldOrder + 1, $newOrder])
                ->decrement('sort_order');
        } else {
            Question::where('quiz_id', $quizId)
                ->whereBetween('sort_order', [$newOrder, $oldOrder - 1])
                ->increment('sort_order');
        }
    }

    public function delete(int $id)
    {
        return DB::transaction(function () use ($id) {
            $question = $this->findById($id);
            $question->options()->delete();
            $deletedOrder = $question->sort_order;
            $quizId = (int) $question->quiz_id;

            $question->delete();

            // Menggeser agar tidak ada gap urutan setelah data dihapus
            Question::where('quiz_id', $quizId)
                ->where('sort_order', '>', $deletedOrder)
                ->decrement('sort_order');

            $this->rebalanceScores($quizId);

            return true;
        });
    }

    public function deleteForQuiz(int $quizId, int $questionId): bool
    {
        return DB::transaction(function () use ($quizId, $questionId) {
            $question = $this->findByIdInQuiz($quizId, $questionId);
            $question->options()->delete();
            $deletedOrder = (int) $question->sort_order;
            $question->delete();

            Question::where('quiz_id', $quizId)
                ->where('sort_order', '>', $deletedOrder)
                ->decrement('sort_order');

            $this->rebalanceScores($quizId);

            return true;
        });
    }

    private function rebalanceScores(int $quizId): void
    {
        $questions = Question::where('quiz_id', $quizId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $total = $questions->count();
        if ($total === 0) {
            return;
        }

        $baseScore = intdiv(100, $total);
        $remainder = 100 % $total;

        foreach ($questions as $index => $question) {
            $score = $baseScore + ($index < $remainder ? 1 : 0);

            if ((int) $question->score !== $score) {
                $question->update(['score' => $score]);
            }
        }
    }
}
