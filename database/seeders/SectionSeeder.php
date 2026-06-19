<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Section;
use Illuminate\Database\Seeder;

class SectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $courseBySlug = Course::query()->pluck('id', 'slug');

        $sections = [
            [
                'course_slug' => 'pemrograman-web',
                'title' => 'Evaluasi dan Tugas',
                'sort_order' => 1,
            ],
            [
                'course_slug' => 'dasar-kedokteran-klinis',
                'title' => 'Evaluasi dan Tugas',
                'sort_order' => 1,
            ],
            [
                'course_slug' => 'teknologi-pertanian-modern',
                'title' => 'Evaluasi dan Tugas',
                'sort_order' => 1,
            ],
        ];

        foreach ($sections as $section) {
            $courseId = $courseBySlug->get($section['course_slug']);
            if (! $courseId) {
                continue;
            }

            Section::updateOrCreate(
                [
                    'course_id' => $courseId,
                    'title' => $section['title'],
                ],
                [
                    'course_id' => $courseId,
                    'title' => $section['title'],
                    'sort_order' => $section['sort_order'],
                ]
            );
        }
    }
}
