<?php

namespace Database\Seeders;

use App\Models\Quiz;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class QuizSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $quizzes = [
            [
                'course_id' => 1,
                'section_id' => 1,
                'title' => 'Quiz 1: Basics of Programming',
                'description' => 'Test your understanding of basic programming concepts.',
                'duration' => 600, // 10 minutes
                'passing_score' => 70,
                'weight' => 10,
                'is_active' => true,
                'max_attempts' => 3,
            ],
            [
                'course_id' => 1,
                'section_id' => 2,
                'title' => 'Quiz 2: Control Structures',
                'description' => 'Assess your knowledge of control structures in programming.',
                'duration' => 900, // 15 minutes
                'passing_score' => 75,
                'weight' => 15,
                'is_active' => true,
                'max_attempts' => 3,
            ],
            [
                'course_id' => 2,
                'section_id' => 3,
                'title' => 'Quiz: Advanced Web Development',
                'description' => 'Evaluate your skills in advanced web development techniques.',
                'duration' => 1200, // 20 minutes
                'passing_score' => 80,
                'weight' => 20,
                'is_active' => true,
                'max_attempts' => 3,
            ],
        ];

        foreach ($quizzes as $quiz) {
            Quiz::updateOrCreate($quiz);
        }
    }
}
