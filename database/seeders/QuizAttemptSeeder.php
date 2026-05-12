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
                $query->where('title', 'Intro Programming - Cohort A1 2026');
            })
            ->value('id');

        $completedEnrollmentId = Enrollment::query()
            ->where('user_id', $completedStudentId)
            ->whereHas('courseOffering', function ($query) {
                $query->where('title', 'Intro Programming - Cohort Legacy 2025');
            })
            ->value('id');

        $quiz1Id = Quiz::where('title', 'Quiz 1: Basics of Programming')->value('id');
        $quiz2Id = Quiz::where('title', 'Quiz 2: Control Structures')->value('id');

        $attempts = [
            [
                'enrollment_id' => $activeEnrollmentId,
                'quiz_id' => $quiz1Id,
                'total_score' => 15,
                'status' => 'graded',
                'started_at' => now()->subDays(6)->setTime(8, 0),
                'submitted_at' => now()->subDays(6)->setTime(8, 10),
            ],
            [
                'enrollment_id' => $activeEnrollmentId,
                'quiz_id' => $quiz1Id,
                'total_score' => 0,
                'status' => 'in_progress',
                'started_at' => now()->subDays(2)->setTime(10, 0),
                'submitted_at' => null,
            ],
            [
                'enrollment_id' => $completedEnrollmentId,
                'quiz_id' => $quiz2Id,
                'total_score' => 10,
                'status' => 'graded',
                'started_at' => now()->subDays(102)->setTime(11, 0),
                'submitted_at' => now()->subDays(102)->setTime(11, 9),
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
                    'started_at' => $attempt['started_at'],
                ],
                $attempt
            );
        }
    }
}
