<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Support\Str;

class CourseService
{
    public function getAll()
    {
        return Course::latest()->paginate(10);
    }

    public function findById(int $id)
    {
        return Course::findOrFail($id);
    }

    public function create(array $data)
    {
        $data['slug'] = Str::slug($data['title']);
        return Course::create($data);
    }

    public function update(int $id, array $data)
    {
        $course = $this->findById($id);
        if (isset($data['title'])) {
            $data['slug'] = Str::slug($data['title']);
        }
        $course->update($data);

        return $course;
    }

    public function delete(int $id)
    {
        $course = $this->findById($id);

        $course->delete();

        return true;
    }
}
