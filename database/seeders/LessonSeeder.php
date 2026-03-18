<?php

namespace Database\Seeders;

use App\Models\Lesson;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LessonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $lessons = [
            [
                'section_id' => 1,
                'title' => 'Introduction to Programming',
                'description' => 'Learn the basics of programming with this introductory lesson.',
                'type' => 'video',
                'lesson_url' => 'https://example.com/lesson1.mp4',
                'duration' => 600,
                'sort_order' => 1,
                'is_preview' => true,
            ],
            [
                'section_id' => 1,
                'title' => 'Variables and Data Types',
                'description' => 'Understand variables and data types in programming.',
                'type' => 'video',
                'lesson_url' => 'https://example.com/lesson2.mp4',
                'duration' => 900,
                'sort_order' => 2,
                'is_preview' => false,
            ],
            [
                'section_id' => 2,
                'title' => 'Advanced Web Development Techniques',
                'description' => 'Explore advanced techniques for web development.',
                'type' => 'video',
                'lesson_url' => 'https://example.com/lesson3.mp4',
                'duration' => 1200,
                'sort_order' => 1,
                'is_preview' => false,
            ],
        ];

        foreach ($lessons as $lesson) {
            Lesson::updateOrCreate(
                ['section_id' => $lesson['section_id'], 'title' => $lesson['title']],
                $lesson
            );
        }
    }
}
