<?php

namespace App\Services;

use App\Models\Section;

class SectionService
{
    public function getAll()
    {
        return Section::latest()->paginate(10);
    }

    public function findById(int $id)
    {
        return Section::findOrFail($id);
    }

    public function create(array $data)
    {
        if (!isset($data['sort_order']) || $data['sort_order'] == 0) {
            $data['sort_order'] = Section::where('course_id', $data['course_id'])
                ->max('sort_order') + 1;
        }

        return Section::create($data);
    }

    public function update(int $id, array $data)
    {
        $section = $this->findById($id);

        if (isset($data['sort_order']) && $data['sort_order'] != $section->sort_order) {
            $this->handleReorder($section, $data['sort_order']);
        }

        $section->update($data);

        return $section;
    }

    private function handleReorder(Section $section, int $newOrder)
    {
        $oldOrder = $section->sort_order;
        $courseId = $section->course_id;

        if ($newOrder > $oldOrder) {
            Section::where('course_id', $courseId)
                ->whereBetween('sort_order', [$oldOrder + 1, $newOrder])
                ->decrement('sort_order');
        } else {
            Section::where('course_id', $courseId)
                ->whereBetween('sort_order', [$newOrder, $oldOrder - 1])
                ->increment('sort_order');
        }
    }

    public function delete(int $id)
    {
        $section = $this->findById($id);
        $deletedOrder = $section->sort_order;
        $courseId = $section->course_id;

        $section->delete();

        // Menggeser agar tidak ada gap urutan setelah data dihapus
        Section::where('course_id', $courseId)
            ->where('sort_order', '>', $deletedOrder)
            ->decrement('sort_order');

        return true;
    }
}