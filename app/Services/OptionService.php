<?php

namespace App\Services;

use App\Models\Option;
use App\Models\Question;
use Illuminate\Validation\ValidationException;

class OptionService
{
    public function getAll()
    {
        return Option::latest()->paginate(10);
    }

    public function findById(int $id)
    {
        return Option::findOrFail($id);
    }

    public function findByIdInQuestion(int $questionId, int $id): Option
    {
        return Option::where('id', $id)
            ->where('question_id', $questionId)
            ->firstOrFail();
    }

    public function create(array $data)
    {
        return Option::create($data);
    }

    public function createForQuestion(int $questionId, array $data): Option
    {
        Question::findOrFail($questionId);

        return Option::create(array_merge($data, [
            'question_id' => $questionId,
        ]));
    }

    public function update(int $id, array $data)
    {
        $option = $this->findById($id);
        $option->update($data);

        return $option;
    }

    public function updateForQuestion(int $questionId, int $optionId, array $data): Option
    {
        $option = $this->findByIdInQuestion($questionId, $optionId);

        if (array_key_exists('question_id', $data) && (int) $data['question_id'] !== $questionId) {
            throw ValidationException::withMessages([
                'question_id' => ['Option must stay within the selected question'],
            ]);
        }

        $option->update($data);
        return $option;
    }

    public function delete(int $id)
    {
        $option = $this->findById($id);
        $option->delete();

        return true;
    }

    public function deleteForQuestion(int $questionId, int $optionId): bool
    {
        $option = $this->findByIdInQuestion($questionId, $optionId);
        $option->delete();

        return true;
    }
}
