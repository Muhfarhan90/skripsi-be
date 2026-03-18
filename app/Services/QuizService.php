<?php

namespace App\Services;

use App\Models\Quiz;

class QuizService
{
    public function getAll()
    {
        return Quiz::latest()->paginate(10);
    }

    public function findById(int $id)
    {
        return Quiz::findOrFail($id);
    }

    public function create(array $data)
    {
        return Quiz::create($data);
    }

    public function update(int $id, array $data)
    {
        $quiz = $this->findById($id);
        $quiz->update($data);

        return $quiz;
    }

    public function delete(int $id)
    {
        $quiz = $this->findById($id);
        $quiz->delete();

        return true;
    }
}