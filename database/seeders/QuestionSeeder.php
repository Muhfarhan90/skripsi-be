<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $quiz = Quiz::query()
            ->where('title', 'Kuis Pemrograman Web Dasar')
            ->whereHas('course', function ($query) {
                $query->where('slug', 'pemrograman-web');
            })
            ->first();

        if (! $quiz) {
            return;
        }

        $questions = [
            [
                'quiz_id' => $quiz->id,
                'question_text' => 'Tag HTML apa yang digunakan untuk membuat tautan ke halaman lain?',
                'type' => 'multiple_choice',
                'score' => 20,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'quiz_id' => $quiz->id,
                'question_text' => 'Properti CSS apa yang umum digunakan untuk mengatur warna teks?',
                'type' => 'multiple_choice',
                'score' => 20,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'quiz_id' => $quiz->id,
                'question_text' => 'JavaScript berjalan di browser untuk membuat halaman web lebih interaktif.',
                'type' => 'true_false',
                'score' => 20,
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'quiz_id' => $quiz->id,
                'question_text' => 'Metode HTTP apa yang biasanya digunakan untuk mengambil data dari server?',
                'type' => 'multiple_choice',
                'score' => 20,
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'quiz_id' => $quiz->id,
                'question_text' => 'Responsive design bertujuan agar tampilan web menyesuaikan berbagai ukuran layar.',
                'type' => 'true_false',
                'score' => 20,
                'sort_order' => 5,
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
