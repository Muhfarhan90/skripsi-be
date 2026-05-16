<?php

namespace App\Services;

use App\Models\Skill;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SkillService
{
    public function getAll(int $perPage = 10, string $search = '')
    {
        $perPage = max($perPage, 1);

        return Skill::query()
            ->withCount('courses')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findById(int $id): Skill
    {
        return Skill::query()
            ->withCount('courses')
            ->findOrFail($id);
    }

    public function create(array $data): Skill
    {
        $skill = Skill::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $this->findById($skill->id);
    }

    public function update(int $id, array $data): Skill
    {
        $skill = $this->findById($id);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $skill->update($data);

        return $this->findById($skill->id);
    }

    public function delete(int $id): bool
    {
        $skill = $this->findById($id);

        if ($skill->courses()->exists()) {
            throw ValidationException::withMessages([
                'skill' => ['Skill ini masih dipakai oleh course dan tidak bisa dihapus.'],
            ]);
        }

        $skill->delete();

        return true;
    }
}
