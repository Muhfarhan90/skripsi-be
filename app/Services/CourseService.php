<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CourseService
{
    public function getAll(int $perPage = 10, string $search = '')
    {
        $perPage = max($perPage, 1);

        return Course::query()
            ->with([
                'category:id,name',
                'instructor:id,fullname',
                'skills:id,name,slug',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('title', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhereHas('category', function ($categoryQuery) use ($search) {
                            $categoryQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('instructor', function ($userQuery) use ($search) {
                            $userQuery->where('fullname', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('skills', function ($skillQuery) use ($search) {
                            $skillQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('slug', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function getPublishedCatalog()
    {
        return Course::query()
            ->with([
                'category:id,name',
                'instructor:id,fullname',
                'skills:id,name,slug',
                'courseOfferings' => function ($query) {
                    $this->applyPublishedOfferingScope($query);
                    $query->with('academicPeriod');
                },
            ])
            ->whereHas('courseOfferings', function ($query) {
                $this->applyPublishedOfferingScope($query);
            })
            ->latest()
            ->paginate(12);
    }

    public function findById(int $id)
    {
        return Course::query()
            ->with([
                'category:id,name',
                'instructor:id,fullname',
                'skills:id,name,slug',
            ])
            ->findOrFail($id);
    }

    public function findPublishedBySlug(string $slug): Course
    {
        return Course::query()
            ->with([
                'category:id,name',
                'instructor:id,fullname',
                'skills:id,name,slug',
                'courseOfferings' => function ($query) {
                    $this->applyPublishedOfferingScope($query);
                    $query->with('academicPeriod');
                },
            ])
            ->where('slug', $slug)
            ->whereHas('courseOfferings', function ($query) {
                $this->applyPublishedOfferingScope($query);
            })
            ->firstOrFail();
    }

    public function findByIdWithCurriculum(int $id)
    {
        return Course::with([
            'category:id,name',
            'instructor:id,fullname',
            'skills:id,name,slug',
            'sections' => function ($query) {
                $query->orderBy('sort_order')->orderBy('id');
            },
            'sections.lessons' => function ($query) {
                $query->orderBy('sort_order')->orderBy('id');
            },
            'sections.quizzes' => function ($query) {
                $query->orderByDesc('id');
            },
        ])->findOrFail($id);
    }

    public function create(array $data)
    {
        $skillIds = $data['skill_ids'] ?? null;
        unset($data['skill_ids']);

        $data['slug'] = Str::slug($data['title']);
        $course = Course::create($data);
        $this->syncSkills($course, is_array($skillIds) ? $skillIds : null);

        return $course->load([
            'category:id,name',
            'instructor:id,fullname',
            'skills:id,name,slug',
        ]);
    }

    public function update(int $id, array $data)
    {
        $course = $this->findById($id);
        $skillIds = $data['skill_ids'] ?? null;
        unset($data['skill_ids']);

        if (isset($data['title'])) {
            $data['slug'] = Str::slug($data['title']);
        }
        $course->update($data);
        $this->syncSkills($course, is_array($skillIds) ? $skillIds : null);

        return $course->load([
            'category:id,name',
            'instructor:id,fullname',
            'skills:id,name,slug',
        ]);
    }

    protected function syncSkills(Course $course, ?array $skillIds): void
    {
        if ($skillIds === null) {
            return;
        }

        $syncPayload = collect($skillIds)
            ->map(fn ($skillId) => (int) $skillId)
            ->unique()
            ->values()
            ->mapWithKeys(fn ($skillId, $index) => [
                $skillId => ['sort_order' => $index + 1],
            ])
            ->all();

        $course->skills()->sync($syncPayload);
    }

    public function upsertCurriculum(int $courseId, array $data)
    {
        return DB::transaction(function () use ($courseId, $data) {
            $course = $this->findByIdWithCurriculum($courseId);

            $coursePayload = $data['course'] ?? null;
            if (is_array($coursePayload) && count($coursePayload) > 0) {
                $this->update($courseId, $coursePayload);
            }

            if (!array_key_exists('sections', $data)) {
                return $this->findByIdWithCurriculum($courseId);
            }

            $incomingSections = collect($data['sections'] ?? []);
            $existingSections = $course->sections->keyBy('id');
            $keptSectionIds = [];

            foreach ($incomingSections as $sectionIndex => $sectionData) {
                $sectionId = isset($sectionData['id']) ? (int) $sectionData['id'] : null;
                $sectionSortOrder = isset($sectionData['sort_order']) ? (int) $sectionData['sort_order'] : $sectionIndex + 1;

                if ($sectionId !== null && !$existingSections->has($sectionId)) {
                    throw ValidationException::withMessages([
                        'sections' => ['Terdapat section yang tidak terhubung ke course ini.'],
                    ]);
                }

                $section = $sectionId !== null
                    ? $existingSections->get($sectionId)
                    : new Section(['course_id' => $courseId]);

                $section->fill([
                    'title' => $sectionData['title'],
                    'sort_order' => $sectionSortOrder,
                ]);
                $section->course_id = $courseId;
                $section->save();

                $keptSectionIds[] = $section->id;

                $incomingLessons = collect($sectionData['lessons'] ?? []);
                $existingLessons = $section->lessons()->get()->keyBy('id');
                $keptLessonIds = [];

                foreach ($incomingLessons as $lessonIndex => $lessonData) {
                    $lessonId = isset($lessonData['id']) ? (int) $lessonData['id'] : null;
                    $lessonSortOrder = isset($lessonData['sort_order']) ? (int) $lessonData['sort_order'] : $lessonIndex + 1;

                    if ($lessonId !== null && !$existingLessons->has($lessonId)) {
                        throw ValidationException::withMessages([
                            'sections' => ['Terdapat lesson yang tidak terhubung ke section yang dipilih.'],
                        ]);
                    }

                    $lesson = $lessonId !== null
                        ? $existingLessons->get($lessonId)
                        : new Lesson(['section_id' => $section->id]);

                    $lesson->fill([
                        'title' => $lessonData['title'],
                        'description' => $lessonData['description'] ?? null,
                        'type' => $lessonData['type'],
                        'lesson_url' => $lessonData['lesson_url'] ?? null,
                        'duration' => isset($lessonData['duration']) ? (int) $lessonData['duration'] : 0,
                        'sort_order' => $lessonSortOrder,
                        'is_preview' => (bool) ($lessonData['is_preview'] ?? false),
                    ]);
                    $lesson->section_id = $section->id;
                    $lesson->save();

                    $keptLessonIds[] = $lesson->id;
                }

                if (count($keptLessonIds) > 0) {
                    $section->lessons()->whereNotIn('id', $keptLessonIds)->delete();
                } else {
                    $section->lessons()->delete();
                }
            }

            if (count($keptSectionIds) > 0) {
                Section::where('course_id', $courseId)->whereNotIn('id', $keptSectionIds)->delete();
            } else {
                Section::where('course_id', $courseId)->delete();
            }

            return $this->findByIdWithCurriculum($courseId);
        });
    }

    public function delete(int $id)
    {
        $course = $this->findById($id);

        $course->delete();

        return true;
    }

    private function applyPublishedOfferingScope($query): void
    {
        $now = now();

        $query->where('is_active', true)
            ->whereHas('academicPeriod', function ($periodQuery) use ($now) {
                $periodQuery->where('is_active', true)
                    ->where(function ($builder) use ($now) {
                        $builder->whereNull('enrollment_open_at')
                            ->orWhere('enrollment_open_at', '<=', $now);
                    })
                    ->where(function ($builder) use ($now) {
                        $builder->whereNull('enrollment_close_at')
                            ->orWhere('enrollment_close_at', '>=', $now);
                    });
            });
    }
}
