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
        return Question::create($data);
    }

    public function update(int $id, array $data)
    {
        $question = $this->findById($id);
        $question->update($data);

        return $question;
    }

    public function delete(int $id)
    {
        $question = $this->findById($id);
        $question->delete();

        return true;
    }
}