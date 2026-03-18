<?php

namespace App\Services;

use App\Models\Option;

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

    public function create(array $data)
    {
        return Option::create($data);
    }

    public function update(int $id, array $data)
    {
        $option = $this->findById($id);
        $option->update($data);

        return $option;
    }

    public function delete(int $id)
    {
        $option = $this->findById($id);
        $option->delete();

        return true;
    }
}