<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Quiz;
use App\Models\Section;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizService
{
    public function getAll()
    {
        return Quiz::latest()->paginate(10);
    }

    public function getByCourse(int $courseId)
    {
        Course::findOrFail($courseId);

        return Quiz::with('section')
            ->where('course_id', $courseId)
            ->orderByDesc('id')
            ->get();
    }

    public function findById(int $id)
    {
        return Quiz::findOrFail($id);
    }

    public function findByIdWithDetails(int $id)
    {
        return Quiz::with([
            'questions' => function ($query) {
                $query->orderBy('sort_order')->orderBy('id');
            },
            'questions.options' => function ($query) {
                $query->orderBy('id');
            },
        ])->findOrFail($id);
    }

    public function create(array $data)
    {
        $courseId = (int) ($data['course_id'] ?? 0);
        $sectionId = (int) ($data['section_id'] ?? 0);
        if ($courseId <= 0 || $sectionId <= 0) {
            throw ValidationException::withMessages([
                'course_id' => ['course_id and section_id are required'],
            ]);
        }

        return $this->createForCourseSection($courseId, $sectionId, $data);
    }

    public function createForCourseSection(int $courseId, int $sectionId, array $data)
    {
        return DB::transaction(function () use ($courseId, $sectionId, $data) {
            $this->ensureSectionBelongsToCourse($courseId, $sectionId);

            $quizData = array_merge($data, [
                'course_id' => $courseId,
                'section_id' => $sectionId,
            ]);

            return Quiz::create($quizData);
        });
    }

    public function update(int $id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $quiz = $this->findById($id);
            $nextCourseId = isset($data['course_id']) ? (int) $data['course_id'] : (int) $quiz->course_id;
            $nextSectionId = isset($data['section_id']) ? (int) $data['section_id'] : (int) $quiz->section_id;

            $this->ensureSectionBelongsToCourse($nextCourseId, $nextSectionId);

            $quiz->update($data);
            return $quiz->refresh();
        });
    }

    public function updateForCourseSection(int $courseId, int $sectionId, int $quizId, array $data)
    {
        return DB::transaction(function () use ($courseId, $sectionId, $quizId, $data) {
            $quiz = Quiz::where('id', $quizId)
                ->where('course_id', $courseId)
                ->firstOrFail();

            $this->ensureSectionBelongsToCourse($courseId, $sectionId);

            $quiz->update(array_merge($data, [
                'course_id' => $courseId,
                'section_id' => $sectionId,
            ]));
            return $quiz->refresh();
        });
    }

    public function findByIdInCourse(int $courseId, int $quizId)
    {
        return Quiz::where('id', $quizId)
            ->where('course_id', $courseId)
            ->firstOrFail();
    }

    public function findByIdWithDetailsInCourse(int $courseId, int $quizId)
    {
        $quiz = $this->findByIdWithDetails($quizId);
        if ((int) $quiz->course_id !== $courseId) {
            throw ValidationException::withMessages([
                'course_id' => ['Quiz does not belong to the selected course'],
            ]);
        }

        return $quiz;
    }

    public function delete(int $id)
    {
        return DB::transaction(function () use ($id) {
            $quiz = $this->findById($id);

            foreach ($quiz->questions()->get() as $question) {
                $question->options()->delete();
                $question->delete();
            }

            $quiz->delete();

            return true;
        });
    }

    private function ensureSectionBelongsToCourse(int $courseId, int $sectionId): Section
    {
        $section = Section::where('id', $sectionId)
            ->where('course_id', $courseId)
            ->first();

        if (!$section) {
            throw ValidationException::withMessages([
                'section_id' => ['Section does not belong to the selected course'],
            ]);
        }

        return $section;
    }
}
