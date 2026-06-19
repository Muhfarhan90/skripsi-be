<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class CourseService
{
    public function getAll(int $perPage = 10, string $search = '')
    {
        $perPage = max($perPage, 1);

        $query = Course::query()
            ->with([
                'category:id,name',
                'instructor:id,fullname',
                'skills:id,name,slug',
            ]);

        $user = auth()->user();
        if ($user && $user->role_name === 'instructor') {
            $query->where('instructor_id', $user->id);
        }

        return $query->when($search !== '', function ($query) use ($search) {
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
                'instructor:id,fullname,bio,avatar',
                'skills:id,name,slug',
                'courseOfferings' => function ($query) {
                    $this->applyPublishedOfferingScope($query);
                    $query->with('academicPeriod');
                },
            ])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->whereHas('courseOfferings', function ($query) {
                $this->applyPublishedOfferingScope($query);
            })
            ->latest()
            ->paginate(12);
    }

    public function findById(int $id)
    {
        $query = Course::query()
            ->with([
                'category:id,name',
                'instructor:id,fullname',
                'skills:id,name,slug',
            ]);

        $user = auth()->user();
        if ($user && $user->role_name === 'instructor') {
            $query->where('instructor_id', $user->id);
        }

        return $query->findOrFail($id);
    }

    public function findPublishedBySlug(string $slug): Course
    {
        return Course::query()
            ->with([
                'category:id,name',
                'instructor:id,fullname,bio,avatar',
                'skills:id,name,slug',
                'courseOfferings' => function ($query) {
                    $this->applyPublishedOfferingScope($query);
                    $query->with('academicPeriod');
                },
                'sections' => function ($query) {
                    $query->orderBy('sort_order')->orderBy('id');
                },
                'sections.lessons' => function ($query) {
                    $query->where('status', 'published')->orderBy('sort_order')->orderBy('id');
                },
                'sections.quizzes' => function ($query) {
                    $query->orderByDesc('id');
                },
                'sections.assignments' => function ($query) {
                    $query->orderBy('due_at')->orderBy('id');
                },
            ])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->where('slug', $slug)
            ->whereHas('courseOfferings', function ($query) {
                $this->applyPublishedOfferingScope($query);
            })
            ->firstOrFail();
    }

    public function findByIdWithCurriculum(int $id)
    {
        $query = Course::with([
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
            'sections.assignments' => function ($query) {
                $query->orderBy('due_at')->orderBy('id');
            },
        ]);

        $user = auth()->user();
        if ($user && $user->role_name === 'instructor') {
            $query->where('instructor_id', $user->id);
        }

        return $query->findOrFail($id);
    }

    public function create(array $data)
    {
        $skillIds = $data['skill_ids'] ?? null;
        unset($data['skill_ids']);

        if (($data['thumbnail'] ?? null) instanceof UploadedFile) {
            $data['thumbnail'] = $this->storeThumbnail($data['thumbnail']);
        }

        $data['slug'] = Str::slug($data['title']);

        // Force instructor_id if user is an instructor
        $user = auth()->user();
        if ($user && $user->role_name === 'instructor') {
            $data['instructor_id'] = $user->id;
        }

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

        if (($data['thumbnail'] ?? null) instanceof UploadedFile) {
            $this->deleteStoredThumbnail($course->thumbnail);
            $data['thumbnail'] = $this->storeThumbnail($data['thumbnail']);
        }

        if (isset($data['title'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        // Force instructor_id if user is an instructor
        $user = auth()->user();
        if ($user && $user->role_name === 'instructor') {
            $data['instructor_id'] = $user->id;
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
            $hasEnrollments = $this->courseHasEnrollments($courseId);

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
                        'status' => $lessonData['status'] ?? 'published',
                    ]);
                    $lesson->section_id = $section->id;
                    $lesson->save();

                    $keptLessonIds[] = $lesson->id;
                }

                if ($hasEnrollments) {
                    $section->lessons()
                        ->whereNotIn('id', $keptLessonIds)
                        ->update(['status' => 'archived']);
                } elseif (count($keptLessonIds) > 0) {
                    $section->lessons()->whereNotIn('id', $keptLessonIds)->delete();
                } else {
                    $section->lessons()->delete();
                }
            }

            if ($hasEnrollments) {
                $removedSectionExists = Section::where('course_id', $courseId)
                    ->whereNotIn('id', $keptSectionIds)
                    ->exists();

                if ($removedSectionExists) {
                    throw ValidationException::withMessages([
                        'sections' => ['Section tidak bisa dihapus karena course sudah memiliki enrollment. Archive lesson atau ubah konten tanpa menghapus section.'],
                    ]);
                }
            } elseif (count($keptSectionIds) > 0) {
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

        if ($this->courseHasEnrollments((int) $course->id) || $course->courseOfferings()->exists()) {
            throw ValidationException::withMessages([
                'course' => ['Course tidak bisa dihapus karena sudah memiliki offering atau enrollment. Nonaktifkan offering atau archive konten jika diperlukan.'],
            ]);
        }

        $course->delete();

        return true;
    }

    private function courseHasEnrollments(int $courseId): bool
    {
        return Course::query()
            ->where('id', $courseId)
            ->whereHas('courseOfferings.enrollments')
            ->exists();
    }

    private function applyPublishedOfferingScope($query): void
    {
        $query->where('is_active', true)
            ->whereHas('academicPeriod', function ($periodQuery) {
                $periodQuery->where('is_active', true);
            });
    }

    private function storeThumbnail(UploadedFile $file): string
    {
        $filename = 'course-thumbnail-' . now()->format('YmdHis') . '-' . Str::random(8) . '.' . $file->extension();
        $path = $file->storeAs('course-thumbnails', $filename, 'public');

        if (! $path) {
            throw ValidationException::withMessages([
                'thumbnail' => ['Gagal mengupload thumbnail course.'],
            ]);
        }

        return '/storage/' . ltrim($path, '/');
    }

    private function deleteStoredThumbnail(?string $path): void
    {
        if (! $path || ! Str::startsWith($path, '/storage/course-thumbnails/')) {
            return;
        }

        Storage::disk('public')->delete(Str::after($path, '/storage/'));
    }
}
