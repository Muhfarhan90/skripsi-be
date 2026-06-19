<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Seeder;

class QuizAttemptSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $activeStudentId = User::where('email', 'student@example.com')->value('id');
        $completedStudentId = User::where('email', 'student.completed@example.com')->value('id');

        $activeEnrollmentId = Enrollment::query()
            ->where('user_id', $activeStudentId)
            ->whereHas('courseOffering', function ($query) {
                $query
                    ->whereHas('course', function ($courseQuery) {
                        $courseQuery->where('slug', 'pemrograman-web');
                    })
                    ->whereHas('academicPeriod', function ($periodQuery) {
                        $periodQuery->where('code', 'PRE-U-2026-A');
                    });
            })
            ->value('id');

        $completedEnrollmentId = Enrollment::query()
            ->where('user_id', $completedStudentId)
            ->whereHas('courseOffering', function ($query) {
                $query
                    ->whereHas('course', function ($courseQuery) {
                        $courseQuery->where('slug', 'pemrograman-web');
                    })
                    ->whereHas('academicPeriod', function ($periodQuery) {
                        $periodQuery->where('code', 'PRE-U-2025-B');
                    });
            })
            ->value('id');

        $quizId = Quiz::query()
            ->where('title', 'Kuis Pemrograman Web Dasar')
            ->whereHas('course', function ($query) {
                $query->where('slug', 'pemrograman-web');
            })
            ->value('id');

        $attempts = [
            [
                'enrollment_id' => $activeEnrollmentId,
                'quiz_id' => $quizId,
                'total_score' => 100,
                'status' => 'graded',
                'started_at' => now()->subDays(6)->setTime(8, 0),
                'submitted_at' => now()->subDays(6)->setTime(8, 12),
            ],
            [
                'enrollment_id' => $activeEnrollmentId,
                'quiz_id' => $quizId,
                'total_score' => 0,
                'status' => 'in_progress',
                'started_at' => now()->subDays(2)->setTime(10, 0),
                'submitted_at' => null,
            ],
            [
                'enrollment_id' => $completedEnrollmentId,
                'quiz_id' => $quizId,
                'total_score' => 100,
                'status' => 'graded',
                'started_at' => now()->subDays(102)->setTime(11, 0),
                'submitted_at' => now()->subDays(102)->setTime(11, 12),
            ],
        ];

        foreach ($attempts as $attempt) {
            if (! $attempt['enrollment_id'] || ! $attempt['quiz_id']) {
                continue;
            }

            QuizAttempt::updateOrCreate(
                [
                    'enrollment_id' => $attempt['enrollment_id'],
                    'quiz_id' => $attempt['quiz_id'],
                    'status' => $attempt['status'],
                ],
                $attempt
            );
        }
    }
}
