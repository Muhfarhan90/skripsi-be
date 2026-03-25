<?php

namespace Database\Seeders;

use App\Models\QuizAttempt;
use Illuminate\Database\Seeder;

class QuizAttemptSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $attempts = [
            [
                'enrollment_id' => 1,
                'quiz_id' => 1,
                'total_score' => 15,
                'status' => 'graded',
                'started_at' => '2026-03-21 08:00:00',
                'submitted_at' => '2026-03-21 08:10:00',
            ],
            [
                'enrollment_id' => 1,
                'quiz_id' => 1,
                'total_score' => 0,
                'status' => 'in_progress',
                'started_at' => '2026-03-22 10:00:00',
                'submitted_at' => null,
            ],
            [
                'enrollment_id' => 2,
                'quiz_id' => 1,
                'total_score' => 0,
                'status' => 'graded',
                'started_at' => '2026-03-23 11:00:00',
                'submitted_at' => '2026-03-23 11:09:00',
            ],
        ];

        foreach ($attempts as $attempt) {
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
