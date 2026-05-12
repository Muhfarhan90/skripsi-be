<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Database\Seeder;

class LessonProgressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $activeStudentId = User::where('email', 'student@example.com')->value('id');
        $completedStudentId = User::where('email', 'student.completed@example.com')->value('id');

        $activeEnrollment = Enrollment::query()
            ->where('user_id', $activeStudentId)
            ->whereHas('courseOffering', function ($query) {
                $query->where('title', 'Intro Programming - Cohort A1 2026');
            })
            ->first();

        $completedEnrollment = Enrollment::query()
            ->where('user_id', $completedStudentId)
            ->whereHas('courseOffering', function ($query) {
                $query->where('title', 'Intro Programming - Cohort Legacy 2025');
            })
            ->first();

        $lessonIntroId = Lesson::where('title', 'Introduction to Programming')->value('id');
        $lessonVariablesId = Lesson::where('title', 'Variables and Data Types')->value('id');
        $lessonAdvancedId = Lesson::where('title', 'Advanced Web Development Techniques')->value('id');

        if ($activeEnrollment && $lessonIntroId) {
            LessonProgress::updateOrCreate(
                [
                    'enrollment_id' => $activeEnrollment->id,
                    'lesson_id' => $lessonIntroId,
                ],
                [
                    'progress_seconds' => 600,
                    'last_accessed_at' => now()->subDays(2),
                    'completed_at' => now()->subDays(2),
                ]
            );
        }

        if ($activeEnrollment && $lessonVariablesId) {
            LessonProgress::updateOrCreate(
                [
                    'enrollment_id' => $activeEnrollment->id,
                    'lesson_id' => $lessonVariablesId,
                ],
                [
                    'progress_seconds' => 300,
                    'last_accessed_at' => now()->subDay(),
                    'completed_at' => null,
                ]
            );
        }

        if ($activeEnrollment) {
            $activeEnrollment->update([
                'last_lesson_id' => $lessonVariablesId,
                'progress' => 33,
            ]);
        }

        if ($completedEnrollment && $lessonIntroId) {
            LessonProgress::updateOrCreate(
                [
                    'enrollment_id' => $completedEnrollment->id,
                    'lesson_id' => $lessonIntroId,
                ],
                [
                    'progress_seconds' => 600,
                    'last_accessed_at' => now()->subDays(105),
                    'completed_at' => now()->subDays(110),
                ]
            );
        }

        if ($completedEnrollment && $lessonVariablesId) {
            LessonProgress::updateOrCreate(
                [
                    'enrollment_id' => $completedEnrollment->id,
                    'lesson_id' => $lessonVariablesId,
                ],
                [
                    'progress_seconds' => 900,
                    'last_accessed_at' => now()->subDays(101),
                    'completed_at' => now()->subDays(106),
                ]
            );
        }

        if ($completedEnrollment && $lessonAdvancedId) {
            LessonProgress::updateOrCreate(
                [
                    'enrollment_id' => $completedEnrollment->id,
                    'lesson_id' => $lessonAdvancedId,
                ],
                [
                    'progress_seconds' => 1200,
                    'last_accessed_at' => now()->subDays(98),
                    'completed_at' => now()->subDays(103),
                ]
            );
        }

        if ($completedEnrollment) {
            $completedEnrollment->update([
                'last_lesson_id' => $lessonAdvancedId,
                'progress' => 100,
                'status' => 'completed',
                'completed_at' => $completedEnrollment->completed_at ?? now()->subDays(95),
            ]);
        }
    }
}
