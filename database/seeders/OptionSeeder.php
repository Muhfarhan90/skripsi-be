<?php

namespace Database\Seeders;

use App\Models\Option;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $options = [
            [
                'question_id' => 1,
                'option_text' => 'Paris',
                'image_url' => null,
                'is_correct' => true,
            ],
            [
                'question_id' => 1,
                'option_text' => 'London',
                'image_url' => null,
                'is_correct' => false,
            ],
            [
                'question_id' => 1,
                'option_text' => 'Berlin',
                'image_url' => null,
                'is_correct' => false,
            ],
            [
                'question_id' => 3,
                'option_text' => 'Jupiter',
                'image_url' => null,
                'is_correct' => true,
            ],
            [
                'question_id' => 3,
                'option_text' => 'Saturn',
                'image_url' => null,
                'is_correct' => false,
            ],
            [
                'question_id' => 3,
                'option_text' => 'Neptune',
                'image_url' => null,
                'is_correct' => false,
            ],
        ];

        foreach ($options as $option) {
            Option::updateOrCreate(
                ['question_id' => $option['question_id'], 'option_text' => $option['option_text']],
                $option
            );
        }
    }
}
