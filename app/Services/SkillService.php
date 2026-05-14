<?php

namespace App\Services;

use App\Models\Skill;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SkillService
{
    public function getAll(int $perPage = 10)
    {
        $safePerPage = $perPage > 0 ? min($perPage, 1000) : 10;

        return Skill::query()
            ->withCount('courses')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate($safePerPage);
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
