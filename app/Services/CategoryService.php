<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Str;

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
        $data['slug'] = Str::slug($data['name']);
        return Category::create($data);
    }

    public function update(int $id, array $data)
    {
        $category = $this->findById($id);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }
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
