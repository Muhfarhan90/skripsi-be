<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Section;
use Carbon\Carbon;

class CourseSnapshotService
{
    public function loadCourseForCurriculum(int $courseId): Course
    {
        return Course::withTrashed()
            ->with([
                'category:id,name',
                'instructor:id,fullname',
                'skills:id,name,slug',
                'sections' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'sections.lessons' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'sections.quizzes' => fn ($query) => $query->orderByDesc('id'),
                'sections.quizzes.questions' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                'sections.quizzes.questions.options' => fn ($query) => $query->orderBy('id'),
                'sections.assignments' => fn ($query) => $query->orderBy('id'),
            ])
            ->findOrFail($courseId);
    }

    public function needsRefresh(?array $snapshot): bool
    {
        if (! is_array($snapshot)) {
            return true;
        }

        return ! isset(
            $snapshot['course_id'],
            $snapshot['course_offering_id'],
            $snapshot['course'],
            $snapshot['sections'],
            $snapshot['ordered_items'],
            $snapshot['lesson_ids'],
            $snapshot['quiz_ids'],
            $snapshot['assignment_ids'],
            $snapshot['required_assignment_ids'],
            $snapshot['quiz_grade_items'],
            $snapshot['assignment_grade_items'],
            $snapshot['snapshot_at'],
        )
            || ! is_array($snapshot['course'])
            || ! is_array($snapshot['sections'])
            || ! is_array($snapshot['ordered_items'])
            || ! is_array($snapshot['quiz_grade_items'])
            || ! is_array($snapshot['assignment_grade_items']);
    }

    public function buildForCourseId(int $courseId, ?int $courseOfferingId = null, ?array $existingSnapshot = null): array
    {
        $course = $this->loadCourseForCurriculum($courseId);

        return $this->buildForCourse($course, $courseOfferingId, $existingSnapshot);
    }

    public function buildForCourse(Course $course, ?int $courseOfferingId = null, ?array $existingSnapshot = null): array
    {
        $snapshotLessonIds = $this->normalizeIdList($existingSnapshot['lesson_ids'] ?? null);
        $snapshotQuizIds = $this->normalizeIdList($existingSnapshot['quiz_ids'] ?? null);
        $snapshotAssignmentIds = $this->normalizeIdList($existingSnapshot['assignment_ids'] ?? null);
        $snapshotRequiredAssignmentIds = $this->normalizeIdList($existingSnapshot['required_assignment_ids'] ?? null);

        $sections = [];
        $orderedItems = [];

        foreach ($course->sections as $section) {
            $lessons = $section->lessons
                ->filter(function (Lesson $lesson) use ($snapshotLessonIds): bool {
                    if ($snapshotLessonIds !== null) {
                        return in_array((int) $lesson->id, $snapshotLessonIds, true);
                    }

                    return (string) $lesson->status === 'published';
                })
                ->values();

            $quizzes = $section->quizzes
                ->filter(function (Quiz $quiz) use ($snapshotQuizIds): bool {
                    if ($snapshotQuizIds !== null) {
                        return in_array((int) $quiz->id, $snapshotQuizIds, true);
                    }

                    return (bool) $quiz->is_active;
                })
                ->values();

            $assignments = $section->assignments
                ->filter(function (Assignment $assignment) use ($snapshotAssignmentIds): bool {
                    if ($snapshotAssignmentIds !== null) {
                        return in_array((int) $assignment->id, $snapshotAssignmentIds, true);
                    }

                    return (string) $assignment->status === 'published';
                })
                ->values();

            if ($lessons->isEmpty() && $quizzes->isEmpty() && $assignments->isEmpty()) {
                continue;
            }

            $lessonSnapshots = $lessons
                ->map(fn (Lesson $lesson) => $this->buildLessonSnapshot($lesson))
                ->values()
                ->all();

            $quizSnapshots = $quizzes
                ->map(fn (Quiz $quiz) => $this->buildQuizSnapshot($quiz))
                ->values()
                ->all();

            $assignmentSnapshots = $assignments
                ->map(fn (Assignment $assignment) => $this->buildAssignmentSnapshot($assignment))
                ->values()
                ->all();

            foreach ($lessonSnapshots as $lessonSnapshot) {
                $orderedItems[] = [
                    'type' => 'lesson',
                    'id' => (int) $lessonSnapshot['id'],
                    'section_id' => (int) $section->id,
                ];
            }

            foreach ($quizSnapshots as $quizSnapshot) {
                $orderedItems[] = [
                    'type' => 'quiz',
                    'id' => (int) $quizSnapshot['id'],
                    'section_id' => (int) $section->id,
                ];
            }

            foreach ($assignmentSnapshots as $assignmentSnapshot) {
                $orderedItems[] = [
                    'type' => 'assignment',
                    'id' => (int) $assignmentSnapshot['id'],
                    'section_id' => (int) $section->id,
                ];
            }

            $sections[] = [
                'id' => (int) $section->id,
                'course_id' => (int) $section->course_id,
                'title' => $section->title,
                'sort_order' => (int) ($section->sort_order ?? 0),
                'lessons' => $lessonSnapshots,
                'quizzes' => $quizSnapshots,
                'assignments' => $assignmentSnapshots,
            ];
        }

        $lessonIds = collect($orderedItems)
            ->where('type', 'lesson')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $quizSnapshots = collect($sections)
            ->flatMap(fn (array $section) => $section['quizzes'] ?? [])
            ->values();

        $assignmentSnapshots = collect($sections)
            ->flatMap(fn (array $section) => $section['assignments'] ?? [])
            ->values();

        $quizIds = $quizSnapshots
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $assignmentIds = $assignmentSnapshots
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $requiredAssignmentIds = $snapshotRequiredAssignmentIds !== null
            ? collect($snapshotRequiredAssignmentIds)
                ->filter(fn ($assignmentId) => in_array($assignmentId, $assignmentIds, true))
                ->values()
                ->all()
            : $assignmentSnapshots
                ->filter(fn (array $assignment) => (bool) ($assignment['is_required_for_certificate'] ?? false))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

        return [
            'course_id' => (int) $course->id,
            'course_offering_id' => $courseOfferingId,
            'course' => [
                'id' => (int) $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'description' => $course->description,
                'category_id' => $course->category_id !== null ? (int) $course->category_id : null,
                'category_name' => $course->relationLoaded('category') ? $course->category?->name : null,
                'instructor_id' => $course->instructor_id !== null ? (int) $course->instructor_id : null,
                'instructor_name' => $course->relationLoaded('instructor') ? $course->instructor?->fullname : null,
                'thumbnail' => $course->thumbnail,
                'requirements' => $course->requirements,
                'outcomes' => $course->outcomes,
                'status' => $course->getAttribute('status'),
                'skills' => $course->relationLoaded('skills')
                    ? $course->skills->map(fn ($skill) => [
                        'id' => (int) $skill->id,
                        'name' => $skill->name,
                        'slug' => $skill->slug,
                    ])->values()->all()
                    : [],
            ],
            'sections' => $sections,
            'ordered_items' => $orderedItems,
            'lesson_ids' => $lessonIds,
            'quiz_ids' => $quizIds,
            'assignment_ids' => $assignmentIds,
            'required_assignment_ids' => $requiredAssignmentIds,
            'quiz_grade_items' => $quizSnapshots
                ->map(fn (array $quiz) => [
                    'quiz_id' => (int) $quiz['id'],
                    'section_id' => $quiz['section_id'] !== null ? (int) $quiz['section_id'] : null,
                    'title' => $quiz['title'],
                    'description' => $quiz['description'],
                    'duration' => $quiz['duration'],
                    'passing_score' => $quiz['passing_score'],
                    'weight' => (int) ($quiz['weight'] ?? 0),
                    'is_active' => (bool) ($quiz['is_active'] ?? false),
                    'is_random' => (bool) ($quiz['is_random'] ?? false),
                    'question_limit' => $quiz['question_limit'],
                    'max_attempts' => $quiz['max_attempts'],
                ])
                ->values()
                ->all(),
            'assignment_grade_items' => $assignmentSnapshots
                ->map(fn (array $assignment) => [
                    'assignment_id' => (int) $assignment['id'],
                    'section_id' => $assignment['section_id'] !== null ? (int) $assignment['section_id'] : null,
                    'title' => $assignment['title'],
                    'description' => $assignment['description'],
                    'instructions' => $assignment['instructions'],
                    'is_required_for_certificate' => (bool) ($assignment['is_required_for_certificate'] ?? false),
                    'allow_resubmission' => (bool) ($assignment['allow_resubmission'] ?? false),
                    'max_attempts' => $assignment['max_attempts'],
                    'status' => $assignment['status'],
                ])
                ->values()
                ->all(),
            'snapshot_at' => $existingSnapshot['snapshot_at'] ?? now()->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    public function findLessonSnapshot(array $snapshot, int $lessonId): ?array
    {
        foreach ($snapshot['sections'] ?? [] as $section) {
            foreach ($section['lessons'] ?? [] as $lesson) {
                if ((int) ($lesson['id'] ?? 0) === $lessonId) {
                    return $lesson;
                }
            }
        }

        return null;
    }

    public function findQuizSnapshot(array $snapshot, int $quizId): ?array
    {
        foreach ($snapshot['sections'] ?? [] as $section) {
            foreach ($section['quizzes'] ?? [] as $quiz) {
                if ((int) ($quiz['id'] ?? 0) === $quizId) {
                    return $quiz;
                }
            }
        }

        return null;
    }

    public function findAssignmentSnapshot(array $snapshot, int $assignmentId): ?array
    {
        foreach ($snapshot['sections'] ?? [] as $section) {
            foreach ($section['assignments'] ?? [] as $assignment) {
                if ((int) ($assignment['id'] ?? 0) === $assignmentId) {
                    return $assignment;
                }
            }
        }

        return null;
    }

    public function findQuestionSnapshot(array $quizSnapshot, int $questionId): ?array
    {
        foreach ($quizSnapshot['questions'] ?? [] as $question) {
            if ((int) ($question['id'] ?? 0) === $questionId) {
                return $question;
            }
        }

        return null;
    }

    public function findOptionSnapshot(array $questionSnapshot, int $optionId): ?array
    {
        foreach ($questionSnapshot['options'] ?? [] as $option) {
            if ((int) ($option['id'] ?? 0) === $optionId) {
                return $option;
            }
        }

        return null;
    }

    public function makeCourseModel(array $snapshot): Course
    {
        $courseSnapshot = is_array($snapshot['course'] ?? null) ? $snapshot['course'] : [];
        $course = $this->makeModel(Course::class, [
            'id' => (int) ($courseSnapshot['id'] ?? $snapshot['course_id'] ?? 0),
            'title' => $courseSnapshot['title'] ?? null,
            'slug' => $courseSnapshot['slug'] ?? null,
            'description' => $courseSnapshot['description'] ?? null,
            'category_id' => $courseSnapshot['category_id'] ?? null,
            'instructor_id' => $courseSnapshot['instructor_id'] ?? null,
            'thumbnail' => $courseSnapshot['thumbnail'] ?? null,
            'requirements' => $courseSnapshot['requirements'] ?? null,
            'outcomes' => $courseSnapshot['outcomes'] ?? null,
            'status' => $courseSnapshot['status'] ?? null,
        ]);

        if (array_key_exists('category_name', $courseSnapshot) || array_key_exists('category_id', $courseSnapshot)) {
            $course->setRelation('category', (object) [
                'id' => $courseSnapshot['category_id'] ?? null,
                'name' => $courseSnapshot['category_name'] ?? null,
            ]);
        }

        if (array_key_exists('instructor_name', $courseSnapshot) || array_key_exists('instructor_id', $courseSnapshot)) {
            $course->setRelation('instructor', (object) [
                'id' => $courseSnapshot['instructor_id'] ?? null,
                'fullname' => $courseSnapshot['instructor_name'] ?? null,
            ]);
        }

        if (is_array($courseSnapshot['skills'] ?? null)) {
            $course->setRelation(
                'skills',
                collect($courseSnapshot['skills'])
                    ->map(fn (array $skill) => (object) $skill)
                    ->values()
            );
        }

        $course->setRelation(
            'sections',
            collect($snapshot['sections'] ?? [])
                ->map(fn (array $section) => $this->makeSectionModel($section))
                ->values()
        );

        return $course;
    }

    public function makeSectionModel(array $sectionSnapshot): Section
    {
        $section = $this->makeModel(Section::class, [
            'id' => (int) ($sectionSnapshot['id'] ?? 0),
            'course_id' => $sectionSnapshot['course_id'] ?? null,
            'title' => $sectionSnapshot['title'] ?? null,
            'sort_order' => $sectionSnapshot['sort_order'] ?? null,
        ]);
        $this->applySectionMeta($section, $sectionSnapshot);

        $section->setRelation(
            'lessons',
            collect($sectionSnapshot['lessons'] ?? [])
                ->map(fn (array $lesson) => $this->makeLessonModel($lesson))
                ->values()
        );
        $section->setRelation(
            'quizzes',
            collect($sectionSnapshot['quizzes'] ?? [])
                ->map(fn (array $quiz) => $this->makeQuizModel($quiz, false))
                ->values()
        );
        $section->setRelation(
            'assignments',
            collect($sectionSnapshot['assignments'] ?? [])
                ->map(fn (array $assignment) => $this->makeAssignmentModel($assignment))
                ->values()
        );

        return $section;
    }

    public function makeLessonModel(array $lessonSnapshot): Lesson
    {
        $lesson = $this->makeModel(Lesson::class, [
            'id' => (int) ($lessonSnapshot['id'] ?? 0),
            'section_id' => $lessonSnapshot['section_id'] ?? null,
            'title' => $lessonSnapshot['title'] ?? null,
            'description' => $lessonSnapshot['description'] ?? null,
            'type' => $lessonSnapshot['type'] ?? null,
            'lesson_url' => $lessonSnapshot['lesson_url'] ?? null,
            'duration' => $lessonSnapshot['duration'] ?? null,
            'sort_order' => $lessonSnapshot['sort_order'] ?? null,
            'is_preview' => (bool) ($lessonSnapshot['is_preview'] ?? false),
            'status' => $lessonSnapshot['status'] ?? null,
            'created_at' => $this->parseDate($lessonSnapshot['created_at'] ?? null),
            'updated_at' => $this->parseDate($lessonSnapshot['updated_at'] ?? null),
        ]);

        $this->applyLearningItemMeta($lesson, $lessonSnapshot);

        return $lesson;
    }

    public function makeQuizModel(array $quizSnapshot, bool $withQuestions = true): Quiz
    {
        $quiz = $this->makeModel(Quiz::class, [
            'id' => (int) ($quizSnapshot['id'] ?? 0),
            'course_id' => $quizSnapshot['course_id'] ?? null,
            'section_id' => $quizSnapshot['section_id'] ?? null,
            'title' => $quizSnapshot['title'] ?? null,
            'description' => $quizSnapshot['description'] ?? null,
            'duration' => $quizSnapshot['duration'] ?? null,
            'passing_score' => $quizSnapshot['passing_score'] ?? null,
            'weight' => $quizSnapshot['weight'] ?? null,
            'is_active' => (bool) ($quizSnapshot['is_active'] ?? false),
            'is_random' => (bool) ($quizSnapshot['is_random'] ?? false),
            'question_limit' => $quizSnapshot['question_limit'] ?? null,
            'max_attempts' => $quizSnapshot['max_attempts'] ?? null,
            'created_at' => $this->parseDate($quizSnapshot['created_at'] ?? null),
            'updated_at' => $this->parseDate($quizSnapshot['updated_at'] ?? null),
        ]);
        $this->applyLearningItemMeta($quiz, $quizSnapshot);

        if ($withQuestions) {
            $quiz->setRelation(
                'questions',
                collect($quizSnapshot['questions'] ?? [])
                    ->map(fn (array $question) => $this->makeQuestionModel($question))
                    ->values()
            );
        }

        return $quiz;
    }

    public function makeQuestionModel(array $questionSnapshot): Question
    {
        $question = $this->makeModel(Question::class, [
            'id' => (int) ($questionSnapshot['id'] ?? 0),
            'quiz_id' => $questionSnapshot['quiz_id'] ?? null,
            'question_text' => $questionSnapshot['question_text'] ?? null,
            'image_url' => $questionSnapshot['image_url'] ?? null,
            'type' => $questionSnapshot['type'] ?? null,
            'score' => $questionSnapshot['score'] ?? 0,
            'sort_order' => $questionSnapshot['sort_order'] ?? null,
            'is_active' => (bool) ($questionSnapshot['is_active'] ?? false),
            'created_at' => $this->parseDate($questionSnapshot['created_at'] ?? null),
            'updated_at' => $this->parseDate($questionSnapshot['updated_at'] ?? null),
        ]);

        $question->setRelation(
            'options',
            collect($questionSnapshot['options'] ?? [])
                ->map(fn (array $option) => $this->makeOptionModel($option))
                ->values()
        );

        return $question;
    }

    public function makeOptionModel(array $optionSnapshot): Option
    {
        return $this->makeModel(Option::class, [
            'id' => (int) ($optionSnapshot['id'] ?? 0),
            'question_id' => $optionSnapshot['question_id'] ?? null,
            'option_text' => $optionSnapshot['option_text'] ?? null,
            'image_url' => $optionSnapshot['image_url'] ?? null,
            'is_correct' => (bool) ($optionSnapshot['is_correct'] ?? false),
            'created_at' => $this->parseDate($optionSnapshot['created_at'] ?? null),
            'updated_at' => $this->parseDate($optionSnapshot['updated_at'] ?? null),
        ]);
    }

    public function makeAssignmentModel(array $assignmentSnapshot): Assignment
    {
        $assignment = $this->makeModel(Assignment::class, [
            'id' => (int) ($assignmentSnapshot['id'] ?? 0),
            'course_id' => $assignmentSnapshot['course_id'] ?? null,
            'section_id' => $assignmentSnapshot['section_id'] ?? null,
            'created_by' => $assignmentSnapshot['created_by'] ?? null,
            'title' => $assignmentSnapshot['title'] ?? null,
            'description' => $assignmentSnapshot['description'] ?? null,
            'instructions' => $assignmentSnapshot['instructions'] ?? null,
            'is_required_for_certificate' => (bool) ($assignmentSnapshot['is_required_for_certificate'] ?? false),
            'allow_resubmission' => (bool) ($assignmentSnapshot['allow_resubmission'] ?? false),
            'max_attempts' => $assignmentSnapshot['max_attempts'] ?? null,
            'status' => $assignmentSnapshot['status'] ?? null,
            'created_at' => $this->parseDate($assignmentSnapshot['created_at'] ?? null),
            'updated_at' => $this->parseDate($assignmentSnapshot['updated_at'] ?? null),
        ]);

        $this->applyLearningItemMeta($assignment, $assignmentSnapshot);

        return $assignment;
    }

    public function makeLessonSnapshotFromModel(Lesson $lesson): array
    {
        return $this->buildLessonSnapshot($lesson);
    }

    public function makeQuizSnapshotFromModel(Quiz $quiz): array
    {
        return $this->buildQuizSnapshot($quiz);
    }

    public function makeAssignmentSnapshotFromModel(Assignment $assignment): array
    {
        return $this->buildAssignmentSnapshot($assignment);
    }

    public function normalizeIdList(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $normalized = [];

        foreach ($value as $id) {
            $normalizedId = (int) $id;

            if ($normalizedId <= 0 || in_array($normalizedId, $normalized, true)) {
                continue;
            }

            $normalized[] = $normalizedId;
        }

        return $normalized;
    }

    private function buildLessonSnapshot(Lesson $lesson): array
    {
        return [
            'id' => (int) $lesson->id,
            'section_id' => (int) $lesson->section_id,
            'title' => $lesson->title,
            'description' => $lesson->description,
            'type' => $lesson->type,
            'lesson_url' => $lesson->lesson_url,
            'duration' => $lesson->duration !== null ? (int) $lesson->duration : null,
            'sort_order' => $lesson->sort_order !== null ? (int) $lesson->sort_order : null,
            'is_preview' => (bool) $lesson->is_preview,
            'status' => $lesson->status,
            'created_at' => $this->formatDate($lesson->created_at),
            'updated_at' => $this->formatDate($lesson->updated_at),
        ];
    }

    private function buildQuizSnapshot(Quiz $quiz): array
    {
        $questions = $quiz->questions
            ->filter(fn (Question $question) => (bool) $question->is_active)
            ->values()
            ->map(fn (Question $question) => [
                'id' => (int) $question->id,
                'quiz_id' => (int) $question->quiz_id,
                'question_text' => $question->question_text,
                'image_url' => $question->image_url,
                'type' => $question->type,
                'score' => (int) ($question->score ?? 0),
                'sort_order' => $question->sort_order !== null ? (int) $question->sort_order : null,
                'is_active' => (bool) $question->is_active,
                'options' => $question->options
                    ->map(fn (Option $option) => [
                        'id' => (int) $option->id,
                        'question_id' => (int) $option->question_id,
                        'option_text' => $option->option_text,
                        'image_url' => $option->image_url,
                        'is_correct' => (bool) $option->is_correct,
                        'created_at' => $this->formatDate($option->created_at),
                        'updated_at' => $this->formatDate($option->updated_at),
                    ])
                    ->values()
                    ->all(),
                'created_at' => $this->formatDate($question->created_at),
                'updated_at' => $this->formatDate($question->updated_at),
            ])
            ->all();

        return [
            'id' => (int) $quiz->id,
            'course_id' => (int) $quiz->course_id,
            'section_id' => $quiz->section_id !== null ? (int) $quiz->section_id : null,
            'title' => $quiz->title,
            'description' => $quiz->description,
            'duration' => $quiz->duration !== null ? (int) $quiz->duration : null,
            'passing_score' => $quiz->passing_score !== null ? (int) $quiz->passing_score : null,
            'weight' => $quiz->weight !== null ? (int) $quiz->weight : null,
            'is_active' => (bool) $quiz->is_active,
            'is_random' => (bool) $quiz->is_random,
            'question_limit' => $quiz->question_limit !== null ? (int) $quiz->question_limit : null,
            'max_attempts' => $quiz->max_attempts !== null ? (int) $quiz->max_attempts : null,
            'questions' => $questions,
            'created_at' => $this->formatDate($quiz->created_at),
            'updated_at' => $this->formatDate($quiz->updated_at),
        ];
    }

    private function buildAssignmentSnapshot(Assignment $assignment): array
    {
        return [
            'id' => (int) $assignment->id,
            'course_id' => (int) $assignment->course_id,
            'section_id' => $assignment->section_id !== null ? (int) $assignment->section_id : null,
            'created_by' => $assignment->created_by !== null ? (int) $assignment->created_by : null,
            'title' => $assignment->title,
            'description' => $assignment->description,
            'instructions' => $assignment->instructions,
            'is_required_for_certificate' => (bool) $assignment->is_required_for_certificate,
            'allow_resubmission' => (bool) $assignment->allow_resubmission,
            'max_attempts' => $assignment->max_attempts !== null ? (int) $assignment->max_attempts : null,
            'status' => $assignment->status,
            'created_at' => $this->formatDate($assignment->created_at),
            'updated_at' => $this->formatDate($assignment->updated_at),
        ];
    }

    private function makeModel(string $className, array $attributes): mixed
    {
        $model = new $className();
        $model->forceFill($attributes);
        $model->exists = true;

        return $model;
    }

    private function applySectionMeta(Section $section, array $sectionSnapshot): void
    {
        $meta = [];

        foreach (['is_supplemental', 'source'] as $key) {
            if (array_key_exists($key, $sectionSnapshot)) {
                $meta[$key] = $sectionSnapshot[$key];
            }
        }

        if ($meta !== []) {
            $section->forceFill($meta);
        }
    }

    private function applyLearningItemMeta(Lesson|Quiz|Assignment $model, array $snapshot): void
    {
        $meta = [];

        foreach ([
            'is_supplemental',
            'counts_toward_progress',
            'counts_toward_certificate',
            'source',
            'is_locked',
            'is_new',
            'is_completed',
        ] as $key) {
            if (array_key_exists($key, $snapshot)) {
                $meta[$key] = $snapshot[$key];
            }
        }

        if ($meta !== []) {
            $model->forceFill($meta);
        }
    }

    private function formatDate(mixed $value): ?string
    {
        if (! $value instanceof Carbon) {
            return null;
        }

        return $value->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        return Carbon::parse($value);
    }
}
