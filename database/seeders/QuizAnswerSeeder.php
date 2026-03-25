<?php

namespace Database\Seeders;

use App\Models\Option;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use Illuminate\Database\Seeder;

class QuizAnswerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $attempt1 = QuizAttempt::where('enrollment_id', 1)
            ->where('quiz_id', 1)
            ->where('started_at', '2026-03-21 08:00:00')
            ->first();

        $attempt2 = QuizAttempt::where('enrollment_id', 1)
            ->where('quiz_id', 1)
            ->where('started_at', '2026-03-22 10:00:00')
            ->first();

        $attempt3 = QuizAttempt::where('enrollment_id', 2)
            ->where('quiz_id', 1)
            ->where('started_at', '2026-03-23 11:00:00')
            ->first();

        if (! $attempt1 || ! $attempt2 || ! $attempt3) {
            return;
        }

        $parisOptionId = Option::where('question_id', 1)->where('option_text', 'Paris')->value('id');
        $londonOptionId = Option::where('question_id', 1)->where('option_text', 'London')->value('id');

        $answers = [
            [
                'attempt_id' => $attempt1->id,
                'question_id' => 1,
                'selected_option_id' => $parisOptionId,
                'answer_text' => null,
                'is_correct' => true,
                'score' => 10,
            ],
            [
                'attempt_id' => $attempt1->id,
                'question_id' => 2,
                'selected_option_id' => null,
                'answer_text' => 'True',
                'is_correct' => true,
                'score' => 5,
            ],
            [
                'attempt_id' => $attempt2->id,
                'question_id' => 1,
                'selected_option_id' => $londonOptionId,
                'answer_text' => null,
                'is_correct' => false,
                'score' => 0,
            ],
            [
                'attempt_id' => $attempt3->id,
                'question_id' => 1,
                'selected_option_id' => $londonOptionId,
                'answer_text' => null,
                'is_correct' => false,
                'score' => 0,
            ],
        ];

        foreach ($answers as $answer) {
            QuizAnswer::updateOrCreate(
                [
                    'attempt_id' => $answer['attempt_id'],
                    'question_id' => $answer['question_id'],
                ],
                $answer
            );
        }
    }
}
