<?php

namespace App\Services;

use App\Models\Question;

class QuestionService
{
    public function getAll()
    {
        return Question::latest()->paginate(10);
    }

    public function findById(int $id)
    {
        return Question::findOrFail($id);
    }

    public function create(array $data)
    {
        if (!isset($data['sort_order']) || $data['sort_order'] == 0) {
            $data['sort_order'] = Question::where('quiz_id', $data['quiz_id'])
                ->max('sort_order') + 1;
        }

        return Question::create($data);
    }

    public function update(int $id, array $data)
    {
        $question = $this->findById($id);

        if (isset($data['sort_order']) && $data['sort_order'] != $question->sort_order) {
            $this->handleReorder($question, $data['sort_order']);
        }

        $question->update($data);

        return $question;
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
        $question = $this->findById($id);
        $deletedOrder = $question->sort_order;
        $quizId = $question->quiz_id;

        $question->delete();

        // Menggeser agar tidak ada gap urutan setelah data dihapus
        Question::where('quiz_id', $quizId)
            ->where('sort_order', '>', $deletedOrder)
            ->decrement('sort_order');

        return true;
    }
}