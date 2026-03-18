<?php

namespace Database\Seeders;

use App\Models\Question;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $questions = [
            [
                'quiz_id' => 1,
                'question_text' => 'What is the capital of France?',
                'type' => 'multiple_choice',
                'score' => 10,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'quiz_id' => 1,
                'question_text' => 'The sky is blue. True or False?',
                'type' => 'true_false',
                'score' => 5,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'quiz_id' => 2,
                'question_text' => 'What is the largest planet in our solar system?',
                'type' => 'multiple_choice',
                'score' => 10,
                'sort_order' => 1,
                'is_active' => true,
            ],
        ];

        foreach ($questions as $question) {
            Question::updateOrCreate(
                ['quiz_id' => $question['quiz_id'], 'question_text' => $question['question_text']],
                $question
            );
        }
    }
}
