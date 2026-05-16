<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Str;

class CategoryService
{
    public function getAll(string $search = '', int $perPage = 10)
    {
        $perPage = max($perPage, 1);

        return Category::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage);
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
