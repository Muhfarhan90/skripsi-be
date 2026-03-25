<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\LessonProgress;
use Illuminate\Database\Seeder;

class LessonProgressSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = [
            [
                'enrollment_id' => 1,
                'lesson_id' => 1,
                'progress_seconds' => 600,
                'last_accessed_at' => now()->subDays(2),
                'completed_at' => now()->subDays(2),
            ],
            [
                'enrollment_id' => 1,
                'lesson_id' => 2,
                'progress_seconds' => 300,
                'last_accessed_at' => now()->subDay(),
                'completed_at' => null,
            ],
            [
                'enrollment_id' => 2,
                'lesson_id' => 1,
                'progress_seconds' => 600,
                'last_accessed_at' => now()->subHours(12),
                'completed_at' => now()->subHours(12),
            ],
        ];

        foreach ($rows as $row) {
            LessonProgress::updateOrCreate(
                [
                    'enrollment_id' => $row['enrollment_id'],
                    'lesson_id' => $row['lesson_id'],
                ],
                $row
            );
        }

        Enrollment::where('id', 1)->update([
            'last_lesson_id' => 2,
            'progress' => 33,
        ]);

        Enrollment::where('id', 2)->update([
            'last_lesson_id' => 1,
            'progress' => 33,
        ]);
    }
}
