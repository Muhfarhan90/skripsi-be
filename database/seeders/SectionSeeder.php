<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sections = [
            [
                'course_id' => 1,
                'title' => 'Getting Started',
                'sort_order' => 1,
                'is_locked' => false,
            ],
            [
                'course_id' => 1,
                'title' => 'Basic Concepts',
                'sort_order' => 2,
                'is_locked' => false,
            ],
            [
                'course_id' => 2,
                'title' => 'Advanced Techniques',
                'sort_order' => 1,
                'is_locked' => true,
            ],
        ];

        foreach ($sections as $section) {
            Section::updateOrCreate(
                ['course_id' => $section['course_id'], 'title' => $section['title']],
                $section
            );
        }
    }
}
