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

        $quizId = Quiz::query()
            ->where('title', 'Kuis Pemrograman Web Dasar')
            ->whereHas('course', function ($query) {
                $query->where('slug', 'pemrograman-web');
            })
            ->value('id');

        if (! $quizId) {
            return;
        }

        $attempt1 = QuizAttempt::query()
            ->where('quiz_id', $quizId)
            ->where('status', 'graded')
            ->whereHas('enrollment', function ($query) use ($activeStudentId) {
                $query->where('user_id', $activeStudentId)
                    ->whereHas('courseOffering', function ($offeringQuery) {
                        $offeringQuery
                            ->whereHas('course', function ($courseQuery) {
                                $courseQuery->where('slug', 'pemrograman-web');
                            })
                            ->whereHas('academicPeriod', function ($periodQuery) {
                                $periodQuery->where('code', 'PRE-U-2026-A');
                            });
                    });
            })
            ->first();

        $attempt2 = QuizAttempt::query()
            ->where('quiz_id', $quizId)
            ->where('status', 'in_progress')
            ->whereHas('enrollment', function ($query) use ($activeStudentId) {
                $query->where('user_id', $activeStudentId)
                    ->whereHas('courseOffering', function ($offeringQuery) {
                        $offeringQuery
                            ->whereHas('course', function ($courseQuery) {
                                $courseQuery->where('slug', 'pemrograman-web');
                            })
                            ->whereHas('academicPeriod', function ($periodQuery) {
                                $periodQuery->where('code', 'PRE-U-2026-A');
                            });
                    });
            })
            ->first();

        $attempt3 = QuizAttempt::query()
            ->where('quiz_id', $quizId)
            ->where('status', 'graded')
            ->whereHas('enrollment', function ($query) use ($completedStudentId) {
                $query->where('user_id', $completedStudentId)
                    ->whereHas('courseOffering', function ($offeringQuery) {
                        $offeringQuery
                            ->whereHas('course', function ($courseQuery) {
                                $courseQuery->where('slug', 'pemrograman-web');
                            })
                            ->whereHas('academicPeriod', function ($periodQuery) {
                                $periodQuery->where('code', 'PRE-U-2025-B');
                            });
                    });
            })
            ->first();

        if (! $attempt1 && ! $attempt2 && ! $attempt3) {
            return;
        }

        $questionByText = Question::query()
            ->where('quiz_id', $quizId)
            ->pluck('id', 'question_text');

        $correctAnswerByQuestion = [
            'Tag HTML apa yang digunakan untuk membuat tautan ke halaman lain?' => '<a>',
            'Properti CSS apa yang umum digunakan untuk mengatur warna teks?' => 'color',
            'JavaScript berjalan di browser untuk membuat halaman web lebih interaktif.' => 'True',
            'Metode HTTP apa yang biasanya digunakan untuk mengambil data dari server?' => 'GET',
            'Responsive design bertujuan agar tampilan web menyesuaikan berbagai ukuran layar.' => 'True',
        ];

        foreach ([$attempt1, $attempt3] as $attempt) {
            if (! $attempt) {
                continue;
            }

            foreach ($correctAnswerByQuestion as $questionText => $optionText) {
                $questionId = $questionByText->get($questionText);
                if (! $questionId) {
                    continue;
                }

                $optionId = Option::query()
                    ->where('question_id', $questionId)
                    ->where('option_text', $optionText)
                    ->value('id');

                QuizAnswer::updateOrCreate(
                    [
                        'attempt_id' => $attempt->id,
                        'question_id' => $questionId,
                    ],
                    [
                        'attempt_id' => $attempt->id,
                        'question_id' => $questionId,
                        'selected_option_id' => $optionId,
                        'answer_text' => null,
                        'is_correct' => true,
                        'score' => 20,
                    ]
                );
            }
        }

        if (! $attempt2) {
            return;
        }

        $firstQuestionId = $questionByText->get('Tag HTML apa yang digunakan untuk membuat tautan ke halaman lain?');
        $wrongOptionId = Option::query()
            ->where('question_id', $firstQuestionId)
            ->where('option_text', '<button>')
            ->value('id');

        if ($firstQuestionId) {
            QuizAnswer::updateOrCreate(
                [
                    'attempt_id' => $attempt2->id,
                    'question_id' => $firstQuestionId,
                ],
                [
                    'attempt_id' => $attempt2->id,
                    'question_id' => $firstQuestionId,
                    'selected_option_id' => $wrongOptionId,
                    'answer_text' => null,
                    'is_correct' => false,
                    'score' => 0,
                ]
            );
        }
    }
}
