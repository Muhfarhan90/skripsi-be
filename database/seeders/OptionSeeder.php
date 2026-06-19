<?php

namespace Database\Seeders;

use App\Models\Option;
use App\Models\Question;
use Illuminate\Database\Seeder;

class OptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $questionByText = Question::query()
            ->whereHas('quiz', function ($query) {
                $query
                    ->where('title', 'Kuis Pemrograman Web Dasar')
                    ->whereHas('course', function ($courseQuery) {
                        $courseQuery->where('slug', 'pemrograman-web');
                    });
            })
            ->pluck('id', 'question_text');

        $optionsByQuestion = [
            'Tag HTML apa yang digunakan untuk membuat tautan ke halaman lain?' => [
                ['option_text' => '<a>', 'is_correct' => true],
                ['option_text' => '<link>', 'is_correct' => false],
                ['option_text' => '<button>', 'is_correct' => false],
                ['option_text' => '<section>', 'is_correct' => false],
            ],
            'Properti CSS apa yang umum digunakan untuk mengatur warna teks?' => [
                ['option_text' => 'color', 'is_correct' => true],
                ['option_text' => 'background-color', 'is_correct' => false],
                ['option_text' => 'font-size', 'is_correct' => false],
                ['option_text' => 'display', 'is_correct' => false],
            ],
            'JavaScript berjalan di browser untuk membuat halaman web lebih interaktif.' => [
                ['option_text' => 'True', 'is_correct' => true],
                ['option_text' => 'False', 'is_correct' => false],
            ],
            'Metode HTTP apa yang biasanya digunakan untuk mengambil data dari server?' => [
                ['option_text' => 'GET', 'is_correct' => true],
                ['option_text' => 'POST', 'is_correct' => false],
                ['option_text' => 'DELETE', 'is_correct' => false],
                ['option_text' => 'PATCH', 'is_correct' => false],
            ],
            'Responsive design bertujuan agar tampilan web menyesuaikan berbagai ukuran layar.' => [
                ['option_text' => 'True', 'is_correct' => true],
                ['option_text' => 'False', 'is_correct' => false],
            ],
        ];

        foreach ($optionsByQuestion as $questionText => $options) {
            $questionId = $questionByText->get($questionText);
            if (! $questionId) {
                continue;
            }

            foreach ($options as $option) {
                Option::updateOrCreate(
                    [
                        'question_id' => $questionId,
                        'option_text' => $option['option_text'],
                    ],
                    [
                        'question_id' => $questionId,
                        'option_text' => $option['option_text'],
                        'image_url' => null,
                        'is_correct' => $option['is_correct'],
                    ]
                );
            }
        }
    }
}
