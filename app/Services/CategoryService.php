<?php

namespace App\Services;

use App\Models\Category;

class CategoryService
{
    public function getAll()
    {
        return Category::latest()->paginate(10);
    }

    public function findById(int $id)
    {
        return Category::findOrFail($id);
    }

    public function create(array $data)
    {
        return Category::create($data);
    }

    public function update(int $id, array $data)
    {
        $category = $this->findById($id);

        $category->update($data);

        return $category;
    }

    public function delete(int $id)
    {
        $category = $this->findById($id);

        $category->delete();

        return true;
    }
}
