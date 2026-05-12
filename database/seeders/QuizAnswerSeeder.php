<?php

namespace Database\Seeders;

use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Seeder;

class QuizAnswerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $activeStudentId = User::where('email', 'student@example.com')->value('id');
        $completedStudentId = User::where('email', 'student.completed@example.com')->value('id');

        $quiz1Id = Quiz::where('title', 'Quiz 1: Basics of Programming')->value('id');
        $quiz2Id = Quiz::where('title', 'Quiz 2: Control Structures')->value('id');

        $attempt1 = QuizAttempt::query()
            ->where('quiz_id', $quiz1Id)
            ->where('status', 'graded')
            ->whereHas('enrollment', function ($query) use ($activeStudentId) {
                $query->where('user_id', $activeStudentId)
                    ->whereHas('courseOffering', function ($offeringQuery) {
                        $offeringQuery->where('title', 'Intro Programming - Cohort A1 2026');
                    });
            })
            ->first();

        $attempt2 = QuizAttempt::query()
            ->where('quiz_id', $quiz1Id)
            ->where('status', 'in_progress')
            ->whereHas('enrollment', function ($query) use ($activeStudentId) {
                $query->where('user_id', $activeStudentId)
                    ->whereHas('courseOffering', function ($offeringQuery) {
                        $offeringQuery->where('title', 'Intro Programming - Cohort A1 2026');
                    });
            })
            ->first();

        $attempt3 = QuizAttempt::query()
            ->where('quiz_id', $quiz2Id)
            ->where('status', 'graded')
            ->whereHas('enrollment', function ($query) use ($completedStudentId) {
                $query->where('user_id', $completedStudentId)
                    ->whereHas('courseOffering', function ($offeringQuery) {
                        $offeringQuery->where('title', 'Intro Programming - Cohort Legacy 2025');
                    });
            })
            ->first();

        if (! $attempt1 || ! $attempt2 || ! $attempt3) {
            return;
        }

        $capitalQuestionId = Question::where('question_text', 'What is the capital of France?')->value('id');
        $trueFalseQuestionId = Question::where('question_text', 'The sky is blue. True or False?')->value('id');
        $planetQuestionId = Question::where('question_text', 'What is the largest planet in our solar system?')->value('id');

        $parisOptionId = Option::where('question_id', $capitalQuestionId)->where('option_text', 'Paris')->value('id');
        $londonOptionId = Option::where('question_id', $capitalQuestionId)->where('option_text', 'London')->value('id');
        $jupiterOptionId = Option::where('question_id', $planetQuestionId)->where('option_text', 'Jupiter')->value('id');

        $answers = [
            [
                'attempt_id' => $attempt1->id,
                'question_id' => $capitalQuestionId,
                'selected_option_id' => $parisOptionId,
                'answer_text' => null,
                'is_correct' => true,
                'score' => 10,
            ],
            [
                'attempt_id' => $attempt1->id,
                'question_id' => $trueFalseQuestionId,
                'selected_option_id' => null,
                'answer_text' => 'True',
                'is_correct' => true,
                'score' => 5,
            ],
            [
                'attempt_id' => $attempt2->id,
                'question_id' => $capitalQuestionId,
                'selected_option_id' => $londonOptionId,
                'answer_text' => null,
                'is_correct' => false,
                'score' => 0,
            ],
            [
                'attempt_id' => $attempt3->id,
                'question_id' => $planetQuestionId,
                'selected_option_id' => $jupiterOptionId,
                'answer_text' => null,
                'is_correct' => true,
                'score' => 10,
            ],
        ];

        foreach ($answers as $answer) {
            if (! $answer['question_id']) {
                continue;
            }

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
