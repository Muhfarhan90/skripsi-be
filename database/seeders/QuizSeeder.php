<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Quiz;
use App\Models\Section;
use Illuminate\Database\Seeder;

class QuizSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $course = Course::query()->where('slug', 'pemrograman-web')->first();
        if (! $course) {
            return;
        }

        $section = Section::query()
            ->where('course_id', $course->id)
            ->where('title', 'Evaluasi dan Tugas')
            ->first();

        if (! $section) {
            return;
        }

        $quizzes = [
            [
                'course_id' => $course->id,
                'section_id' => $section->id,
                'title' => 'Kuis Pemrograman Web Dasar',
                'description' => 'Kuis singkat untuk menguji pemahaman dasar HTML, CSS, JavaScript, dan alur web.',
                'duration' => 900,
                'passing_score' => 70,
                'weight' => 20,
                'is_active' => true,
                'is_random' => false,
                'max_attempts' => 3,
                'open_at' => null,
                'close_at' => null,
            ],
        ];

        foreach ($quizzes as $quiz) {
            Quiz::updateOrCreate(
                [
                    'course_id' => $quiz['course_id'],
                    'section_id' => $quiz['section_id'],
                    'title' => $quiz['title'],
                ],
                $quiz
            );
        }
    }
}
