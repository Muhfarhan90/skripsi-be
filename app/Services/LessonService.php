<?php

namespace App\Services;

use App\Models\Lesson;

class LessonService
{
    public function getAll()
    {
        return Lesson::latest()->paginate(10);
    }

    public function findById(int $id)
    {
        return Lesson::findOrFail($id);
    }

    public function create(array $data)
    {
        if (!isset($data['sort_order']) || $data['sort_order'] == 0) {
            $data['sort_order'] = Lesson::where('section_id', $data['section_id'])
                ->max('sort_order') + 1;
        }

        return Lesson::create($data);
    }

    public function update(int $id, array $data)
    {
        $lesson = $this->findById($id);

        if (isset($data['sort_order']) && $data['sort_order'] != $lesson->sort_order) {
            $this->handleReorder($lesson, $data['sort_order']);
        }

        $lesson->update($data);

        return $lesson;
    }

    private function handleReorder(Lesson $lesson, int $newOrder)
    {
        $oldOrder = $lesson->sort_order;
        $sectionId = $lesson->section_id;

        if ($newOrder > $oldOrder) {
            Lesson::where('section_id', $sectionId)
                ->whereBetween('sort_order', [$oldOrder + 1, $newOrder])
                ->decrement('sort_order');
        } else {
            Lesson::where('section_id', $sectionId)
                ->whereBetween('sort_order', [$newOrder, $oldOrder - 1])
                ->increment('sort_order');
        }
    }

    public function delete(int $id)
    {
        $lesson = $this->findById($id);
        $deletedOrder = $lesson->sort_order;
        $sectionId = $lesson->section_id;

        $lesson->delete();

        // Menggeser agar tidak ada gap urutan setelah data dihapus
        Lesson::where('section_id', $sectionId)
            ->where('sort_order', '>', $deletedOrder)
            ->decrement('sort_order');

        return true;
    }
}